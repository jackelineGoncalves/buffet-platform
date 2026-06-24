# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A buffet/restaurant management platform built on **Laravel 13 + Inertia.js + React 18**. It serves two distinct audiences from one app:

- **Staff** (authenticated, role-gated): `admin`, `kitchen`, `floor` roles managing dishes, orders, and dining sessions.
- **Diners** (unauthenticated, public): access a table-specific ordering flow via `/table/{code}`.

## Commands

```bash
# Setup (composer install, .env, key:generate, migrate, npm install+build)
npm run setup

# Local development (runs server, queue listener, log tailing, and Vite concurrently)
npm run dev

# Frontend only
npm run build            # production build of resources/js via Vite

# Backend only
php artisan serve
php artisan queue:listen
php artisan pail         # real-time log streaming

# Tests
composer test            # clears config cache, then runs `php artisan test`
php artisan test --filter=TestName     # run a single test
php artisan test tests/Feature/OrderTest.php   # run a single test file
```

There is no ESLint/Prettier/Pint config wired up yet, even though Laravel Pint is a dev dependency — don't assume a lint step exists.

## Architecture

### Auth & roles

- `App\Models\User` has a `role` column: `admin`, `kitchen`, or `floor`. There is no diner account — diners interact via signed table codes, not auth.
- `App\Http\Middleware\RoleMiddleware` enforces role checks via `middleware('role:admin')` or `role:kitchen,floor` (comma-separated, OR semantics), aborting 403 on mismatch.
- `routes/web.php` exposes role-specific dashboards (`/admin`, `/kitchen`, `/floor`) gated by this middleware, plus the public `/table/{code}` diner flow (no auth).
- `routes/auth.php` is Breeze-style auth (login, password reset); registration is restricted to `admin` only — staff accounts are provisioned, not self-registered.
- Sanctum is installed (`config/sanctum.php`) but the primary session flow is Inertia/cookie-based, not token API auth.

### Domain model

The core entity is the **dining session**, anchored to a physical table:

- `RestaurantTable` → has many `DiningSession`
- `DiningSession` (status `seated`/`bill`/`paid`/`closed` — only `closed` is considered inactive) → has many `Order`, has many `ServiceRequest`, has one `Payment`
  - "One active session per table" is enforced at two levels: an app-level pre-check in the model's `creating` event (fast-fail, throws `App\Exceptions\TableOccupiedException`), and a partial unique index on `dining_sessions (restaurant_table_id) WHERE status != 'closed'` (added in the `add_one_active_session_per_table_constraint` migration) that closes the race-condition gap the app-level check can't cover. `DiningSession::performInsert()` catches the index violation and rethrows it as the same `TableOccupiedException`. The partial-index approach only works on SQLite/Postgres, not MySQL.
- `Order` (status `new`/`prep`/`ready`/`served`, has a `round` number) → has many `OrderItem`
  - `Order::syncStatusFromItems()` derives the order's status from the aggregate state of its items — call this after mutating item statuses rather than setting order status directly.
- `OrderItem` (status `firing`/`ready`/`served`) → belongs to `Order`, `Dish`, `Station`; has many `OrderItemAllergen`
- `Dish` → belongs to `Category` and `Station`; belongs-to-many `Tag`
- `OrderItemAllergen` → links an `OrderItem` to an `Allergen`, capturing a point-in-time `label` (so historical orders aren't affected by later allergen renames)
- `Payment` → belongs to `DiningSession`; tracks `buffet_total`, `extras_total`, `waste_total`, `tax`, `total`
- `Setting` is a singleton-style config row (`buffet_price`, `waste_fee`, `session_minutes`, `last_call_minutes`, `tax_rate`) driving pricing/timing logic across sessions

When adding features that touch order/session state, check both `Order` and `DiningSession` for derived-status logic before writing new state transitions.

### Frontend (Inertia + React)

- Entry point `resources/js/app.jsx` resolves page components from `resources/js/Pages/**/*.jsx` — there is no separate REST/JSON API layer for the frontend; controllers return Inertia responses directly.
- `resources/js/Pages/` is organized by audience: `Diner/` for the public table flow, plus `Auth/`, `Dashboard.jsx`, `Profile/` for staff.
- `App\Http\Middleware\HandleInertiaRequests` shares `auth.user` (and other global props) with every page — add new globally-needed props here rather than per-controller.
- Shared UI lives in `resources/js/Components/`, layouts in `resources/js/Layouts/`.

### Tests

- PHPUnit (`phpunit.xml`), suites under `tests/Unit/` and `tests/Feature/`.
- Uses SQLite in-memory for the test DB, `sync` queue, array cache/session — tests don't need external services.
- Feature tests are organized per-model/domain concern (e.g. `tests/Feature/OrderTest.php`, `DiningSessionTest.php`, `OrderItemAllergenTest.php`).
