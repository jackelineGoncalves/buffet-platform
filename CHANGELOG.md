# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - 2026-07-08

### Added
- **Floor display (`/floor`):** Real-time view for staff with `role=floor` or `role=admin`. Shows ready items grouped by table and pending service requests (bill/waiter calls). Items disappear when marked as delivered; service requests disappear when resolved or paid.
- `FloorIndexController@index` (`GET /floor`): loads all `OrderItem` records with status `ready` and all unresolved `ServiceRequest` records, eager-loaded with session and table data. Passes `initialReadyItems`, `initialServiceRequests`, and `setting` to `Floor/Index`.
- `FloorOrderItemController@serve` (`PATCH /floor/order-items/{orderItem}/serve`): marks an item as `served`, calls `syncStatusFromItems()`, dispatches `OrderItemStatusUpdated`.
- `FloorServiceRequestController@resolve` (`PATCH /floor/service-requests/{serviceRequest}/resolve`): sets `resolved_at = now()` on a pending service request.
- `FloorPaymentController@store` (`POST /floor/dining-sessions/{session}/payment`): calculates the full bill (buffet × guests, extras, waste × waste_fee, tax), creates a `Payment` record, resolves any open bill service requests, sets session status to `closed`, and dispatches `DiningSessionClosed`.
- `FloorWasteController@update` (`PATCH /floor/dining-sessions/{session}/waste`): increments or decrements `waste_count` by 1 (delta: +1 or -1, minimum 0).
- `DinerServiceRequestController@store` (`POST /table/{code}/service-requests`): creates a `ServiceRequest` of type `bill` or `server` on the active session; returns 409 if a pending request of the same type already exists. Dispatches `ServiceRequestCreated`.
- `ServiceRequestCreated` broadcast event (`ShouldBroadcastNow`) — dispatched when a diner creates a service request. Broadcasts on the private `floor` channel with the request type, `requested_at`, and table code.
- `DiningSessionClosed` broadcast event (`ShouldBroadcastNow`) — dispatched when a session is closed via payment. Broadcasts on the public `table.{code}` channel so the diner view auto-reloads without requiring authentication.
- `floor` private channel authorization in `routes/channels.php`: users with `role=floor` or `role=admin`.
- `OrderItemStatusUpdated` now broadcasts on both `kitchen` and `floor` channels so floor staff receive real-time item status changes.
- **`Floor/Index.jsx`** — real-time floor page:
  - Ready items grouped by table with "✓ Entregado" button (optimistic update + PATCH + rollback).
  - Service requests in yellow: waiter requests show "Resolver" button; bill requests show a full bill breakdown (buffet, extras, desperdicio with +/− buttons, IVA, total) and a "Cobrar y cerrar" button.
  - Waste counter on bill cards: +/− buttons update `waste_count` optimistically, recalculating the total in real-time.
  - Echo subscription on private `floor` channel: `OrderItemStatusUpdated` patches ready items; `ServiceRequestCreated` reloads service requests; reconnect resync via `router.reload()`.
- **`Diner/Table.jsx`** additions:
  - "Cuenta estimada" section always visible during an active session: buffet × guests, extras, waste fee, IVA, total — calculated from session data already on the page.
  - "🙋 Llamar mesero" and "🧾 Pedir la cuenta" buttons. The bill button is replaced by a warning message if any order items are still in `firing`, `prep`, or `ready` (not all served yet).
  - Subscribes to the public `table.{code}` channel via Echo; reloads automatically when `DiningSessionClosed` fires (session closed by floor staff).
- `role` field added to `Register.jsx` registration form (dropdown: floor / kitchen / admin). Previously the field was missing from the UI, causing all registration attempts to fail validation.
- `Setting` passed to `Diner/Table` page so bill totals can be calculated on the frontend.

### Changed
- Floor routes replaced the previous `floor.dashboard` anonymous closure with a proper named-route group under `middleware(['auth', 'role:floor,admin'])` with prefix `/floor`.
- `AuthenticatedSessionController` post-login redirect updated from `floor.dashboard` to `floor.index`.

## [Unreleased] - 2026-07-07

### Added
- **Kitchen display (`/kitchen`):** Real-time order management view for staff with `role=kitchen` or `role=admin`. Shows all active order items grouped by table and round in a three-column kanban (Sin preparar / En preparación / Listos para entregar). Items advance independently through their lifecycle and disappear from view when marked as served.
- `prep` status added to `order_items.status` enum (`firing → prep → ready → served`) to match the three active columns. Previously only `firing` and `ready` existed.
- `KitchenIndexController@index` (`GET /kitchen`): loads all `OrderItem` records with status `firing`, `prep`, or `ready`, eager-loaded with `order.diningSession.restaurantTable`, `orderItemAllergens`, and `station`. Passes `initialItems` and `stations` to the Inertia `Kitchen/Index` page.
- `KitchenOrderItemController@advance` (`PATCH /kitchen/order-items/{orderItem}`): advances an item's status one step (`firing→prep`, `prep→ready`, `ready→served`), calls `Order::syncStatusFromItems()` to keep the parent order's cached status in sync, and dispatches `OrderItemStatusUpdated`.
- **Laravel Reverb** installed as the WebSocket server; `resources/js/echo.js` configures the Laravel Echo frontend client connected to the `reverb` broadcaster. Echo is imported globally in `app.jsx`.
- `OrderPlaced` broadcast event (`ShouldBroadcastNow`) — dispatched by `DiningSession::placeOrder()` after the transaction commits. Broadcasts on the private `kitchen` channel with the full order payload (table code, round, all items with allergens and station).
- `OrderItemStatusUpdated` broadcast event (`ShouldBroadcastNow`) — dispatched by `KitchenOrderItemController@advance` after each status change. Broadcasts `order_item_id`, `order_id`, and new `status` on the private `kitchen` channel.
- Kitchen channel authorization in `routes/channels.php`: users with `role=kitchen` or `role=admin` are granted access to `Broadcast::channel('kitchen', ...)`.
- **`Kitchen/Index.jsx`** — full real-time React page:
  - `StationFilter`: "Todas" + one button per station; active station filters item visibility across all columns.
  - `RoundCard`: shows table code, round number, and elapsed time since `placed_at`; yellow border/background for newly arrived orders (3-second highlight); red border for rounds with any item overdue in `firing` (>10 min).
  - `ItemRow`: displays qty × name, optional note, optional allergen labels, and a per-status advance button ("▶ En preparación", "▶ Listo", "✓ Servido"). Clicking fires an optimistic state update immediately, then PATCHes the server; rolls back cleanly on failure (including the `ready→served` case where the item was already removed from the array).
  - Echo subscription on `window.Echo.private('kitchen')`: `.listen('.OrderPlaced', ...)` plays a beep (Web Audio API) and adds the new items to state; `.listen('.OrderItemStatusUpdated', ...)` patches the matching item or removes it if now `served`.
  - Reconnect resync: if the WebSocket drops and reconnects, `router.reload({ only: ['initialItems'] })` is called to resync the board.
  - A 30-second tick drives elapsed/overdue recalculation without polling the server.

### Changed
- `Order::syncStatusFromItems()` updated: any item in `firing` **or** `prep` now drives the order status to `prep` (previously only `firing` was checked). This keeps the `orders.status` column consistent with the four-step item lifecycle.
- `DiningSession::placeOrder()` now dispatches `OrderPlaced` after the transaction; the dispatch is wrapped in try/catch so a Reverb outage cannot 500 the diner's request — the order is always persisted regardless of broadcast availability.
- `GET /kitchen` and `PATCH /kitchen/order-items/{orderItem}` routes replaced the previous `kitchen.dashboard` anonymous closure with a proper named-route group under `middleware(['auth', 'role:kitchen,admin'])`.
- `AuthenticatedSessionController` post-login redirect updated from the deleted `kitchen.dashboard` route name to `kitchen.index`.
- `bootstrap/app.php`: `channels:` key added to `withRouting()` to register `routes/channels.php` (done automatically by `reverb:install`).

### Tests
- `OrderTest`: added `test_sync_status_from_items_is_prep_when_any_item_is_in_prep()` — verifies that a mix of `ready` + `prep` items drives order status to `prep`.
- `DiningSessionTest`: added `test_place_order_dispatches_order_placed_event()` — uses `Event::fake()` to assert `OrderPlaced` is dispatched with the correct order.
- Added `tests/Feature/Kitchen/KitchenIndexTest.php` (4 tests): 200 for kitchen user, redirect to login for unauthenticated, 403 for non-kitchen role, only active items (`firing`/`prep`/`ready`) included in `initialItems` prop.
- Added `tests/Feature/Kitchen/KitchenOrderItemControllerTest.php` (7 tests): `firing→prep`, `prep→ready`, `ready→served` each return 200 and persist; order status reflects correct derived state after advance; 422 for already-`served` item; 403 for non-kitchen role; `OrderItemStatusUpdated` dispatched with correct payload.

## [Unreleased] - 2026-07-06

### Added
- `is_extra` boolean column on `dishes` (default `false`) to distinguish buffet-included dishes from paid add-ons shown with their price in the diner UI.
- `DiningSession::placeOrder(array $items): Order` — domain method that creates an order inside a session: auto-increments the round number, snapshots each dish's name/price/station at order time, captures allergens as `OrderItemAllergen` records (with snapshot `label`), all inside a DB transaction.
- `DinerTableController@show` (`GET /table/{code}`): resolves the table by code (404 on miss), loads the active (non-`closed`) session with its orders/items/allergens, builds the categorised menu (available dishes only), and passes everything to the Inertia `Diner/Table` page.
- `DinerSessionController@store` (`POST /table/{code}/session`): opens a new dining session; returns 409 with the existing session if one is already active, using `TableOccupiedException` as the signal.
- `DinerOrderController@store` (`POST /table/{code}/orders`): places a new order round on the active session; returns 409 when no session is open.
- `StoreDinerSessionRequest`: validates `guests` is a positive integer.
- `StoreDinerOrderRequest`: validates the `items` array (each item needs an existing `dish_id`, `qty ≥ 1`, optional `note`, optional `allergen_ids` that exist in the DB); a `withValidator` hook adds a second-pass DB check that rejects any dish with `is_available = false`.
- `DishSeeder` and `RestaurantTableSeeder` with example data (11 sushi dishes including extras, 3 tables T1–T3); both registered in `DatabaseSeeder`.
- CSRF token meta tag in `resources/views/app.blade.php` so frontend `fetch()` calls can sign POST requests without Axios.

### Changed
- `GET /table/{code}` replaced the previous anonymous closure with `DinerTableController@show`, now returning a full data payload instead of just the raw code string.
- `bootstrap/app.php`: JSON error responses are now returned whenever the request `expectsJson()`, not only for `/api/*` routes — required for `fetch()`-based calls from the diner frontend to receive parseable error bodies.
- `Diner/Table.jsx` rewritten from a static placeholder into a full interactive diner UI: `OpenSessionForm` (opens a session when none is active), `OrdersList` (shows previous rounds), and `MenuAndCart`/`CartRow` (per-dish quantity, note, and allergen checkboxes); mutations use `fetch()` + CSRF, then `router.reload()` to refresh Inertia props.
- `DinerTableRouteTest` updated to create a real `RestaurantTable` row before hitting the route (controller now does a DB lookup).

### Tests
- Added `DinerSessionControllerTest`: covers session creation, 409 on duplicate, 404 for unknown table code, validation rejection of invalid `guests`.
- Added `DinerOrderControllerTest`: covers successful order with allergens, round-number increment across successive submissions, 409 with no active session, 404 for unknown table code, rejection of unavailable dishes, non-existent dish IDs, and non-existent allergen IDs.
- Added two `DishTest` cases for `is_extra` default value and explicit `true` assignment.
- Added two `DiningSessionTest` cases for `placeOrder()`: snapshot fidelity (name, price, station, allergen label, default `firing` status) and round-number auto-increment across successive calls.
- Added `DinerTableRouteTest` case for 404 on an unknown table code.

## [Unreleased] - 2026-06-24

### Added
- Catalog tables for the menu domain: `stations`, `categories`, `tags`, `allergens`, `settings`, with migrations, Eloquent models (`Station`, `Category`, `Tag`, `Allergen`, `Setting`), and seeders (`StationSeeder`, `CategorySeeder`, `TagSeeder`, `AllergenSeeder`, `SettingSeeder`).
- `DatabaseSeeder` now calls the new catalog seeders.
- Claude Code skills (`laravel-specialist`, `php-pro`) under `.claude/skills/` to guide Laravel/PHP development conventions in this repo.
- Menu tables: `dishes` (`category_id`/`station_id` FKs with `restrictOnDelete`, nullable `price` for buffet vs. add-on items, nullable `per_round_limit`) and the `dish_tag` pivot, with the `Dish` model (`belongsTo` Category/Station, `belongsToMany` Tag).
- Operational tables for the dining-session flow:
  - `restaurant_tables` (`code`, `seats`).
  - `dining_sessions` (FK to `restaurant_tables` with `restrictOnDelete`, `status` enum defaulting to `seated`, `waste_count` defaulting to 0, `opened_at`/`closed_at`, indexed `status`).
  - `orders` (FK to `dining_sessions` with `cascadeOnDelete`, `number`, `round`, `status` enum defaulting to `new`, `placed_at`, unique `(dining_session_id, number)` so ticket numbers can't repeat within the same session).
  - `order_items` (FK to `orders` cascading, nullable snapshot FK to `dishes` (`nullOnDelete`), snapshot `name`/`station_id` for KDS routing, nullable `unit_price`, `status` enum defaulting to `firing`, composite index on `(status, station_id)`).
  - `order_item_allergens` (FK to `order_items` cascading, nullable snapshot FK to `allergens` (`nullOnDelete`), snapshot `label`).
  - `service_requests` (FK to `dining_sessions` cascading, `type` enum `bill`/`server`, nullable `resolved_at`).
  - `payments` (unique FK to `dining_sessions` cascading — one payment per session, `buffet_total`/`extras_total`/`waste_total`/`tax`/`total`, `paid_at`).
- Eloquent models `RestaurantTable`, `DiningSession`, `Order`, `OrderItem`, `OrderItemAllergen`, `ServiceRequest`, `Payment` with the relationships defined in `CLAUDE.md` (e.g. `DiningSession::orders()`, `Order::orderItems()`, `DiningSession::payment()` as `hasOne`).
- `Order::syncStatusFromItems()` derives `orders.status` from its `order_items`: `new` with no items, `prep` while any item is `firing`, `served` when all items are `served`, `ready` otherwise. Call this after advancing an item's status to keep the cached `status` column in sync, as `CLAUDE.md` recommends.
- `App\Exceptions\TableOccupiedException`, thrown from a `DiningSession::creating` model event when a `restaurant_table` already has a non-`closed` session — prevents opening two simultaneous sessions on the same physical table.
- A partial unique index (`dining_sessions_one_active_per_table` on `restaurant_table_id` `where status != 'closed'`, SQLite/Postgres only) added via the `add_one_active_session_per_table_constraint` migration, closing the race-condition gap the app-level `creating` check can't cover on its own. `DiningSession::performInsert()` catches the resulting `QueryException` and rethrows it as `TableOccupiedException` so callers see the same exception either way.

### Changed
- `orders` migration adds a unique index on `(dining_session_id, number)` instead of a plain index — ticket numbers are only guaranteed unique within their own session, not globally.

### Tests
- Added `StationSeederTest`, `CategorySeederTest`, `TagSeederTest`, `AllergenSeederTest`, `SettingSeederTest`.
- Added `DishTest` covering category/station relationships, nullable buffet pricing, add-on pricing, and the `dish_tag` many-to-many relation.
- Added `RestaurantTableTest`, `DiningSessionTest`, `OrderTest`, `OrderItemTest`, `OrderItemAllergenTest`, `ServiceRequestTest`, `PaymentTest`, covering relationships, status/column defaults, snapshot preservation on `dish`/`allergen` deletion, the one-payment-per-session constraint, the per-session unique order number, the `Order::syncStatusFromItems()` state machine, and the one-active-session-per-table guard.
- Added a `DiningSessionTest` case that inserts directly via `DB::table()` (bypassing the Eloquent `creating` event) to prove the partial unique index — not just app code — blocks a second active session.

## [Unreleased] - 2026-06-17

### Added
- `role` column on `users` (`admin`, `kitchen`, `floor`, default `floor`) via migration.
- `RoleMiddleware` (`app/Http/Middleware/RoleMiddleware.php`), registered as the `role` route alias.
- Role-specific dashboards: `/admin`, `/kitchen`, `/floor`, each protected by `role:<name>`.
- Public diner route `GET /table/{code}` (no auth) rendering a placeholder `Diner/Table` Inertia page — full dining-session logic deferred until that schema exists.
- Seeder now creates the default `test@example.com` user with `role=admin` so there's a bootstrap account to create staff logins.

### Changed
- `/register` moved from the `guest` middleware group to `['auth', 'role:admin']` — only an authenticated admin can view or submit it.
- `RegisteredUserController@store` no longer auto-logs-in the newly created user (previously this would replace the admin's session); it now requires a `role` field and redirects back to `/register`.
- `AuthenticatedSessionController@store` redirects after login based on the user's role (`admin` → `/admin`, `kitchen` → `/kitchen`, `floor` → `/floor`) instead of a single shared `/dashboard`.
- `/dashboard` kept as a thin redirector to the role-specific route, to avoid breaking the existing frontend nav link.
- `Welcome.jsx` no longer shows a "Register" link to guests (registration is staff-only now); guests only see "Log in".

### Removed
- Email verification flow: `verify-email` routes, `EmailVerificationPromptController`, `EmailVerificationNotificationController`, `VerifyEmailController`, and the `Auth/VerifyEmail.jsx` page. The `App\Models\User` model never implemented the `MustVerifyEmail` contract, so this was already a no-op — removed as dead code.

### Tests
- Added `RoleMiddlewareTest`, `RoleDashboardsTest`, `DinerTableRouteTest`.
- Updated `RegistrationTest`, `AuthenticationTest`, `EmailVerificationTest` to reflect the new admin-gated registration, role-based redirects, and removal of email verification.
