# Diner Ordering Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the backend (routes, controllers, validation, model logic) for the public diner flow at `/table/{code}`: viewing the menu, opening a dining session, and placing orders (rounds) with optional per-item allergens.

**Architecture:** `GET /table/{code}` stays an Inertia page render (page navigation). The two write actions — opening a session and placing an order — are plain JSON endpoints (`response()->json(...)`) rather than Inertia responses, since they're called as background actions from within the page (cart submission, "seat us" form) rather than full page navigations. Business logic for creating an order (snapshotting dish/allergen data into `OrderItem`/`OrderItemAllergen`) lives on `DiningSession::placeOrder()`, following the existing pattern of domain logic living on models (e.g. `Order::syncStatusFromItems()`).

**Tech Stack:** Laravel 13, Inertia.js (page render only), PHPUnit Feature tests, SQLite in-memory test DB.

## Global Constraints

- No JSON API/resource layer exists elsewhere in this app — these two endpoints are a deliberate, scoped exception for action-style requests, not page navigations. Don't introduce `App\Http\Resources` for this; return models directly (existing app convention has no resource layer at all).
- Ordering beyond a dish's `per_round_limit` is never blocked — pricing consequences are out of scope for this plan (deferred to a future Payment design).
- `App\Models\Dish`, `App\Models\Allergen`, `App\Models\RestaurantTable`, `App\Models\DiningSession`, `App\Models\Order`, `App\Models\OrderItem`, `App\Models\OrderItemAllergen`, `App\Models\Category` already exist — modify, don't recreate.
- Tests in this codebase use direct `Model::create([...])` calls, not factories (only `UserFactory` exists) — follow that convention.
- All new routes are public, no `auth` middleware, consistent with the existing `/table/{code}` route.

---

## Task 1: Add `is_extra` to the dishes schema

**Files:**
- Modify: `database/migrations/2026_06_24_091356_create_dishes_table.php`
- Modify: `app/Models/Dish.php`
- Test: `tests/Feature/DishTest.php`

**Interfaces:**
- Produces: `Dish::$casts['is_extra'] => 'boolean'`, fillable `is_extra`, default `false` at the DB level.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/DishTest.php` (inside the existing `DishTest` class — check the file first for the exact class name/imports already present, then add this method):

```php
    public function test_dish_is_extra_defaults_to_false(): void
    {
        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);

        $dish = Dish::create([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
        ]);

        $this->assertFalse($dish->is_extra);
    }

    public function test_dish_is_extra_can_be_set_true(): void
    {
        $category = Category::create(['code' => 'drinks', 'label' => 'Drinks', 'sort_order' => 2]);
        $station = Station::create(['code' => 'bar', 'label' => 'Bar', 'short_label' => 'BAR', 'color' => '#00ff00']);

        $dish = Dish::create([
            'name' => 'Craft Soda',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'is_extra' => true,
        ]);

        $this->assertTrue($dish->is_extra);
    }
```

Check the top of `tests/Feature/DishTest.php` for existing `use App\Models\Category;` and `use App\Models\Station;` imports — add them if missing (the existing tests in that file already create dishes, so likely already imported; verify, don't duplicate).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_dish_is_extra_defaults_to_false`
Expected: FAIL — `is_extra` column doesn't exist (`SQLSTATE` error) or assertion fails because the attribute is `null`.

- [ ] **Step 3: Alter the migration**

In `database/migrations/2026_06_24_091356_create_dishes_table.php`, add the new column right after `is_custom`:

```php
            $table->boolean('is_available')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_extra')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
```

- [ ] **Step 4: Update the `Dish` model**

In `app/Models/Dish.php`, add `'is_extra'` to the `#[Fillable]` array and to `$casts`:

```php
#[Fillable([
    'name',
    'description',
    'prep_minutes',
    'category_id',
    'station_id',
    'price',
    'per_round_limit',
    'is_available',
    'is_custom',
    'is_extra',
    'sort_order',
])]
class Dish extends Model
{
    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_custom' => 'boolean',
        'is_extra' => 'boolean',
    ];
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=DishTest`
Expected: PASS (all `DishTest` tests, including the two new ones).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_24_091356_create_dishes_table.php app/Models/Dish.php tests/Feature/DishTest.php
git commit -m "Add is_extra flag to dishes"
```

---

## Task 2: `DiningSession::placeOrder()` model logic

**Files:**
- Modify: `app/Models/DiningSession.php`
- Test: `tests/Feature/DiningSessionTest.php`

**Interfaces:**
- Consumes: `Dish` (existing), `Allergen` (existing), `Order::orderItems()` / `OrderItem::orderItemAllergens()` relations (existing).
- Produces: `DiningSession::placeOrder(array $items): Order` where `$items` is `array<int, array{dish_id: int, qty: int, note?: ?string, allergen_ids?: int[]}>`. Returns the created `Order` with `orderItems.orderItemAllergens` eager-loaded. Later tasks (3, 4) call this method directly.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/DiningSessionTest.php` (add `use App\Models\Allergen;` and `use App\Models\Category;`, `use App\Models\Dish;`, `use App\Models\Station;` imports at the top if not already present):

```php
    public function test_place_order_creates_an_order_with_snapshotted_items(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
        $dish = Dish::create([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => 12.50,
        ]);
        $allergen = Allergen::create(['code' => 'nuts', 'label' => 'Contains nuts']);

        $order = $session->placeOrder([
            [
                'dish_id' => $dish->id,
                'qty' => 2,
                'note' => 'No salt',
                'allergen_ids' => [$allergen->id],
            ],
        ]);

        $this->assertSame(1, $order->round);
        $this->assertSame(1, $order->number);
        $this->assertSame($session->id, $order->dining_session_id);

        $item = $order->orderItems->first();
        $this->assertSame('Grilled Chicken', $item->name);
        $this->assertEquals(12.50, $item->unit_price);
        $this->assertSame(2, $item->qty);
        $this->assertSame('No salt', $item->note);
        $this->assertSame($station->id, $item->station_id);
        $this->assertSame('firing', $item->status);

        $itemAllergen = $item->orderItemAllergens->first();
        $this->assertSame('Contains nuts', $itemAllergen->label);
        $this->assertSame($allergen->id, $itemAllergen->allergen_id);
    }

    public function test_place_order_increments_round_and_number_on_successive_calls(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);

        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
        $dish = Dish::create([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => 12.50,
        ]);

        $first = $session->placeOrder([['dish_id' => $dish->id, 'qty' => 1]]);
        $second = $session->placeOrder([['dish_id' => $dish->id, 'qty' => 1]]);

        $this->assertSame(1, $first->round);
        $this->assertSame(2, $second->round);
        $this->assertSame(2, $second->number);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_place_order_creates_an_order_with_snapshotted_items`
Expected: FAIL — `Call to undefined method App\Models\DiningSession::placeOrder()`.

- [ ] **Step 3: Implement `placeOrder()`**

In `app/Models/DiningSession.php`, add these imports at the top:

```php
use App\Models\Allergen;
use App\Models\Dish;
use Illuminate\Support\Facades\DB;
```

Add the method to the `DiningSession` class (after `payment()`, before `booted()`):

```php
    public function placeOrder(array $items): Order
    {
        return DB::transaction(function () use ($items) {
            $nextRound = ((int) $this->orders()->max('round')) + 1;

            $order = $this->orders()->create([
                'number' => $nextRound,
                'round' => $nextRound,
                'placed_at' => now(),
            ]);

            foreach ($items as $item) {
                $dish = Dish::findOrFail($item['dish_id']);

                $orderItem = $order->orderItems()->create([
                    'dish_id' => $dish->id,
                    'name' => $dish->name,
                    'station_id' => $dish->station_id,
                    'unit_price' => $dish->price,
                    'qty' => $item['qty'],
                    'note' => $item['note'] ?? null,
                ]);

                foreach ($item['allergen_ids'] ?? [] as $allergenId) {
                    $allergen = Allergen::findOrFail($allergenId);

                    $orderItem->orderItemAllergens()->create([
                        'allergen_id' => $allergen->id,
                        'label' => $allergen->label,
                    ]);
                }
            }

            return $order->load('orderItems.orderItemAllergens');
        });
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DiningSessionTest`
Expected: PASS (all `DiningSessionTest` tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/DiningSession.php tests/Feature/DiningSessionTest.php
git commit -m "Add DiningSession::placeOrder to snapshot dish/allergen data onto orders"
```

---

## Task 3: Open a dining session from the diner flow

**Files:**
- Create: `app/Http/Requests/Diner/StoreDinerSessionRequest.php`
- Create: `app/Http/Controllers/Diner/DinerSessionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Diner/DinerSessionControllerTest.php`

**Interfaces:**
- Consumes: `RestaurantTable` (existing), `TableOccupiedException` (existing), `DiningSession` (existing).
- Produces: `POST /table/{code}/session` route, named `diner.session.store`. JSON `201` with `{"session": {...}}` on success; JSON `409` with `{"message": "...", "session": {...}}` when a session is already active.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Diner/DinerSessionControllerTest.php`:

```php
<?php

namespace Tests\Feature\Diner;

use App\Models\DiningSession;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinerSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_opens_a_new_session_when_none_is_active(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $response = $this->postJson('/table/T1/session', ['guests' => 3]);

        $response->assertCreated();
        $this->assertDatabaseHas('dining_sessions', [
            'guests' => 3,
            'status' => 'seated',
        ]);
    }

    public function test_returns_409_and_does_not_duplicate_when_a_session_is_already_active(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);

        $response = $this->postJson('/table/T1/session', ['guests' => 5]);

        $response->assertStatus(409);
        $this->assertSame(1, DiningSession::count());
    }

    public function test_returns_404_for_an_unknown_table_code(): void
    {
        $response = $this->postJson('/table/UNKNOWN/session', ['guests' => 2]);

        $response->assertNotFound();
    }

    public function test_guests_is_required_and_must_be_a_positive_integer(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $response = $this->postJson('/table/T1/session', ['guests' => 0]);

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DinerSessionControllerTest`
Expected: FAIL — `404 Not Found` (route doesn't exist) on all tests.

- [ ] **Step 3: Create the form request**

Create `app/Http/Requests/Diner/StoreDinerSessionRequest.php`:

```php
<?php

namespace App\Http\Requests\Diner;

use Illuminate\Foundation\Http\FormRequest;

class StoreDinerSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guests' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Diner/DinerSessionController.php`:

```php
<?php

namespace App\Http\Controllers\Diner;

use App\Exceptions\TableOccupiedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diner\StoreDinerSessionRequest;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;

class DinerSessionController extends Controller
{
    public function store(StoreDinerSessionRequest $request, string $code): JsonResponse
    {
        $table = RestaurantTable::where('code', $code)->firstOrFail();

        try {
            $session = $table->diningSessions()->create([
                'guests' => $request->validated('guests'),
                'opened_at' => now(),
            ]);
        } catch (TableOccupiedException) {
            $existing = $table->diningSessions()
                ->where('status', '!=', 'closed')
                ->latest('opened_at')
                ->first();

            return response()->json([
                'message' => 'This table already has an active dining session.',
                'session' => $existing,
            ], 409);
        }

        return response()->json(['session' => $session], 201);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add the import and route. Add `use App\Http\Controllers\Diner\DinerSessionController;` near the top (with the other `use` statements), then add this route directly above the existing `Route::get('/table/{code}', ...)`:

```php
Route::post('/table/{code}/session', [DinerSessionController::class, 'store'])->name('diner.session.store');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=DinerSessionControllerTest`
Expected: PASS (all 4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Diner/StoreDinerSessionRequest.php app/Http/Controllers/Diner/DinerSessionController.php routes/web.php tests/Feature/Diner/DinerSessionControllerTest.php
git commit -m "Add endpoint to open a dining session from the diner flow"
```

---

## Task 4: Place an order from the diner flow

**Files:**
- Create: `app/Http/Requests/Diner/StoreDinerOrderRequest.php`
- Create: `app/Http/Controllers/Diner/DinerOrderController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Diner/DinerOrderControllerTest.php`

**Interfaces:**
- Consumes: `RestaurantTable` (existing), `DiningSession::placeOrder()` (Task 2).
- Produces: `POST /table/{code}/orders` route, named `diner.orders.store`. JSON `201` with `{"order": {...}}` on success; JSON `409` with `{"message": "..."}` when no active session exists; JSON `422` on validation failure (unknown dish/allergen IDs, unavailable dish).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Diner/DinerOrderControllerTest.php`:

```php
<?php

namespace Tests\Feature\Diner;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Dish;
use App\Models\RestaurantTable;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinerOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeDish(array $overrides = []): Dish
    {
        $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);

        return Dish::create(array_merge([
            'name' => 'Grilled Chicken',
            'category_id' => $category->id,
            'station_id' => $station->id,
            'price' => 12.50,
            'is_available' => true,
        ], $overrides));
    }

    private function makeActiveSession(): DiningSession
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        return DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 3,
            'opened_at' => now(),
        ]);
    }

    public function test_places_an_order_against_the_active_session(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish();
        $allergen = Allergen::create(['code' => 'nuts', 'label' => 'Contains nuts']);

        $response = $this->postJson('/table/T1/orders', [
            'items' => [
                ['dish_id' => $dish->id, 'qty' => 2, 'allergen_ids' => [$allergen->id]],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('order_items', ['name' => 'Grilled Chicken', 'qty' => 2]);
        $this->assertDatabaseHas('order_item_allergens', ['label' => 'Contains nuts']);
    }

    public function test_round_increments_across_successive_submissions(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish();

        $this->postJson('/table/T1/orders', ['items' => [['dish_id' => $dish->id, 'qty' => 1]]]);
        $this->postJson('/table/T1/orders', ['items' => [['dish_id' => $dish->id, 'qty' => 1]]]);

        $this->assertDatabaseHas('orders', ['round' => 1]);
        $this->assertDatabaseHas('orders', ['round' => 2]);
    }

    public function test_returns_409_when_there_is_no_active_session(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $dish = $this->makeDish();

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1]],
        ]);

        $response->assertStatus(409);
    }

    public function test_returns_404_for_an_unknown_table_code(): void
    {
        $dish = $this->makeDish();

        $response = $this->postJson('/table/UNKNOWN/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1]],
        ]);

        $response->assertNotFound();
    }

    public function test_rejects_an_unavailable_dish(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish(['is_available' => false]);

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.dish_id']);
    }

    public function test_rejects_a_nonexistent_dish_id(): void
    {
        $this->makeActiveSession();

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => 999999, 'qty' => 1]],
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_a_nonexistent_allergen_id(): void
    {
        $this->makeActiveSession();
        $dish = $this->makeDish();

        $response = $this->postJson('/table/T1/orders', [
            'items' => [['dish_id' => $dish->id, 'qty' => 1, 'allergen_ids' => [999999]]],
        ]);

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DinerOrderControllerTest`
Expected: FAIL — `404 Not Found` (route doesn't exist) on all tests.

- [ ] **Step 3: Create the form request**

Create `app/Http/Requests/Diner/StoreDinerOrderRequest.php`:

```php
<?php

namespace App\Http\Requests\Diner;

use App\Models\Dish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDinerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.dish_id' => ['required', 'integer', 'exists:dishes,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.allergen_ids' => ['nullable', 'array'],
            'items.*.allergen_ids.*' => ['integer', 'exists:allergens,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);
            $dishIds = collect($items)->pluck('dish_id')->filter()->unique();

            if ($dishIds->isEmpty()) {
                return;
            }

            $unavailable = Dish::whereIn('id', $dishIds)
                ->where('is_available', false)
                ->pluck('id');

            foreach ($items as $index => $item) {
                if (isset($item['dish_id']) && $unavailable->contains($item['dish_id'])) {
                    $validator->errors()->add("items.$index.dish_id", 'This dish is not currently available.');
                }
            }
        });
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Diner/DinerOrderController.php`:

```php
<?php

namespace App\Http\Controllers\Diner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diner\StoreDinerOrderRequest;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;

class DinerOrderController extends Controller
{
    public function store(StoreDinerOrderRequest $request, string $code): JsonResponse
    {
        $table = RestaurantTable::where('code', $code)->firstOrFail();

        $session = $table->diningSessions()
            ->where('status', '!=', 'closed')
            ->latest('opened_at')
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'This table has no active dining session.',
            ], 409);
        }

        $order = $session->placeOrder($request->validated('items'));

        return response()->json(['order' => $order], 201);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add `use App\Http\Controllers\Diner\DinerOrderController;` next to the other `Diner` import, then add this route below the session route:

```php
Route::post('/table/{code}/orders', [DinerOrderController::class, 'store'])->name('diner.orders.store');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=DinerOrderControllerTest`
Expected: PASS (all 7 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Diner/StoreDinerOrderRequest.php app/Http/Controllers/Diner/DinerOrderController.php routes/web.php tests/Feature/Diner/DinerOrderControllerTest.php
git commit -m "Add endpoint to place diner orders against the active session"
```

---

## Task 5: Replace the table page closure with a controller that loads menu/session data

**Files:**
- Create: `app/Http/Controllers/Diner/DinerTableController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/DinerTableRouteTest.php`

**Interfaces:**
- Consumes: `RestaurantTable`, `Category` (with `dishes`), `Allergen` (all existing).
- Produces: `GET /table/{code}` (existing route, now backed by a controller) renders `Diner/Table` with props `code`, `table`, `session` (nullable, eager-loaded `orders.orderItems.orderItemAllergens`), `menu` (categories with available dishes + tags), `allergens`.

- [ ] **Step 1: Update the existing route test to reflect 404 behavior**

Replace the contents of `tests/Feature/DinerTableRouteTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinerTableRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_table_page_without_authentication(): void
    {
        RestaurantTable::create(['code' => 'T1', 'seats' => 4]);

        $response = $this->get('/table/T1');

        $response->assertOk();
    }

    public function test_returns_404_for_an_unknown_table_code(): void
    {
        $response = $this->get('/table/UNKNOWN');

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DinerTableRouteTest`
Expected: `test_returns_404_for_an_unknown_table_code` FAILs (current closure always returns 200 regardless of whether the table exists).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Diner/DinerTableController.php`:

```php
<?php

namespace App\Http\Controllers\Diner;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Models\Category;
use App\Models\RestaurantTable;
use Inertia\Inertia;
use Inertia\Response;

class DinerTableController extends Controller
{
    public function show(string $code): Response
    {
        $table = RestaurantTable::where('code', $code)->firstOrFail();

        $session = $table->diningSessions()
            ->where('status', '!=', 'closed')
            ->latest('opened_at')
            ->with('orders.orderItems.orderItemAllergens')
            ->first();

        $menu = Category::query()
            ->orderBy('sort_order')
            ->with(['dishes' => fn ($query) => $query->where('is_available', true)->with('tags')])
            ->get();

        return Inertia::render('Diner/Table', [
            'code' => $code,
            'table' => $table,
            'session' => $session,
            'menu' => $menu,
            'allergens' => Allergen::all(),
        ]);
    }
}
```

- [ ] **Step 4: Replace the closure route**

In `routes/web.php`, add `use App\Http\Controllers\Diner\DinerTableController;` to the imports. Replace:

```php
Route::get('/table/{code}', function (string $code) {
    return Inertia::render('Diner/Table', [
        'code' => $code,
    ]);
})->name('diner.table');
```

with:

```php
Route::get('/table/{code}', [DinerTableController::class, 'show'])->name('diner.table');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=DinerTableRouteTest`
Expected: PASS (both tests).

- [ ] **Step 6: Run the full test suite**

Run: `composer test`
Expected: PASS — all existing tests plus the new `Diner/` Feature tests, `DishTest`, and `DiningSessionTest` additions all green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Diner/DinerTableController.php routes/web.php tests/Feature/DinerTableRouteTest.php
git commit -m "Load menu and active session data for the diner table page"
```
