# Changelog

All notable changes to this project will be documented in this file.

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
