# Changelog

All notable changes to this project will be documented in this file.

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
