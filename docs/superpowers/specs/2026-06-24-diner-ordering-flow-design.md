# Diner Ordering Flow — Backend Design

## Scope

Backend only (routes, controllers, form requests, model logic, Feature tests) for the public,
unauthenticated diner flow at `/table/{code}`. The diner can: view the menu for a table, open a
dining session if none is active, and place orders (rounds) with optional per-item allergen
flags.

**Out of scope** (deferred to future design cycles):
- React UI for `Diner/Table.jsx` (stays a placeholder for now).
- `Payment` calculation (buffet/extras/waste totals). This design only ensures the data needed
  for that calculation (snapshotted `unit_price`/`name` on `OrderItem`, `is_extra` on `Dish`) is
  recorded faithfully.
- Floor/Kitchen dashboards, service requests, signed/secured table URLs.

## Pricing model (for context, not implemented here)

- `buffet_total = Setting.buffet_price × DiningSession.guests` — flat per-person rate, fixed at
  session open, independent of how many buffet dishes are ordered.
- `extras_total` = sum of `Dish.price × qty` for any `OrderItem` where the dish is `is_extra =
  true`, **plus** the excess quantity of any buffet dish (`is_extra = false`) ordered beyond its
  `per_round_limit` in a given round, charged at `Dish.price`.
- Ordering beyond `per_round_limit` is never blocked — it's allowed and priced as above. No
  validation enforces the limit at order-placement time; the limit is purely a pricing-time
  concern handled when `Payment` is computed (future design).

## 1. Schema change

Alter the existing (not-yet-shipped) migration `2026_06_24_091356_create_dishes_table.php` to add:

```php
$table->boolean('is_extra')->default(false);
```

Update `App\Models\Dish`:
- Add `is_extra` to the `#[Fillable]` list.
- Add `'is_extra' => 'boolean'` to `$casts`.

## 2. Routes & controllers

All routes are public (no `auth` middleware), registered in `routes/web.php`, replacing the
current closure-based `/table/{code}` route.

| Method | Route | Controller@method | Purpose |
|---|---|---|---|
| GET | `/table/{code}` | `Diner\DinerTableController@show` | Look up `RestaurantTable` by `code` (404 if missing). Render `Diner/Table` Inertia page with props: table, active session (nullable, with its orders), available menu (categories → available dishes, with tags), allergen catalog. |
| POST | `/table/{code}/session` | `Diner\DinerSessionController@store` | Open a `DiningSession` for the table. Requires `guests`. If a session is already active, catch `TableOccupiedException` and respond 409 with the existing session instead of duplicating. |
| POST | `/table/{code}/orders` | `Diner\DinerOrderController@store` | Place a new round of items against the table's active session. 409 if no active session exists. |

Controllers resolve the `RestaurantTable` by `code` (route param), then resolve its active session
(`status != 'closed'`) where needed.

## 3. Validation

**`App\Http\Requests\Diner\StoreDinerSessionRequest`**
- `guests`: `required|integer|min:1`

**`App\Http\Requests\Diner\StoreDinerOrderRequest`**
- `items`: `required|array|min:1`
- `items.*.dish_id`: `required|exists:dishes,id`
- `items.*.qty`: `required|integer|min:1`
- `items.*.note`: `nullable|string|max:255`
- `items.*.allergen_ids`: `nullable|array`
- `items.*.allergen_ids.*`: `exists:allergens,id`
- Custom rule: every referenced `dish_id` must have `is_available = true`, else fail validation
  with a clear message ("This dish is not currently available.").

**Error handling**
- Table not found by `code` → standard Laravel 404 (`abort(404)` / `firstOrFail()`).
- `TableOccupiedException` on session open → caught in `DinerSessionController`, respond 409 with
  the existing active session payload. No changes needed to `bootstrap/app.php`.
- No active session on order placement → `DinerOrderController` responds 409 manually (checked
  before invoking the placement logic).

## 4. Order placement logic

Add `App\Models\DiningSession::placeOrder(array $items): Order`:

1. Compute the next `round`/`number` as `(current max for this session) + 1` — they're always
   equal since one cart submission = one round = one `Order`.
2. Create the `Order` (status defaults to `new` per existing model attribute).
3. For each item:
   - Snapshot `name` and `unit_price` from the `Dish` onto the `OrderItem` (status `firing`,
     matching the existing snapshot pattern used for `OrderItemAllergen.label`).
   - Create `OrderItemAllergen` rows for any `allergen_ids`, snapshotting `label` from
     `Allergen` (same point-in-time pattern already used elsewhere in the schema).
4. Wrap the whole operation in `DB::transaction()`.

Controllers stay thin: validate input, resolve the active session, call
`$session->placeOrder($validated['items'])`, and return an Inertia response (redirect back with
flash data — no JSON API layer exists in this app).

## 5. Tests

New `tests/Feature/Diner/` directory:

- **`DinerTableControllerTest`**
  - 404 when `code` doesn't match any `RestaurantTable`.
  - Props include `null` session when none active.
  - Props include the active session (with its orders) when one exists.
- **`DinerSessionControllerTest`**
  - Opens a new session successfully when none active.
  - Returns 409 and does not duplicate when a session is already active.
- **`DinerOrderControllerTest`**
  - Creates an `Order` with `round`/`number` incrementing correctly across successive
    submissions.
  - Snapshots `name`, `unit_price` on `OrderItem`, and `label` on `OrderItemAllergen` correctly.
  - Rejects (422) when a referenced dish is unavailable.
  - Rejects (409) when there's no active session for the table.
  - Rejects (422) when `dish_id` or `allergen_ids.*` don't exist.
