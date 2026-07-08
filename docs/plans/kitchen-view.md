# Kitchen View Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a real-time kitchen display at `/kitchen` where staff can see active order items grouped by table/round, filter by station, and advance each item through `firing` → `prep` → `ready` → `served`.

**Architecture:** Laravel Reverb handles WebSocket broadcasting; two events (`OrderPlaced`, `OrderItemStatusUpdated`) are emitted on private channel `kitchen`. The frontend (React + Laravel Echo) holds a flat array of active items in local state and applies event patches without full page reloads. Items in status `served` disappear from view.

**Tech Stack:** Laravel 13, Reverb, Laravel Echo, pusher-js, React 18, Inertia.js, SQLite (dev + test), Tailwind CSS.

## Global Constraints

- PHP 8.3+, Laravel 13, React 18, Inertia.js — no version downgrades.
- `BROADCAST_CONNECTION=null` already set in `phpunit.xml` — existing tests are unaffected by broadcast dispatches.
- Run tests with `composer test` (clears config cache first). Single test: `php artisan test --filter=TestName`.
- SQLite in-memory for tests — no external services needed.
- Role middleware: `role:kitchen,admin` (comma = OR semantics). Never `role:kitchen` alone for kitchen routes — admin must also have access.
- TDD: write the failing test before writing implementation code.
- Frequent commits after each task.

---

## File Map

### Created
- `app/Events/OrderPlaced.php`
- `app/Events/OrderItemStatusUpdated.php`
- `app/Http/Controllers/Kitchen/KitchenIndexController.php`
- `app/Http/Controllers/Kitchen/KitchenOrderItemController.php`
- `resources/js/echo.js`
- `resources/js/Pages/Kitchen/Index.jsx`
- `routes/channels.php`
- `tests/Feature/Kitchen/KitchenIndexTest.php`
- `tests/Feature/Kitchen/KitchenOrderItemControllerTest.php`

### Modified
- `database/migrations/2026_06_24_123808_create_order_items_table.php` — add `prep` to enum
- `app/Models/Order.php` — update `syncStatusFromItems()` to handle `prep` item status
- `app/Models/DiningSession.php` — dispatch `OrderPlaced` after `placeOrder()` transaction
- `resources/js/app.jsx` — import `./echo`
- `routes/web.php` — replace kitchen closure with proper controller routes

---

## Task 1: Add `prep` item status and update `syncStatusFromItems`

**Files:**
- Modify: `database/migrations/2026_06_24_123808_create_order_items_table.php`
- Modify: `app/Models/Order.php`
- Test: `tests/Feature/OrderTest.php`

**Interfaces:**
- Produces: `OrderItem.status` enum accepts `['firing', 'prep', 'ready', 'served']`
- Produces: `Order::syncStatusFromItems()` maps `prep` item → `prep` order status

---

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/OrderTest.php` inside the class (before the closing `}`):

```php
public function test_sync_status_from_items_is_prep_when_any_item_is_in_prep(): void
{
    $session = $this->makeSession();
    $order = Order::create([
        'dining_session_id' => $session->id,
        'number' => 1,
        'round' => 1,
        'placed_at' => now(),
    ]);
    $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#fff']);

    OrderItem::create(['order_id' => $order->id, 'name' => 'Chicken', 'station_id' => $station->id, 'qty' => 1, 'status' => 'ready']);
    OrderItem::create(['order_id' => $order->id, 'name' => 'Steak', 'station_id' => $station->id, 'qty' => 1, 'status' => 'prep']);

    $order->syncStatusFromItems();

    $this->assertEquals('prep', $order->fresh()->status);
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --filter=test_sync_status_from_items_is_prep_when_any_item_is_in_prep
```

Expected: FAIL — SQLite may reject `prep` as an unknown enum value, or the assertion fails because `syncStatusFromItems` returns `ready` instead of `prep`.

- [ ] **Step 3: Update the migration to add `prep`**

In `database/migrations/2026_06_24_123808_create_order_items_table.php`, change:

```php
// Before
$table->enum('status', ['firing', 'ready', 'served'])->default('firing');

// After
$table->enum('status', ['firing', 'prep', 'ready', 'served'])->default('firing');
```

- [ ] **Step 4: Run `migrate:fresh --seed` to rebuild the DB**

```bash
php artisan migrate:fresh --seed
```

Expected: Ran X migrations, seeded successfully.

- [ ] **Step 5: Update `syncStatusFromItems()` in `app/Models/Order.php`**

Replace the `match` block:

```php
// Before
$status = match (true) {
    $statuses->isEmpty() => 'new',
    $statuses->contains('firing') => 'prep',
    $statuses->every(fn ($itemStatus) => $itemStatus === 'served') => 'served',
    default => 'ready',
};

// After
$status = match (true) {
    $statuses->isEmpty() => 'new',
    $statuses->contains('firing') || $statuses->contains('prep') => 'prep',
    $statuses->every(fn ($itemStatus) => $itemStatus === 'served') => 'served',
    default => 'ready',
};
```

- [ ] **Step 6: Run all tests to confirm everything passes**

```bash
composer test
```

Expected: All tests pass, including the new one and all existing `syncStatusFromItems` tests.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_24_123808_create_order_items_table.php app/Models/Order.php tests/Feature/OrderTest.php
git commit -m "feat: add prep status to order_items and update syncStatusFromItems"
```

---

## Task 2: Install Laravel Reverb and configure Echo on the frontend

**Files:**
- Create: `resources/js/echo.js`
- Modify: `resources/js/app.jsx`

**Interfaces:**
- Produces: `window.Echo` available globally in the browser for private channel subscriptions

---

- [ ] **Step 1: Install Reverb via Composer**

```bash
composer require laravel/reverb
```

Expected: Package installed, `composer.lock` updated.

- [ ] **Step 2: Run the Reverb install command**

```bash
php artisan reverb:install
```

Expected: Publishes `config/reverb.php`, adds `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` to `.env`, adds the corresponding `VITE_REVERB_*` vars to `.env`.

If `.env` already has these keys, the command skips them — that is fine.

- [ ] **Step 3: Install frontend packages**

```bash
npm install laravel-echo pusher-js
```

Expected: Both packages added to `node_modules` and `package.json`.

- [ ] **Step 4: Create `resources/js/echo.js`**

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

- [ ] **Step 5: Import `echo.js` in `resources/js/app.jsx`**

```jsx
// Before
import '../css/app.css';
import './bootstrap';

// After
import '../css/app.css';
import './bootstrap';
import './echo';
```

- [ ] **Step 6: Verify Vite builds without errors**

```bash
npm run build
```

Expected: Build completes with no errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/echo.js resources/js/app.jsx package.json package-lock.json composer.json composer.lock config/reverb.php
git commit -m "feat: install Reverb and configure Laravel Echo"
```

---

## Task 3: Broadcast events + wire into `placeOrder` + channel auth

**Files:**
- Create: `app/Events/OrderPlaced.php`
- Create: `app/Events/OrderItemStatusUpdated.php`
- Create: `routes/channels.php`
- Modify: `app/Models/DiningSession.php`

**Interfaces:**
- Consumes: `Order` with relationships `orderItems.orderItemAllergens`, `orderItems.station`, `diningSession.restaurantTable`
- Consumes: `OrderItem` with `order_id` and `status`
- Produces: `OrderPlaced` broadcast on `PrivateChannel('kitchen')` with payload `{ order: {...} }`
- Produces: `OrderItemStatusUpdated` broadcast on `PrivateChannel('kitchen')` with payload `{ order_item_id, order_id, status }`

---

- [ ] **Step 1: Write the failing test for `OrderPlaced` dispatch**

Create `tests/Feature/Kitchen/KitchenOrderItemControllerTest.php` — but first, add an event-dispatch test in the existing `tests/Feature/DiningSessionTest.php` (inside the class, before closing `}`):

```php
public function test_place_order_dispatches_order_placed_event(): void
{
    Event::fake([\App\Events\OrderPlaced::class]);

    $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
    $session = DiningSession::create([
        'restaurant_table_id' => $table->id,
        'guests' => 2,
        'opened_at' => now(),
    ]);
    $category = Category::create(['code' => 'mains', 'label' => 'Mains', 'sort_order' => 1]);
    $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
    $dish = Dish::create([
        'name' => 'Chicken',
        'category_id' => $category->id,
        'station_id' => $station->id,
        'price' => 10.00,
    ]);

    $session->placeOrder([['dish_id' => $dish->id, 'qty' => 1]]);

    Event::assertDispatched(\App\Events\OrderPlaced::class);
}
```

Also add these use statements at the top of the file if not already present:
```php
use Illuminate\Support\Facades\Event;
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --filter=test_place_order_dispatches_order_placed_event
```

Expected: FAIL — `App\Events\OrderPlaced` class does not exist.

- [ ] **Step 3: Create `app/Events/OrderPlaced.php`**

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('kitchen')];
    }

    public function broadcastAs(): string
    {
        return 'OrderPlaced';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing(
            'orderItems.orderItemAllergens',
            'orderItems.station',
            'diningSession.restaurantTable',
        );

        return ['order' => $this->order->toArray()];
    }
}
```

- [ ] **Step 4: Create `app/Events/OrderItemStatusUpdated.php`**

```php
<?php

namespace App\Events;

use App\Models\OrderItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderItemStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly OrderItem $orderItem) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('kitchen')];
    }

    public function broadcastAs(): string
    {
        return 'OrderItemStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_item_id' => $this->orderItem->id,
            'order_id' => $this->orderItem->order_id,
            'status' => $this->orderItem->status,
        ];
    }
}
```

- [ ] **Step 5: Dispatch `OrderPlaced` from `DiningSession::placeOrder()`**

In `app/Models/DiningSession.php`, add the import at the top:

```php
use App\Events\OrderPlaced;
```

Then update `placeOrder()` to dispatch after the transaction returns:

```php
public function placeOrder(array $items): Order
{
    $order = DB::transaction(function () use ($items) {
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

    OrderPlaced::dispatch($order);

    return $order;
}
```

- [ ] **Step 6: Create `routes/channels.php`**

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('kitchen', function ($user) {
    return in_array($user->role, ['kitchen', 'admin']);
});
```

- [ ] **Step 7: Register `channels.php` in `bootstrap/app.php`**

Add the `channels` key to `withRouting()`:

```php
// Before
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)

// After
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    health: '/up',
)
```

Without this, Laravel never loads the channel authorization callback and private channel subscriptions silently fail.

- [ ] **Step 9: Run the event dispatch test to confirm it passes**

```bash
php artisan test --filter=test_place_order_dispatches_order_placed_event
```

Expected: PASS.

- [ ] **Step 10: Run all tests to confirm nothing broke**

```bash
composer test
```

Expected: All tests pass.

- [ ] **Step 11: Commit**

```bash
git add app/Events/OrderPlaced.php app/Events/OrderItemStatusUpdated.php app/Models/DiningSession.php routes/channels.php bootstrap/app.php tests/Feature/DiningSessionTest.php
git commit -m "feat: add OrderPlaced and OrderItemStatusUpdated broadcast events"
```

---

## Task 4: Kitchen controllers, routes, and tests

**Files:**
- Create: `app/Http/Controllers/Kitchen/KitchenIndexController.php`
- Create: `app/Http/Controllers/Kitchen/KitchenOrderItemController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Kitchen/KitchenIndexTest.php`
- Create: `tests/Feature/Kitchen/KitchenOrderItemControllerTest.php`

**Interfaces:**
- Consumes: `OrderPlaced` and `OrderItemStatusUpdated` events (Task 3)
- Consumes: `OrderItem` with `firing`/`prep`/`ready` statuses (Task 1)
- Produces: `GET /kitchen` — Inertia page with `initialItems` and `stations` props
- Produces: `PATCH /kitchen/order-items/{orderItem}` — advances item status, returns JSON `{ item }`

---

- [ ] **Step 1: Write failing tests for the index controller**

Create `tests/Feature/Kitchen/KitchenIndexTest.php`:

```php
<?php

namespace Tests\Feature\Kitchen;

use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenIndexTest extends TestCase
{
    use RefreshDatabase;

    private function makeKitchenUser(): User
    {
        return User::create([
            'name' => 'Chef',
            'email' => 'chef@example.com',
            'password' => bcrypt('password'),
            'role' => 'kitchen',
        ]);
    }

    public function test_kitchen_page_loads_for_kitchen_role(): void
    {
        $response = $this->actingAs($this->makeKitchenUser())->get('/kitchen');

        $response->assertOk();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/kitchen');

        $response->assertRedirect('/login');
    }

    public function test_non_kitchen_role_receives_403(): void
    {
        $floor = User::create([
            'name' => 'Floor',
            'email' => 'floor@example.com',
            'password' => bcrypt('password'),
            'role' => 'floor',
        ]);

        $response = $this->actingAs($floor)->get('/kitchen');

        $response->assertForbidden();
    }

    public function test_only_active_items_are_included_in_initial_props(): void
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'name' => 'A', 'station_id' => $station->id, 'qty' => 1, 'status' => 'firing']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'B', 'station_id' => $station->id, 'qty' => 1, 'status' => 'prep']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'C', 'station_id' => $station->id, 'qty' => 1, 'status' => 'ready']);
        OrderItem::create(['order_id' => $order->id, 'name' => 'D', 'station_id' => $station->id, 'qty' => 1, 'status' => 'served']);

        $response = $this->actingAs($this->makeKitchenUser())->get('/kitchen');

        $items = $response->viewData('page')['props']['initialItems'];
        $statuses = collect($items)->pluck('status');

        $this->assertContains('firing', $statuses);
        $this->assertContains('prep', $statuses);
        $this->assertContains('ready', $statuses);
        $this->assertNotContains('served', $statuses);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/Kitchen/KitchenIndexTest.php
```

Expected: FAIL — route `/kitchen` renders `Dashboard` placeholder, not the Kitchen index. Tests asserting 403 for floor role may fail if the middleware isn't updated yet.

- [ ] **Step 3: Create `app/Http/Controllers/Kitchen/KitchenIndexController.php`**

```php
<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Station;
use Inertia\Inertia;
use Inertia\Response;

class KitchenIndexController extends Controller
{
    public function index(): Response
    {
        $items = OrderItem::whereIn('status', ['firing', 'prep', 'ready'])
            ->with(['order.diningSession.restaurantTable', 'orderItemAllergens', 'station'])
            ->get();

        return Inertia::render('Kitchen/Index', [
            'initialItems' => $items,
            'stations' => Station::all(),
        ]);
    }
}
```

- [ ] **Step 4: Update the kitchen route in `routes/web.php`**

Replace:
```php
Route::middleware(['auth', 'role:kitchen'])->get('/kitchen', function () {
    return Inertia::render('Dashboard');
})->name('kitchen.dashboard');
```

With:
```php
use App\Http\Controllers\Kitchen\KitchenIndexController;
use App\Http\Controllers\Kitchen\KitchenOrderItemController;

Route::middleware(['auth', 'role:kitchen,admin'])->prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/', [KitchenIndexController::class, 'index'])->name('index');
    Route::patch('/order-items/{orderItem}', [KitchenOrderItemController::class, 'advance'])->name('order-items.advance');
});
```

- [ ] **Step 5: Create a placeholder `Kitchen/Index.jsx` so the index test passes**

Create `resources/js/Pages/Kitchen/Index.jsx`:

```jsx
import { Head } from '@inertiajs/react';

export default function Kitchen({ initialItems, stations }) {
    return (
        <>
            <Head title="Cocina" />
            <div className="p-6">
                <h1 className="text-xl font-bold">Pantalla de cocina</h1>
                <p>{initialItems.length} ítems activos</p>
            </div>
        </>
    );
}
```

- [ ] **Step 6: Run index tests to confirm they pass**

```bash
php artisan test tests/Feature/Kitchen/KitchenIndexTest.php
```

Expected: All 4 tests pass.

- [ ] **Step 7: Write failing tests for the advance controller**

Create `tests/Feature/Kitchen/KitchenOrderItemControllerTest.php`:

```php
<?php

namespace Tests\Feature\Kitchen;

use App\Events\OrderItemStatusUpdated;
use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class KitchenOrderItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeKitchenUser(): User
    {
        return User::create([
            'name' => 'Chef',
            'email' => 'chef@example.com',
            'password' => bcrypt('password'),
            'role' => 'kitchen',
        ]);
    }

    private function makeOrderItem(array $overrides = []): OrderItem
    {
        $table = RestaurantTable::create(['code' => 'T1', 'seats' => 4]);
        $session = DiningSession::create([
            'restaurant_table_id' => $table->id,
            'guests' => 2,
            'opened_at' => now(),
        ]);
        $station = Station::create(['code' => 'grill', 'label' => 'Grill', 'short_label' => 'GR', 'color' => '#ff0000']);
        $order = Order::create([
            'dining_session_id' => $session->id,
            'number' => 1,
            'round' => 1,
            'placed_at' => now(),
        ]);

        return OrderItem::create(array_merge([
            'order_id' => $order->id,
            'name' => 'Grilled Chicken',
            'station_id' => $station->id,
            'qty' => 1,
            'status' => 'firing',
        ], $overrides));
    }

    public function test_advances_firing_item_to_prep(): void
    {
        $item = $this->makeOrderItem(['status' => 'firing']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertOk();
        $this->assertSame('prep', $item->fresh()->status);
    }

    public function test_advances_prep_item_to_ready(): void
    {
        $item = $this->makeOrderItem(['status' => 'prep']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertOk();
        $this->assertSame('ready', $item->fresh()->status);
    }

    public function test_advances_ready_item_to_served(): void
    {
        $item = $this->makeOrderItem(['status' => 'ready']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertOk();
        $this->assertSame('served', $item->fresh()->status);
    }

    public function test_syncs_order_status_after_advancing(): void
    {
        $item = $this->makeOrderItem(['status' => 'firing']);

        $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $this->assertSame('prep', $item->order->fresh()->status);
    }

    public function test_returns_422_for_already_served_item(): void
    {
        $item = $this->makeOrderItem(['status' => 'served']);

        $response = $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertStatus(422);
        $this->assertSame('served', $item->fresh()->status);
    }

    public function test_non_kitchen_role_receives_403(): void
    {
        $item = $this->makeOrderItem();
        $floor = User::create([
            'name' => 'Floor',
            'email' => 'floor@example.com',
            'password' => bcrypt('password'),
            'role' => 'floor',
        ]);

        $response = $this->actingAs($floor)
            ->patchJson("/kitchen/order-items/{$item->id}");

        $response->assertForbidden();
    }

    public function test_dispatches_order_item_status_updated_event(): void
    {
        Event::fake([OrderItemStatusUpdated::class]);
        $item = $this->makeOrderItem(['status' => 'firing']);

        $this->actingAs($this->makeKitchenUser())
            ->patchJson("/kitchen/order-items/{$item->id}");

        Event::assertDispatched(OrderItemStatusUpdated::class, function ($event) use ($item) {
            return $event->orderItem->id === $item->id
                && $event->orderItem->status === 'prep';
        });
    }
}
```

- [ ] **Step 8: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/Kitchen/KitchenOrderItemControllerTest.php
```

Expected: FAIL — `KitchenOrderItemController` does not exist yet.

- [ ] **Step 9: Create `app/Http/Controllers/Kitchen/KitchenOrderItemController.php`**

```php
<?php

namespace App\Http\Controllers\Kitchen;

use App\Events\OrderItemStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class KitchenOrderItemController extends Controller
{
    public function advance(OrderItem $orderItem): JsonResponse
    {
        if ($orderItem->status === 'served') {
            return response()->json(['message' => 'This item is already served.'], 422);
        }

        $next = match ($orderItem->status) {
            'firing' => 'prep',
            'prep' => 'ready',
            'ready' => 'served',
        };

        $orderItem->update(['status' => $next]);
        $orderItem->order->syncStatusFromItems();

        OrderItemStatusUpdated::dispatch($orderItem);

        return response()->json(['item' => $orderItem]);
    }
}
```

- [ ] **Step 10: Run all tests to confirm they pass**

```bash
composer test
```

Expected: All tests pass.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Kitchen/ routes/web.php resources/js/Pages/Kitchen/Index.jsx tests/Feature/Kitchen/
git commit -m "feat: add kitchen controllers, routes, and tests"
```

---

## Task 5: Kitchen frontend — full `Kitchen/Index.jsx`

**Files:**
- Modify: `resources/js/Pages/Kitchen/Index.jsx` (replace the placeholder from Task 4)

**Interfaces:**
- Consumes: `initialItems` — array of `OrderItem` with nested `order.dining_session.restaurant_table`, `order_item_allergens`, `station`
- Consumes: `stations` — array of `Station` with `id`, `label`, `color`
- Consumes: WebSocket events `.OrderPlaced` and `.OrderItemStatusUpdated` on channel `kitchen`
- Consumes: `PATCH /kitchen/order-items/{id}` with CSRF token

---

- [ ] **Step 1: Replace `resources/js/Pages/Kitchen/Index.jsx` with the full implementation**

```jsx
import { Head } from '@inertiajs/react';
import { useState, useEffect, useMemo, useCallback } from 'react';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function playBeep() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);
    } catch (_) {}
}

function elapsed(placedAt) {
    const diff = Math.floor((Date.now() - new Date(placedAt)) / 1000);
    if (diff < 60) return `${diff}s`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    return `${Math.floor(diff / 3600)}h`;
}

const OVERDUE_MS = 10 * 60 * 1000;

function StationFilter({ stations, active, onChange }) {
    return (
        <div className="flex flex-wrap gap-2 mb-6">
            <button
                onClick={() => onChange(null)}
                className={`px-3 py-1 rounded text-sm font-medium ${active === null ? 'bg-gray-800 text-white' : 'bg-gray-100 hover:bg-gray-200'}`}
            >
                Todas
            </button>
            {stations.map(station => (
                <button
                    key={station.id}
                    onClick={() => onChange(station.id)}
                    className="px-3 py-1 rounded text-sm font-medium"
                    style={{
                        backgroundColor: active === station.id ? station.color : undefined,
                        color: active === station.id ? '#fff' : undefined,
                    }}
                >
                    {active !== station.id && <span className="inline-block w-2 h-2 rounded-full mr-1" style={{ backgroundColor: station.color }} />}
                    {station.label}
                </button>
            ))}
        </div>
    );
}

function ItemRow({ item, onAdvance }) {
    const buttonLabel = { firing: '▶ En preparación', prep: '▶ Listo', ready: '✓ Servido' }[item.status];
    const allergens = item.order_item_allergens?.map(a => a.label).join(', ');

    return (
        <div className="flex items-start justify-between gap-2 py-2 border-b last:border-0">
            <div className="text-sm min-w-0">
                <span className="font-medium">{item.qty}× {item.name}</span>
                {item.note && <span className="ml-1 text-gray-500 italic">— {item.note}</span>}
                {allergens && <div className="text-xs text-orange-600 mt-0.5">{allergens}</div>}
            </div>
            <button
                onClick={() => onAdvance(item.id, item.status)}
                className="shrink-0 text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 whitespace-nowrap"
            >
                {buttonLabel}
            </button>
        </div>
    );
}

function RoundCard({ round, statusFilter, onAdvance, isNew, isOverdue }) {
    const visibleItems = round.items.filter(i => i.status === statusFilter);
    if (visibleItems.length === 0) return null;

    const borderClass = isNew
        ? 'border-yellow-400 bg-yellow-50'
        : isOverdue
            ? 'border-red-500'
            : 'border-gray-200';

    return (
        <div className={`rounded border p-3 ${borderClass}`}>
            <div className="flex justify-between items-baseline mb-2">
                <span className="font-bold text-sm">Mesa {round.tableCode} · R{round.round}</span>
                <span className="text-xs text-gray-400">{round.elapsed}</span>
            </div>
            {visibleItems.map(item => (
                <ItemRow key={item.id} item={item} onAdvance={onAdvance} />
            ))}
        </div>
    );
}

function Column({ title, rounds, statusFilter, onAdvance, newOrderIds, overdueOrderIds }) {
    const visibleRounds = rounds.filter(r => r.items.some(i => i.status === statusFilter));

    return (
        <div>
            <h2 className="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">
                {title}
                <span className="ml-2 text-gray-400 font-normal normal-case">({visibleRounds.length})</span>
            </h2>
            <div className="space-y-3">
                {visibleRounds.map(round => (
                    <RoundCard
                        key={round.orderId}
                        round={round}
                        statusFilter={statusFilter}
                        onAdvance={onAdvance}
                        isNew={newOrderIds.has(round.orderId)}
                        isOverdue={overdueOrderIds.has(round.orderId)}
                    />
                ))}
                {visibleRounds.length === 0 && (
                    <p className="text-sm text-gray-400 italic">Sin ítems</p>
                )}
            </div>
        </div>
    );
}

export default function Kitchen({ initialItems, stations }) {
    const [items, setItems] = useState(initialItems);
    const [stationFilter, setStationFilter] = useState(null);
    const [newOrderIds, setNewOrderIds] = useState(new Set());
    const [tick, setTick] = useState(0);

    useEffect(() => {
        const id = setInterval(() => setTick(t => t + 1), 30_000);
        return () => clearInterval(id);
    }, []);

    const handleOrderPlaced = useCallback((e) => {
        playBeep();
        const order = e.order;
        const incoming = (order.order_items ?? []).map(item => ({
            ...item,
            order: {
                id: order.id,
                round: order.round,
                placed_at: order.placed_at,
                dining_session: order.dining_session,
            },
        }));
        setItems(prev => [...prev, ...incoming]);
        setNewOrderIds(prev => new Set([...prev, order.id]));
        setTimeout(() => {
            setNewOrderIds(prev => { const n = new Set(prev); n.delete(order.id); return n; });
        }, 3000);
    }, []);

    const handleItemUpdated = useCallback((e) => {
        setItems(prev =>
            prev
                .map(item => item.id === e.order_item_id ? { ...item, status: e.status } : item)
                .filter(item => item.status !== 'served')
        );
    }, []);

    useEffect(() => {
        if (!window.Echo) return;
        window.Echo.private('kitchen')
            .listen('.OrderPlaced', handleOrderPlaced)
            .listen('.OrderItemStatusUpdated', handleItemUpdated);
        return () => window.Echo.leave('kitchen');
    }, [handleOrderPlaced, handleItemUpdated]);

    const advanceItem = useCallback(async (itemId, currentStatus) => {
        const next = { firing: 'prep', prep: 'ready', ready: 'served' }[currentStatus];
        setItems(prev =>
            prev
                .map(i => i.id === itemId ? { ...i, status: next } : i)
                .filter(i => i.status !== 'served')
        );
        try {
            const res = await fetch(`/kitchen/order-items/${itemId}`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            });
            if (!res.ok) {
                setItems(prev => prev.map(i => i.id === itemId ? { ...i, status: currentStatus } : i));
            }
        } catch {
            setItems(prev => prev.map(i => i.id === itemId ? { ...i, status: currentStatus } : i));
        }
    }, []);

    const rounds = useMemo(() => {
        const map = {};
        items.forEach(item => {
            const oid = item.order_id;
            if (!map[oid]) {
                map[oid] = {
                    orderId: oid,
                    round: item.order.round,
                    tableCode: item.order.dining_session.restaurant_table.code,
                    placedAt: item.order.placed_at,
                    items: [],
                };
            }
            map[oid].items.push(item);
        });
        return Object.values(map)
            .sort((a, b) => new Date(a.placedAt) - new Date(b.placedAt))
            .map(r => ({ ...r, elapsed: elapsed(r.placedAt) }));
    }, [items, tick]);

    const filtered = useMemo(() =>
        stationFilter
            ? rounds
                .map(r => ({ ...r, items: r.items.filter(i => i.station_id === stationFilter) }))
                .filter(r => r.items.length > 0)
            : rounds,
        [rounds, stationFilter]
    );

    const overdueOrderIds = useMemo(() => {
        const ids = new Set();
        rounds.forEach(round => {
            const hasFiring = round.items.some(i => i.status === 'firing');
            if (hasFiring && Date.now() - new Date(round.placedAt) > OVERDUE_MS) {
                ids.add(round.orderId);
            }
        });
        return ids;
    }, [rounds]);

    return (
        <>
            <Head title="Cocina" />
            <div className="p-6 min-h-screen bg-gray-50">
                <h1 className="text-xl font-bold mb-4">Pantalla de cocina</h1>
                <StationFilter stations={stations} active={stationFilter} onChange={setStationFilter} />
                <div className="grid grid-cols-3 gap-6">
                    <Column
                        title="Sin preparar"
                        rounds={filtered}
                        statusFilter="firing"
                        onAdvance={advanceItem}
                        newOrderIds={newOrderIds}
                        overdueOrderIds={overdueOrderIds}
                    />
                    <Column
                        title="En preparación"
                        rounds={filtered}
                        statusFilter="prep"
                        onAdvance={advanceItem}
                        newOrderIds={newOrderIds}
                        overdueOrderIds={overdueOrderIds}
                    />
                    <Column
                        title="Listos para entregar"
                        rounds={filtered}
                        statusFilter="ready"
                        onAdvance={advanceItem}
                        newOrderIds={newOrderIds}
                        overdueOrderIds={overdueOrderIds}
                    />
                </div>
            </div>
        </>
    );
}
```

- [ ] **Step 2: Run all tests to confirm nothing broke**

```bash
composer test
```

Expected: All tests pass (the frontend is not covered by PHP tests; manual verification is the next step).

- [ ] **Step 3: Start all services and verify the kitchen view works manually**

Open three terminal tabs:

```bash
# Tab 1 — Laravel server
php artisan serve

# Tab 2 — Reverb WebSocket server
php artisan reverb:start

# Tab 3 — Vite dev server
npm run dev
```

Then:
1. Open `http://localhost:8000/login` and log in as a kitchen user.
2. Navigate to `http://localhost:8000/kitchen` — should show 3 empty columns and the station filter.
3. Open a second browser tab at `http://localhost:8000/table/T1` — open a session, place an order.
4. The kitchen tab should: play a beep, show the new round card in "Sin preparar" with a yellow highlight.
5. Click "▶ En preparación" on an item — it should move to the "En preparación" column immediately (optimistic update).
6. Click "▶ Listo" — it should move to "Listos para entregar".
7. Click "✓ Servido" — the item disappears.
8. Filter by a station — only items from that station should be visible.
9. Wait 10 minutes with a `firing` item — the round card border should turn red.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Kitchen/Index.jsx
git commit -m "feat: implement real-time kitchen display with station filter and status advancement"
```
