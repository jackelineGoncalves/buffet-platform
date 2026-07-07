# Kitchen View — Design Spec

**Date:** 2026-07-06  
**Feature:** `/kitchen` — real-time order management for kitchen staff

---

## Overview

A real-time kitchen display screen where staff with `role=kitchen` can see all active order items grouped by table/round, filter by station, and advance each item through its status lifecycle (`firing` → `prep` → `ready` → `served`). New orders appear instantly via WebSockets; served items disappear from view.

---

## Architecture

### Real-time stack

- **Laravel Reverb** — WebSocket server, installed via `php artisan install:broadcasting`, runs on the same host, no external services required.
- **Laravel Echo** — frontend WebSocket client, listens for events and patches local React state.
- **Private channel `kitchen`** — authorized only for authenticated users with `role=kitchen` or `role=admin`. Comensales never have access.

### Two broadcast events

| Event | Triggered by | Payload |
|---|---|---|
| `OrderPlaced` | `DiningSession::placeOrder()` | Full round: table code, round number, all items with qty, name, note, allergens, station |
| `OrderItemStatusUpdated` | `KitchenOrderItemController@advance` | `order_item_id`, `order_id`, new `status` |

Both implement `ShouldBroadcast` and broadcast on the `kitchen` private channel.

### Data flow

```
Diner places order
  → DinerOrderController → placeOrder()
  → broadcast(OrderPlaced) → Reverb → Echo → Kitchen.jsx
  → React patches local state (adds the round card)

Kitchen staff advances an item
  → KitchenOrderItemController@advance
  → updates OrderItem status → syncStatusFromItems()
  → broadcast(OrderItemStatusUpdated) → Reverb → Echo → Kitchen.jsx
  → React patches local state (moves item to new column)
```

---

## Backend

### New route

```
PATCH /kitchen/order-items/{orderItem}   → KitchenOrderItemController@advance
```

Protected by `middleware(['auth', 'role:kitchen,admin'])`.

### `KitchenOrderItemController@advance`

1. Load the `OrderItem` (404 if not found).
2. Reject with 422 if status is already `served` — cannot advance further.
3. Advance to next status: `firing` → `prep`, `prep` → `ready`, `ready` → `served`.
4. Call `$orderItem->order->syncStatusFromItems()`.
5. Dispatch `OrderItemStatusUpdated`.
6. Return JSON with the updated item.

### `KitchenIndexController@index`

Loads initial page state:
- All `OrderItem` records with status `firing`, `prep`, or `ready`, eager-loaded with `order.diningSession.restaurantTable`, `orderItemAllergens`, `station`.
- All `Station` records (for the filter UI).
- Returns an Inertia response to `Kitchen/Index`.

### Events

**`app/Events/OrderPlaced.php`**
- Implements `ShouldBroadcast`
- Broadcasts on `PrivateChannel('kitchen')`
- Payload: the full `Order` with `orderItems.orderItemAllergens`, `diningSession.restaurantTable`

**`app/Events/OrderItemStatusUpdated.php`**
- Implements `ShouldBroadcast`
- Broadcasts on `PrivateChannel('kitchen')`
- Payload: `order_item_id`, `order_id`, `status`

### Channel authorization

`routes/channels.php`:
```php
Broadcast::channel('kitchen', function ($user) {
    return in_array($user->role, ['kitchen', 'admin']);
});
```

### Change to `DiningSession::placeOrder()`

After the transaction completes, dispatch `OrderPlaced` with the loaded order. No other changes to the method.

### Change to `Order::syncStatusFromItems()`

Add `prep` to the status derivation logic:
- `new` → no items
- `prep` → at least one item is `firing` or `prep`
- `served` → all items are `served`
- `ready` → all items are `ready` or `served` but not all `served`

### Schema change to `order_items` migration

Modify the existing `create_order_items_table` migration to add `prep` to the enum:
```php
$table->enum('status', ['firing', 'prep', 'ready', 'served'])->default('firing');
```
Run `php artisan migrate:fresh --seed` to apply. No new migration needed (not yet in production).

---

## Frontend

**File:** `resources/js/Pages/Kitchen/Index.jsx`

### Layout

```
[Filter: All | Sushi | Hot Kitchen | Fry | Cold | Bar]

Sin preparar          En preparación        Listos para entregar
─────────────────     ──────────────────    ────────────────────
[RoundCard]           [RoundCard]           [RoundCard]
[RoundCard]           ...                   ...
```

### Components

**`KitchenIndex`** (page root)
- Holds the full state: array of active rounds (orders with items).
- Connects to Reverb via `useEffect` on mount.
- Handles `OrderPlaced` → appends new round to state + triggers alert.
- Handles `OrderItemStatusUpdated` → patches the matching item in state.
- Passes filtered rounds to each column.

**`StationFilter`**
- Buttons: All + one per station.
- Active filter stored in local state.
- Passed down to columns to filter which items render.

**`RoundCard`**
- Shows: table code, round number, elapsed time since `placed_at`.
- Lists items filtered by the active station filter.
- If no items match the active filter, the card is hidden entirely.
- Items in `firing` render in "Sin preparar" column.
- Items in `prep` render in "En preparación" column.
- Items in `ready` render in "Listos para entregar" column.
- Items in `served` do not render.
- Each item shows: qty, name, note (if any), allergen labels.
- Each item has an advance button:
  - `firing` → "▶ En preparación"
  - `prep` → "▶ Listo"
  - `ready` → "✓ Servido" (triggers disappear)

**Column layout note:** A single round can appear across multiple columns simultaneously because its items advance independently. The three columns are logical views over the same data, not separate lists.

### Alerts

- **New order sound + highlight:** When `OrderPlaced` fires, play a short beep (Web Audio API, no external assets needed) and apply a highlight class to the new card. The highlight fades after 3 seconds.
- **Overdue warning:** Items that have been in `firing` status for more than a configurable threshold (default: 10 minutes, to be made configurable via `Setting` in a future iteration) cause their card to display a red border.

### Real-time connection

```js
useEffect(() => {
    const channel = Echo.private('kitchen')
        .listen('OrderPlaced', handleOrderPlaced)
        .listen('OrderItemStatusUpdated', handleItemUpdated)
    return () => Echo.leave('kitchen')
}, [])
```

### Initial state

`KitchenIndexController` loads all active items on page load. WebSocket events only carry incremental changes. If the connection drops and reconnects, Inertia's `router.reload()` is called to resync state.

---

## Tests

### `tests/Feature/Kitchen/KitchenOrderItemControllerTest.php`

- Advancing `firing` → `prep` returns 200 and persists.
- Advancing `prep` → `ready` returns 200 and persists.
- Advancing `ready` → `served` returns 200 and persists.
- After advancing, `Order::status` reflects the correct derived status.
- A user without `role=kitchen` receives 403.
- Advancing an item already in `served` returns 422.

### `tests/Feature/Kitchen/KitchenIndexTest.php`

- Page returns 200 for a user with `role=kitchen`.
- Unauthenticated user is redirected to login.
- Only items with status `firing`, `prep`, or `ready` are included in the response; `served` items are excluded.

### Event dispatch tests (unit)

- `OrderPlaced` is dispatched when `placeOrder()` completes (using `Event::fake()`).
- `OrderItemStatusUpdated` is dispatched when the controller advances an item.

---

## Out of scope for this iteration

- Per-station assignment of kitchen users (all `kitchen` role users see the same channel).
- Configurable overdue threshold via the admin UI (`Setting` table integration deferred).
- Order recall / undo status change.
- Floor staff `served` confirmation flow (separate feature).
