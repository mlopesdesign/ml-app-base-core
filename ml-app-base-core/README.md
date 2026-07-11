# ML App Base Core — WordPress Plugin

**Version:** 0.1.32  
**Author:** ML Lopes Design  
**Installation target:** `https://app.example.com` **only**

---

## Purpose

`ml-app-base-core` is the private backend plugin for the official App Base mobile application. It runs exclusively on the clean, separate WordPress installation at `app.appbase.com.br` and must **never** be installed on the main public site (`appbase.com.br`).

It manages all private mobile app data:

- App user registration and authentication
- Session and bearer token management
- Birthday fields (day/month; year optional)
- Expo push device tokens
- Notification preferences and LGPD consent records
- Favorited events and news
- App configuration endpoint
- Notification audit logs

---

## Architecture context

This plugin is part of a hybrid architecture:

| Layer | Location | Purpose |
|---|---|---|
| Public content | `appbase.com.br` | Events, news, galleries, banners |
| Private app core | `app.appbase.com.br` | Users, sessions, tokens, LGPD |
| Mobile app | Expo / EAS | React Native client |

The mobile app consumes two separate APIs:
- **Public:** `appbase.com.br/wp-json/ml-app-base/v1/`
- **Private:** `app.appbase.com.br/wp-json/ml-app-base/v1/`

---

## Installation

1. Upload `ml-app-base-core/` to `/wp-content/plugins/` on `app.appbase.com.br`.
2. Activate the plugin in the WordPress admin.
3. Plugin activation automatically creates all required database tables via `dbDelta`.
4. Tables use the WordPress table prefix configured for this installation (`wpapp_`).

**Requirements:**
- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Permalink structure set to "Post name" for REST API routes to work.

---

## REST Namespace

```
ml-app-base/v1
Base URL: https://app.example.com/wp-json/ml-app-base/v1
```

---


## Admin user controls


Version 0.1.30 adds native Firebase Cloud Messaging (FCM) push delivery for the React Native app, keeps Expo Push fallback for legacy tokens, and adds Firebase settings in the admin panel.

Version 0.1.29 adds category/subcategory hierarchy and promotional highlights to the AppCore-owned Cardápio module while preserving the AppCore admin menu position and REST compatibility.

Version 0.1.20 adds administrative user actions in WordPress: block user, unblock user, safe-delete user, and block/unblock e-mail addresses. Blocking revokes sessions and disables devices. Safe-delete anonymizes personal data and does not drop plugin tables.

## Endpoints

### Public (no authentication required)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/config` | App configuration and feature flags |
| POST | `/auth/register` | Register new app user |
| POST | `/auth/login` | Login and receive bearer token |

### Authenticated (Authorization: Bearer {token})

| Method | Endpoint | Description |
|---|---|---|
| POST | `/auth/logout` | Revoke current session |
| GET | `/me` | Get profile + notification preferences |
| PATCH | `/me` | Update profile fields |
| DELETE | `/me` | Soft-delete account (LGPD cleanup) |
| POST | `/devices/register` | Register Expo push token |
| POST | `/devices/unregister` | Unregister device |
| GET | `/favorites` | Paginated favorites list |
| POST | `/favorites` | Add a favorite |
| DELETE | `/favorites/{id}` | Remove a favorite |
| GET | `/notifications/preferences` | Get notification consent flags |
| PATCH | `/notifications/preferences` | Update notification consent flags |

---

## Database tables

All tables use `$wpdb->prefix` — never hardcoded `wp_`.
Production prefix on `app.appbase.com.br` is expected to be `wpapp_`.

| Table suffix | Purpose |
|---|---|
| `ml_app_base_users` | Custom app user profiles (separate from wp_users) |
| `ml_app_base_sessions` | Opaque bearer token hashes and session metadata |
| `ml_app_base_devices` | Expo push tokens per user device |
| `ml_app_base_favorites` | User-favorited content references |
| `ml_app_base_notification_consents` | LGPD notification opt-in flags (one per user) |
| `ml_app_base_notification_logs` | Push notification audit log |

Schema version is tracked in WordPress option `ml_app_base_core_schema_version`.

---

## Security model

- Passwords are hashed with `wp_hash_password()` — never stored in plain text.
- `password_hash` is never returned in any API response.
- Bearer tokens are generated with `random_bytes(32)` (64 hex chars).
- Only the SHA-256 hash of each token is stored in `ml_app_base_sessions`.
- Plain tokens are returned to the client exactly once (at register/login).
- Sessions are invalidated by `revoked_at` (logout) or `expires_at` (expiry).
- Deleted users (`status = deleted`) cannot authenticate.
- All inputs are sanitized; all admin outputs are escaped.
- ABSPATH guard is present in every PHP file.
- `$wpdb->prepare()` is used for all parameterized queries.

---

## LGPD compliance

- Registration requires explicit acceptance of terms and privacy policy.
- Consent timestamps (`accepts_terms_at`, `accepts_privacy_at`) are recorded.
- Birthday year is optional — only stored when a feature requires it.
- Push tokens are treated as personal data.
- Account deletion (`DELETE /me`) performs:
  - Session revocation for all active sessions
  - Device soft-deletion (push tokens disabled)
  - Permanent deletion of favorites
  - Permanent deletion of notification consent records
  - User status set to `deleted`, `deleted_at` set
- Notification audit logs retain anonymized records for compliance.
- Birthday automation must always check `birthday_enabled AND push_enabled` before sending.

---

## No-secrets rule

**No real credentials, tokens, passwords, API keys, or production secrets may be written into this plugin's files, commits, or releases.**

Placeholder variable names only. Real values must be stored in:
- Server-side `.env` files (never committed)
- Hestia Control Panel environment variables
- EAS Build secrets (for mobile app variables)

---

## Deployment notes

- This plugin targets `app.appbase.com.br` exclusively.
- Database: separate MySQL/MariaDB database, table prefix `wpapp_`.
- Do not install on `appbase.com.br` (main public site).
- ZIP packaging and deployment validation: see Prompt 03.
- WordPress admin page: **ML App Base** (left sidebar, dashicons-smartphone icon).

---

## Changelog

### 0.1.29
- Added category photos and home visibility for Cardápio categories.
- Added home-ready featured/promotional item payloads for the mobile app.
- Added /menu/home and /menu/featured endpoints while preserving the existing /menu contract.

### 0.1.2 — 2026-05-11
- Fixed DELETE /me confirmation handling to support both query param and body.

### 0.1.1 — 2026-05-10
- Added `uninstall.php` professional cleanup routine.
- Uninstall drops plugin-owned custom tables when plugin is deleted from admin.
- Uninstall deletes plugin-owned options and transients.
- Uninstall does NOT delete uploads, media, `wp_users`, posts, or public site data.
- Added permanent deletion warning to admin dashboard.

### 0.1.0 — 2026-05-10
- Initial release.
- All 6 database tables created via `dbDelta` on activation.
- 14 REST endpoints implemented under `ml-app-base/v1`.
- Opaque bearer token authentication (SHA-256 hashed storage).
- LGPD consent model at registration and account deletion.
- Admin page with table status, endpoint list, and security notes.
