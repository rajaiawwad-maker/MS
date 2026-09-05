# DJ RAK Manager — REST API v1

**Version:** 1.0
**Date:** 2026-09-05
**Server Timezone:** Asia/Riyadh (UTC+3)

---

## 1. INTRODUCTION

### 1.1 Project Name: DJ RAK Manager

DJ RAK Manager is a DJ equipment rental management system built with pure PHP 7.3 (procedural style) and MySQL. It manages clients, bookings, inventory, expenses, payments, reporting, auditing, and user permissions.

### 1.2 REST API v1 Purpose

The v1 REST API exposes the full set of DJ RAK Manager web services to be consumed by the mobile iOS/Android applications. Every operation available in the web panel has a corresponding JSON endpoint. The API accepts JSON request bodies for write operations and always returns JSON responses (UTF-8 encoded).

### 1.3 Base URL and Versioning Policy

- **API Prefix:** `/api/v1/`
- **Local XAMPP Base URL (typical):** `http://localhost/project/MS/api/v1`
- **Production pattern:** `https://<host>/project/MS/api/v1`
- **Fallback path without Apache mod_rewrite:** `/api/index.php/v1/...` (for example `/api/index.php/v1/auth/login`)

The API uses a **path-based versioning strategy** via the `/api/v1/` prefix. Breaking changes will increment the version segment (e.g. `/api/v2/`) while leaving v1 stable for existing mobile clients. Non-breaking additions (new optional fields, new endpoints) may be deployed to v1 at any time.

### 1.4 Contacts

For API-related issues and integration questions contact the project maintainer: DJ RAK Engineering Team.

### 1.5 Global Transport Notes

- All POST/PUT requests must send body as **JSON** with header `Content-Type: application/json; charset=utf-8`.
- All responses return JSON with `Content-Type: application/json; charset=utf-8`.
- Character set is always UTF-8.
- Dates are returned and expected as `YYYY-MM-DD`.
- Datetimes are returned and expected as `YYYY-MM-DD HH:MM:SS` in **Asia/Riyadh** server timezone (UTC+3). Do NOT convert to UTC when sending — send the local Riyadh wall-clock values.
- Time-only fields (event_start_time, event_end_time) use `HH:MM:SS` 24-hour format.
- All monetary values are **numbers (float or integer)** in JSON — never string formatted currencies. Currency is assumed JOD unless a `currency_code` setting is overridden in system_settings.
- Boolean values in JSON are native `true` / `false`.
- Nullable fields return `null` explicitly in JSON; omitting a nullable field on write is equivalent to sending `null`.

---

## 2. AUTHENTICATION

Authentication uses **Bearer tokens** issued at login time. Tokens are cryptographically-random 64-hex-character strings (32 random bytes, hex-encoded). The raw token is returned to the client **exactly once**. The server stores only the **SHA-256 hash** of the token in the `api_tokens` table, so a database leak cannot be used to authenticate.

### 2.1 Obtain a Token (Login)

**Endpoint:** `POST /api/v1/auth/login` (public — no auth required)

Request body:

```json
{
  "username": "admin",
  "password": "admin123",
  "device_name": "iPhone 15 Pro - Mohammed"
}
```

- `username` **required** — user's login username.
- `password` **required** — user's plain-text password.
- `device_name` **optional** — label stored with the token for user-visible session management (recommended: OS + model + owner name).

Success response (HTTP 200):

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token_type": "Bearer",
    "access_token": "a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2",
    "expires_at": "2026-09-12 14:30:00",
    "user": {
      "id": 1,
      "name": "System Administrator",
      "username": "admin",
      "email": "admin@djrak.com",
      "phone": "+966500000000",
      "role_id": 1,
      "role_name": "Administrator",
      "active": true,
      "permissions": [
        "manage_users",
        "manage_setup",
        "manage_inventory",
        "manage_clients",
        "view_clients",
        "create_bookings",
        "edit_bookings",
        "cancel_bookings",
        "view_bookings",
        "record_payments",
        "view_financials",
        "view_dj_rak",
        "manage_expenses",
        "view_expenses",
        "view_reports",
        "view_calendar",
        "view_dashboard",
        "send_whatsapp",
        "view_audit_logs",
        "manage_settings",
        "override_inventory"
      ],
      "lang": "en"
    }
  },
  "pagination": null
}
```

The `access_token` is 64 hex chars. Save it securely on the device. `expires_at` is an ISO-style datetime in Asia/Riyadh.

### 2.2 Send the Token on Every Authenticated Request

Add the HTTP header:

```
Authorization: Bearer <access_token>
```

For example (with a real token):

```
Authorization: Bearer a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2
```

If the header is missing, malformed, or the token hash does not exist or has expired, the server responds with HTTP 401.

### 2.3 Public Endpoints (no Authorization header required)

| Method | URL | Purpose |
| :--- | :--- | :--- |
| POST | `/api/v1/auth/login` | Issue a fresh access token |
| GET | `/api/v1/public/confirm/:token` | Retrieve booking details for a customer confirmation link |
| POST | `/api/v1/public/confirm/:token` | Customer confirms / requests change / declines |
| GET | `/api/v1/i18n/:lang` | Load language dictionary |

All other endpoints require Authorization header.

### 2.4 Revoke a Token (Logout)

**Endpoint:** `POST /api/v1/auth/logout` — Authorization required.

Deletes the token hash row from `api_tokens` table, immediately invalidating it. Returns HTTP 200.

### 2.5 Token Expiry and Renewal

- Each issued token is valid for **7 days (604,800 seconds)** from creation.
- Every use of the token updates `api_tokens.last_used_at`, but does **not** extend the original `expires_at`.
- If a request arrives with an expired token, the server responds:

```json
{
  "success": false,
  "message": "Unauthenticated",
  "error_code": "token_expired",
  "errors": null
}
```

with HTTP 401. The mobile app must prompt the user to log in again.

### 2.6 Security Notes

1. **Treat the access_token as a password.** It grants full API access as the user.
2. **Never log the raw token** on the device, in crash logs, or in third-party analytics.
3. **Store the token encrypted on the device.** iOS apps must use Keychain Services; Android apps must use Android Keystore (Jetpack Security EncryptedSharedPreferences). Do NOT store tokens in SharedPreferences / NSUserDefaults plaintext.
4. Use HTTPS in production. If HTTPS is not available, do **not** send credentials or tokens over plain HTTP outside a trusted local dev environment.
5. When a user logs out, always call `POST /auth/logout` so the server-side hash is deleted. If the app is uninstalled without logout, the token will naturally expire after 7 days.
6. `api_tokens` table is created **lazily** on the first successful login via `CREATE TABLE IF NOT EXISTS`. If the MySQL user lacks `CREATE TABLE` privilege, see **Troubleshooting > Missing /api_tokens table**.

---

## 3. CONVENTIONS

### 3.1 Response Envelope Format

Every response shares a uniform top-level envelope.

#### 3.1.1 Success Envelope

```json
{
  "success": true,
  "message": "OK",
  "data": { /* object | array | string | number | boolean | null */ },
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 147,
    "total_pages": 8
  }
}
```

- `success` is always `true` for non-error responses.
- `message` is a human-readable summary (in the current user's language, if i18n session is active).
- `data` carries the primary payload. For list endpoints it is an array of records; for single-item endpoints it is an object; for delete/action endpoints it is often `null`.
- `pagination` is an object when the endpoint is paginated (list endpoints); otherwise it is `null`.

#### 3.1.2 Error Envelope

```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "validation_error",
  "errors": {
    "username": ["Username is required"],
    "password": ["Password must be at least 8 characters"],
    "items": {
      "0": {
        "quantity": ["quantity must be at least 1"]
      }
    }
  }
}
```

- `success` is always `false` for error responses.
- `message` is a high-level summary.
- `error_code` is a stable machine-readable string (use this for branching in client code; do **not** branch on the English `message`).
- `errors` is an object of per-field arrays of messages. For validation errors (HTTP 422) this object is always populated. For other errors it is often `null`. Nested validation errors (e.g. items array) use integer-string keys matching the array index.

### 3.2 HTTP Status Codes

| Code | Name | Used When |
| :--- | :--- | :--- |
| 200 | OK | Request succeeded (read, update, delete, actions, logout). |
| 201 | Created | A new resource was created (POST create endpoints). |
| 400 | Bad Request | Malformed request body, invalid action, unparseable JSON. |
| 401 | Unauthenticated | Missing / malformed / invalid / expired token, or wrong credentials on login. |
| 403 | Forbidden | Token is valid but user lacks the required permission for this endpoint. |
| 404 | Not Found | Resource does not exist or URL path is wrong. |
| 405 | Method Not Allowed | Route exists but wrong HTTP verb (e.g. GET on a POST-only endpoint). |
| 409 | Conflict | Duplicate unique-constraint violation (e.g. duplicate username). |
| 422 | Unprocessable Entity | Input validation failed. Inspect `errors` field. |
| 429 | Too Many Requests | Login rate limit exceeded (see §3.7 Rate Limiting). Include `Retry-After` header. |
| 500 | Internal Server Error | Unrecoverable server failure (database down, PDO exception, misconfiguration). |

### 3.3 Common Error Codes (machine-readable)

| `error_code` | Typical HTTP | Meaning |
| :--- | :--- | :--- |
| `unauthenticated` | 401 | No Authorization header or header unparseable. |
| `token_invalid` | 401 | Token hash not found in DB. |
| `token_expired` | 401 | Token expired; re-login. |
| `forbidden` | 403 | User lacks the permission required by the endpoint. |
| `not_found` | 404 | Resource (booking, client, user, etc.) not found. |
| `invalid_action` | 400 | Illegal `action` value, e.g. wrong confirm action in public endpoint. |
| `invalid_credentials` | 401 | Wrong username or password on login. |
| `search_query_short` | 422 | Global search `q` shorter than 2 characters. |
| `validation_error` | 422 | Input validation failed; `errors` field populated. |
| `not_implemented` | 500 | Future placeholder (rare). |
| `too_many_login_attempts` | 429 | Login throttle tripped. |
| `booking_not_found` | 404 | Public confirm token matched no booking. |
| `booking_canceled` | 404 | Public confirm token matched a booking in Canceled status. |
| `invalid_lang_code` | 422 | i18n/set sent a language other than `en` / `ar`. |

### 3.4 Pagination

All list endpoints that return arrays accept pagination query parameters:

- `?page=1` — default page is `1`. Pages are 1-indexed.
- `?per_page=20` — default page size is `20`.
- Maximum permitted `per_page` value is **100**. Larger values are silently clamped to 100.

Success envelope includes `pagination`:

```json
"pagination": {
  "page": 2,
  "per_page": 20,
  "total": 147,
  "total_pages": 8
}
```

### 3.5 Filtering Patterns

Each endpoint documents its own query filters. The common patterns are:

| Pattern | Example | Meaning |
| :--- | :--- | :--- |
| Exact status | `?status=Confirmed` | Only bookings with `status = 'Confirmed'`. |
| Date range | `?date_from=2026-01-01&date_to=2026-12-31` | Range inclusive. |
| Client FK | `?client_id=3` | Only records belonging to client id=3. |
| Text search | `?q=BK2026` | Substring match on identifier fields (booking_number, client name, etc.). |
| Type FK | `?type_id=2` | Only expense rows of type id=2. |
| Entity filter | `?entity_type=booking` | Audit logs filtered by entity type. |
| User FK | `?user_id=1` | Audit logs created by user id=1. |

Unrecognized query parameters are ignored silently.

### 3.6 Dates and Datetimes

- Server date calculations all use **Asia/Riyadh** (configured in `config.php` with `date_default_timezone_set('Asia/Riyadh')`).
- `YYYY-MM-DD` for `DATE` fields (date_from, date_to, payment_date, purchase_date, etc.).
- `YYYY-MM-DD HH:MM:SS` for `DATETIME` fields (created_at, updated_at, customer_confirmed_at, expires_at, last_login, etc.).
- `HH:MM:SS` for `TIME` fields (event_start_time, event_end_time).
- Clients should format for display using the user's locale, but always wire values back to the API in the canonical formats above.

### 3.7 Money and Currency

- All monetary DB columns are `DECIMAL(12,2)`.
- JSON payloads return money as JSON numbers (float).
- Default currency is **JOD** (Jordanian Dinar), set in `system_settings` key `currency_code` with symbol `currency_symbol`.
- Clients can look up the active symbol via `GET /settings` (requires `manage_settings`) or from login response user's system settings (or by fetching settings explicitly).
- For locale-aware display on mobile, feed the raw numeric amount plus the symbol into a `formatMoney` helper (e.g. `1,250.00 JOD`).

### 3.8 Internationalization (i18n)

The API supports two language codes: `en` (English, LTR default) and `ar` (Arabic, RTL).

- `GET /api/v1/i18n/en` or `GET /api/v1/i18n/ar` — load the complete dictionary. The response merges the requested language on top of English, so keys missing from an Arabic dictionary still fall back to English. Response payload: `{lang, count, dictionary:{key:value}}`.
- `POST /api/v1/i18n/set` with body `{"lang":"ar"}` — stores the active language in the session linked to the token. Subsequent response messages will use the chosen language where translated strings are available.

### 3.9 Permissions Matrix (21 Permissions)

Every non-public, non-profile endpoint is protected by a permission check. The Administrator role (role id=1) has all 21 permissions. Other roles get subsets.

| # | Permission Name | What It Grants | Example Protected Endpoints |
| :--- | :--- | :--- | :--- |
| 1 | `manage_users` | Create users, edit users, deactivate users, list roles, list permissions, view user permissions | GET /users, POST /users, PUT /users/:id, POST /users/:id/deactivate, GET /roles, GET /permissions, GET /users/:id/permissions |
| 2 | `manage_setup` | Create / edit / delete categories, item types, expense types | POST /categories, PUT /categories/:id, DELETE /categories/:id, POST /item-types, PUT /item-types/:id, DELETE /item-types/:id, POST /expense-types, PUT /expense-types/:id, DELETE /expense-types/:id |
| 3 | `manage_inventory` | Create / edit / delete individual inventory item rows (serialized assets) | GET /inventory-items, POST /inventory-items, PUT /inventory-items/:id, DELETE /inventory-items/:id, GET /reports/inventory |
| 4 | `manage_clients` | Add new clients, edit clients, delete clients | POST /clients, PUT /clients/:id, DELETE /clients/:id |
| 5 | `view_clients` | Read-only access to client list and client detail | GET /clients, GET /clients/:id |
| 6 | `create_bookings` | Create new bookings (quotations / drafts) | POST /bookings |
| 7 | `edit_bookings` | Update an existing booking, change its status, regenerate customer confirmation token | PUT /bookings/:id, POST /bookings/:id/status, POST /bookings/:id/regenerate_token |
| 8 | `cancel_bookings` | Cancel an existing booking (transitions to Canceled) | POST /bookings/:id/cancel |
| 9 | `view_bookings` | List bookings, view booking detail, download invoice, list payments | GET /bookings, GET /bookings/:id, GET /bookings/:id/invoice, GET /payments |
| 10 | `record_payments` | Record new payment against a booking; delete a payment record | POST /payments, DELETE /payments/:id |
| 11 | `view_financials` | View financial reports, client statements, expenses reports | GET /reports/financial-summary, GET /reports/expenses, GET /reports/expenses/export/csv, GET /reports/client-statement/:id, GET /clients/:id/statement |
| 12 | `view_dj_rak` | View DJ RAK-specific line-item amounts on bookings and reports | Exposed alongside booking detail fields that carry dj_rak_amount (checked by UI layer on top of view_bookings permission) |
| 13 | `manage_expenses` | Create / edit / delete expense rows | POST /expenses, PUT /expenses/:id, DELETE /expenses/:id |
| 14 | `view_expenses` | List expense records | GET /expenses |
| 15 | `view_reports` | Access the bookings report (JSON + CSV) | GET /reports/bookings, GET /reports/bookings/export/csv |
| 16 | `view_calendar` | View booking calendar and calendar iCal downloads | GET /calendar, GET /calendar/download/:id |
| 17 | `view_dashboard` | View dashboard stats and recent activity widgets | GET /dashboard/stats, GET /dashboard/recent_activity |
| 18 | `send_whatsapp` | Permission to trigger outbound WhatsApp message links (used by web UI and future mobile share-to-WhatsApp actions) | Checked in UI when generating WhatsApp deep-links for booking status notifications |
| 19 | `view_audit_logs` | View the system audit log list | GET /audit-logs |
| 20 | `manage_settings` | Read and update system_settings table | GET /settings, PUT /settings |
| 21 | `override_inventory` | Create or update bookings even when requested item quantity exceeds computed available stock | Enabled implicitly inside POST /bookings and PUT /bookings/:id inventory check (skips quantity enforcement) |

**Role defaults (from schema seed data):**
- Administrator (id=1) → all 21 permissions.
- Booking User (id=2) → manage_inventory, manage_clients, view_clients, create_bookings, edit_bookings, view_bookings, record_payments, view_expenses, view_reports, view_calendar, view_dashboard, send_whatsapp.
- Finance User (id=3) → view_clients, view_bookings, record_payments, view_financials, view_dj_rak, view_expenses, view_reports, view_dashboard.
- Viewer (id=4) → view_clients, view_bookings, view_financials, view_expenses, view_reports, view_calendar, view_dashboard.

Endpoints with `perm: null` in the routes file require **only** a valid token (any authenticated user) — e.g. GET /categories, GET /item-types, GET /expense-types, GET /search, GET /profile, PUT /profile, POST /profile/password, POST /i18n/set.

### 3.10 Rate Limiting

The login endpoint `POST /auth/login` is **per-IP rate-limited** to **5 failed attempts per minute**. On the 6th failed attempt within a 60-second sliding window the server responds with HTTP 429:

```json
{
  "success": false,
  "message": "Too many login attempts. Please try again in 60 seconds.",
  "error_code": "too_many_login_attempts",
  "errors": null
}
```

with a response header `Retry-After: 60`. A successful login clears the throttle state for the IP. No other endpoints currently enforce rate limits.

---

## 4. ENDPOINTS REFERENCE

Grouped by resource.

### 4.1 Auth Group

#### 4.1.1 POST /auth/login

- **Permission:** Public.
- **Body Params:** `{username:string, password:string, device_name?:string}`
- **Success 200 Example:**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token_type": "Bearer",
    "access_token": "a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2",
    "expires_at": "2026-09-12 14:30:00",
    "user": { "id": 1, "name": "Admin", "username": "admin", "email": "a@a.com", "phone": "+966500000000", "role_id": 1, "role_name": "Administrator", "active": true, "permissions": [ "manage_users" ], "lang": "en" }
  },
  "pagination": null
}
```

- **Error 401 (wrong creds):**

```json
{
  "success": false,
  "message": "Invalid credentials",
  "error_code": "invalid_credentials",
  "errors": null
}
```

**cURL example:**

```bash
curl -X POST http://localhost/project/MS/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123","device_name":"Pixel 8 - Admin"}'
```

#### 4.1.2 POST /auth/logout

- **Permission:** Token only.
- **Body Params:** None (empty JSON object `{}` accepted, or no body).
- **Success 200:**

```json
{ "success": true, "message": "Logged out", "data": null, "pagination": null }
```

**cURL example:**

```bash
curl -X POST http://localhost/project/MS/api/v1/auth/logout \
  -H "Authorization: Bearer a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2" \
  -H "Content-Type: application/json"
```

#### 4.1.3 GET /auth/me

- **Permission:** Token only.
- **Query Params:** None.
- **Success 200:** Same shape as login response `user` object.

```json
{
  "success": true,
  "message": "Current user",
  "data": { "id": 1, "name": "Admin", "username": "admin", "email": "a@a.com", "phone": "+966500000000", "role_id": 1, "role_name": "Administrator", "active": true, "permissions": [ "manage_users", "..." ], "lang": "en" },
  "pagination": null
}
```

#### 4.1.4 GET /profile

- **Permission:** Token only. Returns the same profile subset owned by the token user.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": { "id": 1, "name": "Admin", "username": "admin", "email": "a@a.com", "phone": "+966500000000", "role_id": 1, "role_name": "Administrator" },
  "pagination": null
}
```

#### 4.1.5 PUT /profile

- **Permission:** Token only. Update own profile.
- **Body Params:** `{name?:string, email?:string, phone?:string}`.
- **Success 200:** `{ "success": true, "message": "Profile updated", "data": null, "pagination": null }`
- **Error 422:** `error_code=validation_error` with per-field errors.

#### 4.1.6 POST /profile/password

- **Permission:** Token only. Change own password.
- **Body Params:** `{current_password:string, new_password:string, confirm_password:string}`. `new_password` must match `confirm_password` and be at least 8 characters. Server verifies `current_password` via password_verify before updating.
- **Success 200:** `{ success:true, message:"Password updated", data:null, pagination:null }`
- **Error 422 example:**

```json
{
  "success": false,
  "message": "Validation failed",
  "error_code": "validation_error",
  "errors": { "new_password": ["Password must be at least 8 characters"] }
}
```

### 4.2 Dashboard Group

#### 4.2.1 GET /dashboard/stats

- **Permission:** `view_dashboard`.
- **Query Params:** `?date_from`, `?date_to` (optional, default to current month).
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "total_bookings": 42,
    "total_quoted_amount": 125000.00,
    "total_collected": 95000.00,
    "total_pending": 30000.00,
    "total_expenses": 18500.00,
    "net_income": 76500.00,
    "collection_pct": 76.00,
    "avg_booking_value": 2976.19,
    "by_status": { "Draft": 5, "Quotation": 10, "Confirmed": 22, "Event Completed": 4, "Closed": 1, "Canceled": 3 }
  },
  "pagination": null
}
```

#### 4.2.2 GET /dashboard/recent_activity

- **Permission:** `view_dashboard`.
- **Query Params:** `?limit=10` (default 10, max 50).
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 9172, "action": "booking_created", "entity_type": "booking", "entity_id": 120, "user_name": "Admin", "created_at": "2026-09-05 11:45:00" },
    { "id": 9171, "action": "logged_in", "entity_type": "User", "entity_id": 1, "user_name": "Admin", "created_at": "2026-09-05 08:00:00" }
  ],
  "pagination": null
}
```

### 4.3 Bookings Group

#### 4.3.1 GET /bookings

- **Permission:** `view_bookings`.
- **Query Params:** `?page`, `?per_page`, `?status`, `?client_id`, `?date_from`, `?date_to`, `?q`.
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 120, "booking_number": "BK-20260905-001", "client_id": 3, "client_name": "ABC Events", "client_phone": "0501234567",
      "date_from": "2026-09-15", "date_to": "2026-09-16", "status": "Confirmed", "payment_status": "Partially Collected",
      "quoted_amount": 5000.00, "dj_rak_amount": 500.00, "location": "Riyadh Front" }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 1, "total_pages": 1 }
}
```

#### 4.3.2 GET /bookings/:id

- **Permission:** `view_bookings`.
- **Path:** `:id` integer booking id.
- **Success 200:** Full booking with items, payments, totals, invoice_url.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 120, "booking_number": "BK-20260905-001", "client_id": 3, "client_name": "ABC Events",
    "date_from": "2026-09-15", "date_to": "2026-09-16", "event_start_time": "18:00:00", "event_end_time": "02:00:00",
    "location": "Riyadh Front", "status": "Confirmed", "payment_status": "Partially Collected",
    "quoted_amount": 5000.00, "dj_rak_amount": 500.00,
    "customer_confirmation_token": "abcdef1234567890abcdef1234567890",
    "customer_response": "Confirmed", "customer_confirmed_at": "2026-09-04 10:00:00",
    "items": [
      { "id": 500, "item_type_id": 1, "item_type_name": "JBL PRX812W", "category_name": "Speakers", "quantity": 4, "rental_value": 500.00 }
    ],
    "payments": [
      { "id": 77, "payment_date": "2026-09-05", "amount": 2500.00, "payment_method": "Bank Transfer", "reference": "TRX-12345" }
    ],
    "invoice_url": "http://localhost/project/MS/invoice.php?id=120",
    "totals": { "quoted_amount": 5000.00, "dj_rak_amount": 500.00, "collected": 2500.00, "pending": 2500.00 }
  },
  "pagination": null
}
```

**cURL example:**

```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://localhost/project/MS/api/v1/bookings/120"
```

#### 4.3.3 POST /bookings

- **Permission:** `create_bookings`.
- **Body Params:**

```json
{
  "client_id": 3,
  "date_from": "2026-09-20",
  "date_to": "2026-09-21",
  "location": "Al Faisaliah Tower",
  "event_start_time": "19:00:00",
  "event_end_time": "03:00:00",
  "quoted_amount": 4800.00,
  "dj_rak_amount": 400.00,
  "status": "Draft",
  "internal_notes": "VIP wedding - double-check mics",
  "items": [
    { "item_type_id": 1, "quantity": 4, "rental_value": 500.00 },
    { "item_type_id": 3, "quantity": 1, "rental_value": 800.00 }
  ]
}
```

- `client_id`, `date_from`, `date_to`, `quoted_amount` required.
- `items[]` array: each item needs `item_type_id`, `quantity` (>=1), `rental_value`.
- Stock availability is enforced per item type unless caller holds `override_inventory` permission.
- **Success 201:**

```json
{ "success": true, "message": "Booking created", "data": { "id": 121, "booking_number": "BK-20260905-002" }, "pagination": null }
```

**cURL example:**

```bash
curl -X POST http://localhost/project/MS/api/v1/bookings \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 3,
    "date_from": "2026-09-20",
    "date_to": "2026-09-21",
    "location": "Al Faisaliah Tower",
    "quoted_amount": 4800,
    "items": [{"item_type_id":1,"quantity":2,"rental_value":500}]
  }'
```

#### 4.3.4 PUT /bookings/:id

- **Permission:** `edit_bookings`.
- **Body Params:** Any subset of the POST /bookings fields (client_id, date_from, date_to, quoted_amount, location, dj_rak_amount, event_start_time, event_end_time, status, internal_notes, items). If `items` key is sent it completely **replaces** the existing booking_items rows for this booking.
- **Success 200:** `{ "success": true, "message": "Booking updated", "data": null, "pagination": null }`

#### 4.3.5 POST /bookings/:id/cancel

- **Permission:** `cancel_bookings`.
- **Body:** Empty `{}`.
- **Success 200:** `{ "success": true, "message": "Booking canceled", "data": null, "pagination": null }`. Transitions status to `Canceled`.

#### 4.3.6 POST /bookings/:id/status

- **Permission:** `edit_bookings`.
- **Body:** `{ "to": "Confirmed" }` — allowed values: `Draft`, `Quotation`, `Confirmed`, `Change Requested`, `Event Completed`, `Closed`. To cancel, use the cancel endpoint instead.
- **Success 200:** `{ "success": true, "message": "Booking status updated", "data": null, "pagination": null }`

#### 4.3.7 POST /bookings/:id/regenerate_token

- **Permission:** `edit_bookings`.
- **Body:** Empty `{}`.
- **Success 200:**

```json
{
  "success": true,
  "message": "Confirmation token regenerated",
  "data": {
    "confirmation_token": "abcdef1234567890abcdef1234567890",
    "confirmation_url": "http://localhost/project/MS/confirm.php?token=abcdef1234567890abcdef1234567890"
  },
  "pagination": null
}
```

#### 4.3.8 GET /bookings/:id/invoice

- **Permission:** `view_bookings`.
- **Success 200:** Full invoice payload including company, client, line items, payments, and computed totals.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "company": { "company_name": "DJ RAK Entertainment", "address": "Riyadh, SA", "phone": "+966 50 000 0000", "tax_id": "", "currency": "JOD" },
    "booking": { "id": 120, "booking_number": "BK-20260905-001", "...": "..." },
    "client": { "id": 3, "name": "ABC Events", "phone": "0501234567", "alt_phone": "", "email": "", "address": "" },
    "items": [ { "id": 500, "item_type_name": "JBL PRX812W", "quantity": 4, "rental_value": 500.00 } ],
    "payments": [ { "id": 77, "payment_date": "2026-09-05", "amount": 2500.00, "payment_method": "Bank Transfer" } ],
    "totals": { "quoted_amount": 5000.00, "collected_amount": 2500.00, "pending_amount": 2500.00, "dj_rak_amount": 500.00, "items_subtotal": 2000.00 }
  },
  "pagination": null
}
```

### 4.4 Calendar

#### 4.4.1 GET /calendar

- **Permission:** `view_calendar`.
- **Query Params:** `?start`, `?end` (optional YYYY-MM-DD range, default current month).
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 120, "title": "ABC Events - BK-20260905-001", "start": "2026-09-15", "end": "2026-09-16", "status": "Confirmed", "client_name": "ABC Events", "client_phone": "0501234567", "location": "Riyadh Front", "color": "#16a34a" }
  ],
  "pagination": null
}
```

#### 4.4.2 GET /calendar/download/:id

- **Permission:** `view_calendar`. Returns a single booking formatted as iCalendar JSON payload for the mobile app to serialize into a `.ics` attachment.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "mime": "text/calendar",
    "filename": "booking-120.ics",
    "base64_content": "QkVHSU46VkNBTEVOREFSDQpWRVJTSU9OOjIuMA0KQkVHSU46VkVWRU5UDQpTVU1NQVJZOkJBQyBFdmVudHMgLSBCSy0yMDI2MDkwNS0wMDENCkRUU1RBUlQ6MjAyNjA5MTVUMTgwMDAwDQpERU5EOjIwMjYwOTE2VDAyMDAwMA0KRU5EOlZFVkVOVA0KRU5EOlZDQUxFTkRBUg=="
  },
  "pagination": null
}
```

Base64-decode, write to device storage, then open with calendar intent.

### 4.5 Clients

#### 4.5.1 GET /clients

- **Permission:** `view_clients`.
- **Query Params:** `?page`, `?per_page`, `?q` (searches name/phone/email), `?active=1`.
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 3, "name": "ABC Events Management", "phone": "0501234567", "alt_phone": "0507654321", "email": "contact@abcevents.com", "address": "Riyadh", "active": true }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 5, "total_pages": 1 }
}
```

#### 4.5.2 GET /clients/:id

- **Permission:** `view_clients`.
- **Success 200:** Single client record with summary booking totals.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 3, "name": "ABC Events", "phone": "0501234567", "alt_phone": "", "email": "c@abcevents.com",
    "address": "Riyadh", "notes": "Regular client", "active": true, "created_at": "2026-01-10 09:00:00"
  },
  "pagination": null
}
```

#### 4.5.3 POST /clients

- **Permission:** `manage_clients`.
- **Body:**

```json
{
  "name": "New Client LLC",
  "phone": "0511112222",
  "alt_phone": "0112223333",
  "email": "billing@newclient.com",
  "address": "Khobar, Saudi Arabia",
  "notes": "Corporate client"
}
```

- Required: `name`, `phone`.
- **Success 201:** `{ "success": true, "message": "Client created", "data": { "id": 20 }, "pagination": null }`

**cURL example:**

```bash
curl -X POST http://localhost/project/MS/api/v1/clients \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"New Client LLC","phone":"0511112222","email":"b@n.com"}'
```

#### 4.5.4 PUT /clients/:id

- **Permission:** `manage_clients`. Same fields as POST.
- **Success 200:** `{ "success": true, "message": "Client updated", "data": null, "pagination": null }`

#### 4.5.5 DELETE /clients/:id

- **Permission:** `manage_clients`. Soft deletes via setting `active = 0` (implementation detail).
- **Success 200:** `{ "success": true, "message": "Client deleted", "data": null, "pagination": null }`

#### 4.5.6 GET /clients/:id/statement

- **Permission:** `view_financials`.
- **Query Params:** `?date_from`, `?date_to` (optional).
- **Success 200:** Same payload shape as `GET /reports/client-statement/:id`.

### 4.6 Inventory

#### 4.6.1 GET /categories

- **Permission:** Token only.
- **Query Params:** `?active=1` (optional).
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 1, "name": "Speakers", "description": "Main PA", "active": true }
  ],
  "pagination": null
}
```

#### 4.6.2 POST /categories

- **Permission:** `manage_setup`.
- **Body:** `{ "name": "Lasers", "description": "Stage lasers", "active": true }`. `name` required, unique.
- **Success 201:** `{ "success": true, "message": "Category created", "data": { "id": 11 }, "pagination": null }`

#### 4.6.3 PUT /categories/:id

- **Permission:** `manage_setup`. Same fields.
- **Success 200:** `{ "success": true, "message": "Category updated", "data": null, "pagination": null }`

#### 4.6.4 DELETE /categories/:id

- **Permission:** `manage_setup`.
- **Error 409:** If any item_type references this category (FK block by MySQL).
- **Success 200:** `{ "success": true, "message": "Category deleted", "data": null, "pagination": null }`

#### 4.6.5 GET /item-types

- **Permission:** Token only.
- **Query Params:** `?category_id`, `?active=1`, `?q`.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 1, "category_id": 1, "name": "JBL PRX812W", "description": "12-inch 2-way", "default_rental_value": 500.00, "quantity": 4, "active": true }
  ],
  "pagination": null
}
```

#### 4.6.6 POST /item-types

- **Permission:** `manage_setup`.
- **Body:** `{ "category_id": 1, "name": "New Speaker", "description": "Pro speaker", "default_rental_value": 600, "quantity": 2, "active": true }`.
- Required: `category_id`, `name`, `default_rental_value`, `quantity`.
- **Success 201:** `{ data: { id: 17 } }`

#### 4.6.7 PUT /item-types/:id

- **Permission:** `manage_setup`. Same fields.
- **Success 200.**

#### 4.6.8 DELETE /item-types/:id

- **Permission:** `manage_setup`.
- **Error 409** if referenced by existing booking_items or inventory_items.
- **Success 200.**

#### 4.6.9 GET /item-types/:id/availability

- **Permission:** Token only.
- **Query Params:** `?date_from=YYYY-MM-DD&?date_to=YYYY-MM-DD` (both required).
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "item_type_id": 1,
    "total_quantity": 4,
    "booked_quantity": 1,
    "available_quantity": 3,
    "date_from": "2026-09-15",
    "date_to": "2026-09-16"
  },
  "pagination": null
}
```

#### 4.6.10 GET /availability/item-types (bulk)

- **Permission:** Token only.
- **Query Params:** `?ids=1,2,3` (comma-separated list of item_type ids), `?date_from`, `?date_to`.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "item_type_id": 1, "available_quantity": 3 },
    { "item_type_id": 2, "available_quantity": 0 }
  ],
  "pagination": null
}
```

#### 4.6.11 GET /inventory-items

- **Permission:** `manage_inventory`.
- **Query Params:** `?page`, `?per_page`, `?item_type_id`, `?status`, `?q` (searches serial_number, asset_code).
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 1, "item_type_id": 1, "serial_number": "SN-ABC-123", "asset_code": "AST-0001", "purchase_date": "2025-06-01",
      "status": "Available", "location": "Warehouse A", "notes": "Firmware v2.1" }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 30, "total_pages": 2 }
}
```

#### 4.6.12 POST /inventory-items

- **Permission:** `manage_inventory`.
- **Body:** `{ "item_type_id": 1, "serial_number": "SN-NEW", "asset_code": "AST-NEW", "purchase_date": "2026-09-01", "status": "Available", "location": "Warehouse A", "notes": "" }`.
- Required: `item_type_id`.
- **Success 201:** `{ "data": { "id": 31 } }`

#### 4.6.13 PUT /inventory-items/:id

- **Permission:** `manage_inventory`.
- **Success 200.**

#### 4.6.14 DELETE /inventory-items/:id

- **Permission:** `manage_inventory`.
- **Success 200.**

### 4.7 Expenses

#### 4.7.1 GET /expense-types

- **Permission:** Token only.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 1, "name": "Transportation", "fixed_value": 0.00, "description": "Vehicle fuel & rental", "active": true }
  ],
  "pagination": null
}
```

#### 4.7.2 POST /expense-types

- **Permission:** `manage_setup`.
- **Body:** `{ "name": "Cleaning", "fixed_value": 0, "description": "Post-event cleaning", "active": true }`.
- Required: `name`.
- **Success 201.**

#### 4.7.3 PUT /expense-types/:id

- **Permission:** `manage_setup`. **Success 200.**

#### 4.7.4 DELETE /expense-types/:id

- **Permission:** `manage_setup`. **Success 200.**

#### 4.7.5 GET /expenses

- **Permission:** `view_expenses`.
- **Query Params:** `?page`, `?per_page`, `?date_from`, `?date_to`, `?type_id`, `?booking_id`.
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 5, "type_id": 1, "type_name": "Transportation", "date": "2026-09-03", "amount": 350.00,
      "payment_method": "Cash", "reference": "RECEIPT-001", "booking_id": 118, "booking_number": "BK-20260830-009",
      "user_name": "Admin", "description": "Riyadh to Jeddah van rental", "notes": "" }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 22, "total_pages": 2 }
}
```

#### 4.7.6 POST /expenses

- **Permission:** `manage_expenses`.
- **Body:**

```json
{
  "expense_type_id": 1,
  "date": "2026-09-05",
  "amount": 200.00,
  "description": "Fuel for pickup truck",
  "payment_method": "Credit Card",
  "reference": "VISA-4321",
  "booking_id": null
}
```

Required: `expense_type_id`, `date`, `amount` (>= 0).
- **Success 201:** `{ data: { id: 23 } }`

#### 4.7.7 PUT /expenses/:id

- **Permission:** `manage_expenses`. Same fields. **Success 200.**

#### 4.7.8 DELETE /expenses/:id

- **Permission:** `manage_expenses`. **Success 200.**

### 4.8 Payments

#### 4.8.1 GET /payments

- **Permission:** `view_bookings`.
- **Query Params:** `?page`, `?per_page`, `?booking_id`, `?date_from`, `?date_to`, `?payment_method`.
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 77, "booking_id": 120, "booking_number": "BK-20260905-001",
      "payment_date": "2026-09-05", "amount": 2500.00, "payment_method": "Bank Transfer",
      "reference": "TRX-12345", "notes": "50% deposit", "created_by": 1, "user_name": "Admin" }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 1, "total_pages": 1 }
}
```

#### 4.8.2 POST /payments

- **Permission:** `record_payments`.
- **Body:**

```json
{
  "booking_id": 120,
  "payment_date": "2026-09-06",
  "amount": 2500.00,
  "payment_method": "Bank Transfer",
  "reference": "TRX-99887",
  "notes": "Final balance"
}
```

Required: `booking_id`, `payment_date`, `amount` (> 0).
After creation server auto-updates parent booking's `payment_status` (Not Collected / Partially Collected / Fully Collected).
- **Success 201:** `{ data: { id: 78 } }`

#### 4.8.3 DELETE /payments/:id

- **Permission:** `record_payments`.
- **Success 200:** `{ success:true, message:"Payment deleted", data:null, pagination:null }`. Parent booking payment_status recalculated.

### 4.9 Reports

All CSV export endpoints return a JSON envelope with Base64 content, **not** a raw binary download. On mobile: read `base64_content`, base64-decode it, write bytes to a file named by `filename` under app storage, then open with a CSV viewer intent.

#### 4.9.1 GET /reports/bookings

- **Permission:** `view_reports`.
- **Query Params:** `?page`, `?per_page`, `?date_from`, `?date_to`, `?status`, `?client_id`.
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "summary": {
      "total_count": 47,
      "total_booked_amount": 120000.00,
      "total_collected": 90000.00,
      "total_pending": 30000.00,
      "total_dj_rak": 8000.00,
      "avg_value": 2553.19,
      "collection_pct": 75.00
    },
    "rows": [
      { "id": 120, "booking_number": "BK-20260905-001", "client_name": "ABC Events",
        "date_from": "2026-09-15", "date_to": "2026-09-16", "status": "Confirmed",
        "payment_status": "Partially Collected", "quoted_amount": 5000.00, "collected": 2500.00 }
    ]
  },
  "pagination": { "page": 1, "per_page": 20, "total": 47, "total_pages": 3 }
}
```

#### 4.9.2 GET /reports/bookings/export/csv

- **Permission:** `view_reports`. Same filters as JSON. No pagination — returns all matching rows.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "mime": "text/csv",
    "filename": "bookings_report_20260905_143000.csv",
    "base64_content": "Qm9va2luZyBOdW1iZXIsQ2xpZW50LERhdGUgRnJvbSxEYXRlIFRvLFN0YXR1cyxQYXltZW50IFN0YXR1cyxRdW90ZWQgQW1vdW50LENvbGxlY3RlZCBBbW91bnQsUGVuZGluZyBBbW91bnQsREogUkFLIEFtb3VudCxDcmVhdGVkIEF0DQpCSy0yMDI2MDkwNS0wMDEsQUJDIEV2ZW50cywyMDI2LTA5LTE1LDIwMjYtMDktMTYsQ29uZmlybWVkLFBhcnRpYWxseSBDb2xsZWN0ZWQsNTAwMC4wMCwyNTAwLjAwLDI1MDAuMDAsNTAwLjAwLDIwMjYtMDktMDUgMTE6MDA6MDA=",
    "total_rows": 47
  },
  "pagination": null
}
```

**cURL example:**

```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://localhost/project/MS/api/v1/reports/bookings/export/csv?date_from=2026-09-01&date_to=2026-09-30"
```

#### 4.9.3 GET /reports/financial-summary

- **Permission:** `view_financials`.
- **Query Params:** `?date_from`, `?date_to` (default first day of month through today).
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "total_bookings_count": 12,
    "total_booked_amount": 30000.00,
    "total_collected": 24000.00,
    "total_pending": 6000.00,
    "total_expenses": 3500.00,
    "net_income": 20500.00,
    "collection_pct": 80.00,
    "total_dj_rak_amount": 2000.00,
    "avg_booking_value": 2500.00
  },
  "pagination": null
}
```

#### 4.9.4 GET /reports/expenses

- **Permission:** `view_financials`.
- **Query Params:** `?page`, `?per_page`, `?date_from`, `?date_to`, `?type_id`.
- **Success 200 (paginated):** `{ summary: { total_expense_count, total_expense_amount }, rows: [...] }`

#### 4.9.5 GET /reports/expenses/export/csv

- **Permission:** `view_financials`. Same filters. Base64 CSV envelope.
- **Success 200:** same shape as bookings CSV export.

#### 4.9.6 GET /reports/inventory

- **Permission:** `manage_inventory`.
- **Query Params:** none.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "summary": {
      "total_types": 20,
      "total_units": 120,
      "total_inventory_items": 90,
      "by_category": {
        "Speakers": { "types": 2, "units": 6 },
        "Subwoofers": { "types": 2, "units": 6 }
      },
      "by_status": {
        "Available": 75, "Booked": 8, "Out for Event": 3, "Maintenance": 2, "Damaged": 1, "Lost": 0, "Retired": 1
      }
    },
    "rows": [
      { "id": 1, "category_id": 1, "category_name": "Speakers", "name": "JBL PRX812W",
        "default_rental_value": 500.00, "quantity": 4, "active": true, "total_items": 4 }
    ]
  },
  "pagination": null
}
```

#### 4.9.7 GET /reports/client-statement/:id

- **Permission:** `view_financials`.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "client": { "id": 3, "name": "ABC Events", "phone": "0501234567", "..." : "..." },
    "summary": { "total_bookings": 10, "total_booked": 50000.00, "total_collected": 42000.00, "total_pending": 8000.00 },
    "bookings": [ { "id": 120, "booking_number": "BK-20260905-001", "date_from": "2026-09-15", "status": "Confirmed", "quoted_amount": 5000.00, "collected": 2500.00 } ],
    "payments": [ { "id": 77, "booking_id": 120, "booking_number": "BK-20260905-001", "payment_date": "2026-09-05", "amount": 2500.00 } ]
  },
  "pagination": null
}
```

### 4.10 Users

#### 4.10.1 GET /users

- **Permission:** `manage_users`.
- **Query Params:** `?page`, `?per_page`, `?role_id`, `?active`, `?q` (name/username/email/phone).
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 1, "name": "Admin", "username": "admin", "email": "a@a.com", "phone": "+966500000000",
      "role_id": 1, "role_name": "Administrator", "active": true, "last_login": "2026-09-05 08:00:00",
      "created_at": "2026-01-01 00:00:00" }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 3, "total_pages": 1 }
}
```

#### 4.10.2 GET /users/:id

- **Permission:** `manage_users`.
- **Success 200:** Single user record (same fields as list).

#### 4.10.3 POST /users

- **Permission:** `manage_users`.
- **Body:**

```json
{
  "name": "Sarah Bookings",
  "username": "sarah",
  "email": "sarah@djrak.com",
  "phone": "+966561112222",
  "role_id": 2,
  "password": "SecureP@ss123",
  "active": true
}
```

Required: `name`, `username`, `role_id`, `password` (>= 8 chars). `username` and `email` must be unique.
- **Success 201:** `{ data: { id: 5 } }`

#### 4.10.4 PUT /users/:id

- **Permission:** `manage_users`.
- **Body:** Any subset of `name`, `email`, `phone`, `role_id`, `active`, `password`. Omit `password` to leave unchanged.
- **Success 200.**

#### 4.10.5 POST /users/:id/deactivate

- **Permission:** `manage_users`.
- **Body:** `{}`. Sets `active = 0`. Existing tokens for this user remain valid until expiry; force a logout for immediate effect by additionally deleting all their rows from api_tokens.
- **Success 200:** `{ message: "User deactivated" }`

#### 4.10.6 GET /roles

- **Permission:** `manage_users`.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 1, "name": "Administrator", "description": "Full access" },
    { "id": 2, "name": "Booking User", "description": "Operational bookings access" }
  ],
  "pagination": null
}
```

#### 4.10.7 GET /permissions

- **Permission:** `manage_users`.
- **Success 200:** Array of all 21 permission names with descriptions (matches matrix §3.9).

#### 4.10.8 GET /users/:id/permissions

- **Permission:** `manage_users`.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "role_id": 2,
    "role_name": "Booking User",
    "permissions": [ "manage_inventory", "manage_clients", "view_clients", "create_bookings", "edit_bookings", "view_bookings", "record_payments", "view_expenses", "view_reports", "view_calendar", "view_dashboard", "send_whatsapp" ]
  },
  "pagination": null
}
```

### 4.11 Settings

#### 4.11.1 GET /settings

- **Permission:** `manage_settings`.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "company_name": "DJ RAK Entertainment",
    "company_phone": "+966 50 000 0000",
    "company_email": "info@djrak.com",
    "company_address": "Riyadh, Saudi Arabia",
    "currency_code": "JOD",
    "currency_symbol": "JOD",
    "date_format": "d/m/Y",
    "timezone": "Asia/Riyadh",
    "booking_prefix": "BK",
    "whatsapp_country_code": "966"
  },
  "pagination": null
}
```

#### 4.11.2 PUT /settings

- **Permission:** `manage_settings`.
- **Body:** JSON object with any subset of the keys returned by GET. Unknown keys are ignored.
- **Success 200:** `{ success:true, message:"Settings updated", data:null, pagination:null }`

### 4.12 Public (Booking Confirmation)

The two public endpoints do not require an Authorization header. They are intended to be accessed from customer-facing confirmation links (WhatsApp, email, SMS).

Idempotency guarantee: sending the same action twice has the same effect as sending it once. Already-confirmed bookings return HTTP 200 with an `Already confirmed` message and no database mutation.

#### 4.12.1 GET /public/confirm/:token

- **Permission:** Public.
- **Path:** `:token` is the 32-char hex `customer_confirmation_token` stored on the booking row.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "booking": { "id": 120, "booking_number": "BK-20260905-001", "date_from": "2026-09-15", "date_to": "2026-09-16", "location": "Riyadh Front", "quoted_amount": 5000.00, "status": "Quotation" },
    "client": { "name": "ABC Events", "phone": "0501234567", "email": "c@abcevents.com" },
    "items": [ { "item_type_name": "JBL PRX812W", "category_name": "Speakers", "quantity": 4, "rental_value": 500.00 } ],
    "company": { "company_name": "DJ RAK", "company_address": "Riyadh", "company_phone": "+966 50 000 0000", "currency": "JOD" },
    "totals": { "quoted_amount": 5000.00, "dj_rak_amount": 500.00, "collected_amount": 2500.00, "pending_amount": 2500.00 },
    "customer_response": null,
    "customer_confirmed_at": null
  },
  "pagination": null
}
```

- **Error 404:** token invalid or booking is Canceled.

#### 4.12.2 POST /public/confirm/:token

- **Permission:** Public.
- **Body:**

```json
{
  "action": "confirm",
  "change_details": null
}
```

- `action` is one of: `confirm`, `change`, `decline` (required).
- `change_details` — optional freeform object. If `action == "change"` clients should include a text note describing requested changes in `change_details.message`.
- **Effects:**
  - `confirm` → sets `customer_response = 'Confirmed'`, `customer_confirmed_at = NOW()`, and transitions booking status to `Confirmed` (unless already Event Completed / Closed / Canceled).
  - `change` → sets `customer_response = 'Change Requested'`, `customer_confirmed_at = NOW()`, transitions status to `Change Requested`.
  - `decline` → sets `customer_response = 'Declined'`, `customer_confirmed_at = NOW()`. Status unchanged.
- **Success 200:** Returns same payload shape as GET (with the new response fields populated).

### 4.13 Miscellaneous

#### 4.13.1 GET /search

- **Permission:** Token only. Global search across bookings, clients, item types, and inventory items.
- **Query Params:** `?q=BK` (minimum 2 characters).
- **Error 422:** `search_query_short` if `q` < 2 chars.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "query": "BK",
    "total": 12,
    "results": [
      { "type": "booking", "id": 120, "title": "BK-20260905-001", "subtitle": "ABC Events", "date_1": "2026-09-15", "status": "Confirmed" },
      { "type": "client", "id": 10, "title": "BK Events Co", "subtitle": "0509998888", "status": true },
      { "type": "item_type", "id": 15, "title": "Black Cable 10m", "subtitle": "Cables" },
      { "type": "inventory_item", "id": 42, "title": "SN-BK-007 - JBL PRX812W", "subtitle": "AST-0042", "status": "Available" }
    ]
  },
  "pagination": null
}
```

**cURL example (5th):**

```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://localhost/project/MS/api/v1/search?q=ABC"
```

#### 4.13.2 GET /audit-logs

- **Permission:** `view_audit_logs`.
- **Query Params:** `?page`, `?per_page`, `?user_id`, `?entity_type`, `?action`, `?date_from`, `?date_to`, `?q`.
- **Success 200 (paginated):**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 9172, "user_id": 1, "user_name": "Admin", "user_username": "admin",
      "action": "booking_created", "entity_type": "booking", "entity_id": 120,
      "old_value": null, "new_value": "{\"id\":120}",
      "ip_address": "127.0.0.1", "user_agent": "PostmanRuntime/7.0",
      "created_at": "2026-09-05 11:45:00" }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 200, "total_pages": 10 }
}
```

#### 4.13.3 GET /i18n/:lang

- **Permission:** Public.
- **Path:** `:lang` must be `en` or `ar`; invalid values fall back to `en`.
- **Success 200:**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "lang": "ar",
    "count": 420,
    "dictionary": {
      "Dashboard": "الرئيسية",
      "Bookings": "الحجوزات",
      "Confirm": "تأكيد",
      "Total_Amount": "المبلغ الإجمالي"
    }
  },
  "pagination": null
}
```

#### 4.13.4 POST /i18n/set

- **Permission:** Token only.
- **Body:** `{ "lang": "ar" }` — `en` or `ar`.
- **Success 200:** `{ success:true, message:"OK", data:{ current_lang:"ar" }, pagination:null }`
- **Error 422:** `invalid_lang_code` otherwise.

---

## 5. POSTMAN USAGE GUIDE

A full Postman collection and environment are maintained inside the repository. They let you explore every endpoint, run automated assertions, and auto-login.

### 5.1 Postman Files Location

- Collection: `docs/postman/DJ_RAK_API_v1.postman_collection.json`
- Environment: `docs/postman/DJ_RAK_API_v1.postman_environment.json`

If the `docs/postman` directory does not exist, create it and export the files from a working Postman setup using the instructions below, then commit them to the repo.

### 5.2 Import Steps

1. Launch the Postman desktop app.
2. Click **Import** in the top-left corner.
3. Drag and drop **both** JSON files into the Import dialog (collection first, environment second — order does not matter for import).
4. Click **Import**. Postman adds a new collection called `DJ RAK API v1` and an environment called `DJ RAK API v1`.

### 5.3 Configure Environment

1. Top-right corner: open the environment dropdown and select **DJ RAK API v1**.
2. Click the eye icon (Quick Look) or the pencil (Edit) for the environment.
3. Verify / edit the `baseUrl` variable:
   - Default (XAMPP local): `http://localhost/project/MS/api/v1`
   - Production: `https://your-host.com/project/MS/api/v1`
   - Without mod_rewrite: `http://localhost/project/MS/api/index.php/v1`
4. (Optional) Adjust `adminUsername` (default `admin`) and `adminPassword` (default `admin123`) if seed credentials differ.
5. Save the environment. Make sure values are saved as both **Initial Value** and **Current Value** (Postman toggles this on save).

### 5.4 Auto-Login via Pre-request Script

The `DJ RAK API v1` collection ships with a **Collection-level Pre-request Script** that automatically obtains a token when:

- The `token` environment variable is empty or unset, OR
- You explicitly cleared the `token` variable, OR
- A previous request returned HTTP 401 with `error_code = token_expired`.

The script performs:

```
POST {{baseUrl}}/auth/login
Body: { "username": "{{adminUsername}}", "password": "{{adminPassword}}", "device_name": "Postman Runner" }
```

and writes the returned `access_token` into the environment variable `token` (as current value). Every request then sends `Authorization: Bearer {{token}}` via a collection header preset.

To force a fresh login:

1. Edit the environment, clear the `token` current value, and Save.
2. Or open the collection → **Run** (Collection Runner) — the first request in the run will re-login if `token` is empty.

### 5.5 Manual Token Workflow (Alternative)

1. In the collection, open `Auth > POST /auth/login`.
2. Update the body with your username/password if needed, then click **Send**.
3. Copy the `access_token` from the response body.
4. Edit the environment → set `token` current value to the 64-char string (no quotes, no `Bearer ` prefix).
5. Save. Every subsequent request now inherits the token via `Authorization: Bearer {{token}}`.

### 5.6 Running the Collection (Runner)

1. Go to **Collections → DJ RAK API v1 → Run**.
2. In the Runner screen:
   - Select the **DJ RAK API v1** environment.
   - **Persist Variables** toggle: **turn ON** before running. This ensures variables set by earlier requests (e.g. `bookingId`, `clientId`, `invoiceId`) propagate to later requests.
   - Iterations: `1`. Delay: `0 ms`.
   - Order is preserved (folder order): Auth → Dashboard → Bookings → Calendar → Clients → Inventory → Expenses → Payments → Reports → Users → Settings → Public → Misc. Requests inside folders set Postman variables (e.g. `POST /bookings` writes `bookingId` from the created id, and the following `GET /bookings/{{bookingId}}` reads it).
3. Click **Run DJ RAK API v1**.

### 5.7 Automated Tests (pm.test assertions)

Every request includes Postman test scripts that assert:

- HTTP status code matches (sad-path requests assert 401 / 403 / 404 / 422 / 429).
- Response has `Content-Type: application/json`.
- Response is valid JSON.
- Response time < 2000 ms (fails on slow DB).
- `success` boolean present.
- Required fields present in `data` (e.g. bookings list must have pagination, login must have access_token with 64 chars, expires_at valid datetime, user permissions array length >= 1 for admin).
- For validation-error tests: `error_code == validation_error` and `errors` object is non-null.
- For public confirm sad paths: `error_code in {booking_not_found, booking_canceled}`.

Runner output shows each request with a green pass count / red fail count and a collapsible list of individual assertions. This makes the collection usable as a regression suite when deploying new API builds.

---

## 6. TROUBLESHOOTING GUIDE

### 6.1 401 Unauthenticated

**Symptoms:** Response `error_code` is one of `unauthenticated`, `token_invalid`, `token_expired`.

**Possible causes and fixes:**

1. You did not send an `Authorization` header. Add `Authorization: Bearer <access_token>`. Check for typos: the word is **Bearer** with one `r`.
2. Token was revoked by `POST /auth/logout` (the row is deleted from `api_tokens`). Re-login via `POST /auth/login`.
3. Token expired (`expires_at` datetime is in the past). Server returns `error_code=token_expired`. Re-login.
4. Token truncated or copied incorrectly. Login response's `access_token` is exactly 64 hex characters.
5. Database missing `api_tokens` table (see §6.10).

### 6.2 403 Forbidden (`error_code: forbidden`)

The token is valid, but the authenticated user's role does not include the permission required for the endpoint.

- Consult the Permissions Matrix in §3.9 to find which permission protects the endpoint.
- The **Administrator** role has all 21 permissions — if you are debugging, test with the default admin/admin123 user first.
- Grant the missing permission via `role_permissions` table, or use `POST /users` to assign the user a different role.

### 6.3 404 Not Found

1. **Wrong URL path:** Check the exact path in §4. Note the `/api/v1` prefix. `/bookings` (without /api/v1) is the web page, not the API.
2. **Typo in resource name:** e.g. `/booking/120` instead of `/bookings/120`.
3. **Resource does not exist:** e.g. client id 9999 was deleted. Check with GET list first.
4. **Apache mod_rewrite disabled:** XAMPP ships with mod_rewrite but sometimes it is off in httpd.conf. Use the fallback path: replace `/api/v1/auth/login` with `/api/index.php/v1/auth/login`. If the fallback works, enable `mod_rewrite` and verify `api/.htaccess` + `api/v1/.htaccess` are readable by Apache.

### 6.4 422 Unprocessable Entity

Inspect `errors` object in the response. Each key is a field name; each value is an array of English messages. Common issues:

- Required fields missing (the error will say *"X is required"*).
- Password / new_password shorter than 8 characters.
- `date_from` later than `date_to`.
- Booking item quantity 0 or negative.
- Nested `items` array entry missing `item_type_id` / `quantity` / `rental_value`. Per-item errors keyed by index string, e.g. `errors.items.0.quantity`.
- `action` in public confirm not in `confirm|change|decline` (this returns 400 `invalid_action` not 422, but still an input issue).

### 6.5 429 Too Many Requests

Only triggered by `POST /auth/login` after **5 failed attempts per IP per minute**.

- Wait the duration in the `Retry-After` response header (usually 60 seconds).
- If your integration script loops retries without backoff, add exponential backoff.
- Successful login resets the counter; use the correct credentials.
- In local testing, you can manually clear the throttle state by deleting the temp file referenced by `$GLOBALS['API_LOGIN_THROTTLE_FILE']` (usually under `sys_get_temp_dir()`).

### 6.6 CORS Errors on Mobile / WebView

The API emits permissive CORS headers from `response.php`:

- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Headers: Authorization,Content-Type,X-Requested-With`
- `Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS`
- Preflight `OPTIONS` requests return HTTP 204 with no body (handled in the front controller).

If you still see CORS in the browser / WebView:

1. Verify Apache modules **mod_headers** and **mod_rewrite** are enabled (XAMPP control panel → Apache → Config → httpd.conf, uncomment `LoadModule headers_module ...` and `LoadModule rewrite_module ...`, restart Apache).
2. Ensure the files `api/.htaccess` and `api/v1/.htaccess` exist and are readable. They duplicate the CORS headers via `Header set ...` in case PHP's headers get lost to an output-buffer flush.
3. On Android, WebView sometimes blocks requests from `file://` origins. Load the app content via `http(s)://` or use a local embedded WebView server.
4. iOS App Transport Security (ATS): for `http://localhost` in development, add `NSAllowsArbitraryLoads` or an `NSExceptionDomains` entry for `localhost` in Info.plist.

### 6.7 500 Internal Server Error

The API catches PDO exceptions in most endpoints, but some lower-level failures still bubble up as HTTP 500. Diagnose in order:

1. **MySQL server not running:** Open XAMPP Control Panel and start the MySQL module. Try to connect with `mysql -u root` from the command line.
2. **Wrong DB credentials in `config.php`:** Verify host/user/password/dbname in `includes/config.php` (or wherever the project stores DB credentials).
3. **Corrupt .htaccess rules:** Temporarily use the `/api/index.php/v1/...` fallback URL. If that works, the rewrite rules (not PHP) are the problem.
4. **PHP fatal errors:** Check Apache's `error_log` file. On XAMPP default install it's at `C:\xampp\apache\logs\error.log`. Tail it and reproduce the 500.
5. **DB user lacks permission:** Creating the `api_tokens` table lazily requires `CREATE TABLE` privilege (see §6.10).

### 6.8 JSON Parse Errors

Every POST/PUT request must include the header:

```
Content-Type: application/json
```

And the body must be valid JSON. Common mistakes:

- Trailing commas (`{"a":1,}`).
- Single quotes around strings (not valid JSON).
- PHP Warnings/Notices echoed before the JSON body. In production set `display_errors = Off` in `php.ini`.

If Postman works but your mobile code doesn't, log the raw bytes of the request and diff against a working cURL.

### 6.9 Missing /api_tokens Table

The first successful login auto-runs:

```sql
CREATE TABLE IF NOT EXISTS api_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  device_name VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  last_used_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_api_tokens_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

If your MySQL user lacks the `CREATE TABLE` privilege, the first login will fail with a 500-level PDOException. **Fix:** Append the SQL above by hand to the database (phpMyAdmin, HeidiSQL, or the MySQL CLI), or run:

```bash
mysql -u root -p dj_rak_system < database/append_api_tokens.sql
```

(Create `database/append_api_tokens.sql` containing the block above if it doesn't exist yet, or simply pipe it via `echo ... | mysql`.)

### 6.10 Dates in Wrong Timezone

The entire system assumes `Asia/Riyadh`. This is enforced at the top of `config.php` line 2 with:

```php
date_default_timezone_set('Asia/Riyadh');
```

If you see UTC or another timezone in `created_at` / `expires_at` values:

1. Do not modify the config line. It is deliberately hardcoded so that mobile clients can rely on the documented contract.
2. If you changed `php.ini`'s `date.timezone` globally, restart Apache after reverting. PHP's `date.timezone` INI setting is overridden by the explicit `date_default_timezone_set()` call, but only if that line actually executes.
3. If you are calling the API from a server that does NOT hit the front controller (e.g. a standalone PHP script), be sure to include the same bootstrap (config.php) so the timezone is configured.

---

## Quick Start (3 Steps)

**Step 1 — Import Postman files.**
Launch Postman, click Import, drop both `DJ_RAK_API_v1.postman_collection.json` and `DJ_RAK_API_v1.postman_environment.json` into the dialog (both files live under `docs/postman/` in the repo). Confirm the import.

**Step 2 — Configure environment.**
Top-right dropdown: select **DJ RAK API v1** → edit. Set `baseUrl` to your local XAMPP URL (default `http://localhost/project/MS/api/v1`; use the `/api/index.php/v1` fallback if mod_rewrite is disabled). Verify `adminUsername=admin` and `adminPassword=admin123`. Save with both Initial and Current values populated.

**Step 3 — Run the collection and watch tests go green.**
Go to **Collections → DJ RAK API v1 → Run**. Make sure the **DJ RAK API v1** environment is chosen and **Persist Variables** is ON. Click **Run**. The runner walks through Auth → Dashboard → Bookings → Calendar → Clients → Inventory → Expenses → Payments → Reports → Users → Settings → Public → Misc. Each request has pm.test() assertions. If everything is set up correctly, you will see a majority of green passes (the deliberately injected sad-path requests like wrong login also pass because they assert the correct error codes). Any red failures indicate a real integration issue to address.
