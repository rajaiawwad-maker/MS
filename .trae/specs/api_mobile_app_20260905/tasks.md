# DJ & RAK REST API — Implementation Tasks
==========================================

**Status legend**: `pending`, `in_progress`, `blocked`, `completed`  
**PRs = pending tasks to be done during IMPLEMENT phase**

---

## Dependency Order Overview

1.  **Layer 0: Infrastructure (Task 1) — route bootstrap, tokens table, responses, auth, CORS, rate limit → prereq for ALL**
2.  **Layer 1: Auth Endpoints (Task 2)** — login/logout/me/profile/password — needed for Postman to auth
3.  **Layer 2: Domain CRUD Endpoints (Tasks 3-12)** — independent but require infra + auth helpers
4.  **Layer 3: Reports & Derived Endpoints (Task 13)** — rely on booking/payment data
5.  **Layer 4: Public Confirmation / Calendar / Misc (Tasks 14-15)**
6.  **Layer 5: Postman Collection + Docs (Tasks 16-17)** — require endpoints to exist

---

## Task 1: API Infrastructure Bootstrap (Route Dispatcher / Response / Auth / Tokens / CORS)

**Status**: pending  
**Priority**: high  
**Depends On**: none  
**Files to create (all under new `api/v1/` subfolder)**:
- `api/index.php` → minimal htaccess-compatible front controller that requires `api/v1/index.php`.
- `api/v1/index.php` → bootstrap (require config.php), route resolution via PATH_INFO + method, call handler, emit responses, OPTIONS handling, CORS, rate-limit, auth check for non-public routes
- `api/v1/includes/bootstrap.php` → shared require of config, helpers, defines API_ROOT
- `api/v1/includes/response.php` → `api_success($data, $message, $status, $pagination)`, `api_error($message, $error_code, $status, $errors)`, always emit JSON charset UTF-8 nosniff + CORS headers
- `api/v1/includes/auth.php` → `ensure_api_tokens_table()`, `issue_token($user_id,$device,$ip,$ua,$expirySecs)`, `authenticate_by_token()`, `currentApiUser()`, `revoke_current_token()`, `api_require_permission($permName)`. Hashed token storage with SHA256.
- `api/v1/includes/pagination.php` → `api_paginate($query,$params,$page,$per_page,$count_sql,$params_count)` returns `{data, pagination:{page,per_page,total,total_pages}}`
- `api/v1/includes/validation.php` → helpers: `required($fields,$data)`, `validate_strlen`, `validate_min8_password`, returns assoc errors[]
- `api/v1/includes/routes.php` → route map array `['METHOD /pattern'=>['HandlerClass|func','public?','perm?']]`

**Route patterns to implement in routes.php initially (handlers implemented in later tasks; at this task route map just wired, handlers can return 501 Not Implemented at Task 1 close):**
```
POST   /auth/login                     public
POST   /auth/logout                     auth
GET    /auth/me                         auth

GET    /dashboard/stats                 auth + view_dashboard
GET    /dashboard/recent_activity       auth + view_dashboard

GET    /bookings                        auth + view_bookings
GET    /bookings/:id                    auth + view_bookings
POST   /bookings                        auth + create_bookings
PUT    /bookings/:id                    auth + edit_bookings
POST   /bookings/:id/cancel             auth + cancel_bookings
POST   /bookings/:id/status             auth + edit_bookings
POST   /bookings/:id/regenerate_token   auth + edit_bookings
GET    /bookings/:id/invoice            auth + view_bookings

GET    /calendar                        auth + view_calendar
GET    /calendar/download/:id           auth + view_calendar

GET    /clients                         auth + view_clients
GET    /clients/:id                     auth + view_clients
POST   /clients                         auth + manage_clients
PUT    /clients/:id                     auth + manage_clients
DELETE /clients/:id                     auth + manage_clients
GET    /clients/:id/statement           auth + view_financials

GET    /categories                      auth
GET    /categories                      + manage_setup (write)
POST   /categories                      manage_setup
PUT    /categories/:id                  manage_setup
DELETE /categories/:id                  manage_setup

GET    /item-types                      auth
POST   /item-types                      manage_setup
PUT    /item-types/:id                  manage_setup
DELETE /item-types/:id                  manage_setup
GET    /item-types/:id/availability     auth
GET    /availability/item-types         auth

GET    /inventory-items                 auth + manage_inventory
POST   /inventory-items                 manage_inventory
PUT    /inventory-items/:id             manage_inventory
DELETE /inventory-items/:id             manage_inventory

GET    /expense-types                   auth
POST   /expense-types                   manage_setup
PUT    /expense-types/:id               manage_setup
DELETE /expense-types/:id               manage_setup

GET    /expenses                        auth + view_expenses
POST   /expenses                        manage_expenses
PUT    /expenses/:id                    manage_expenses
DELETE /expenses/:id                    manage_expenses

GET    /payments                        auth + view_bookings
POST   /payments                        record_payments
DELETE /payments/:id                    record_payments

GET    /reports/bookings                view_reports
GET    /reports/bookings/export/csv     view_reports
GET    /reports/financial-summary       view_financials
GET    /reports/expenses                view_financials
GET    /reports/expenses/export/csv     view_financials
GET    /reports/inventory               manage_inventory
GET    /reports/client-statement/:id    view_financials

GET    /users                           manage_users
GET    /users/:id                       manage_users
POST   /users                           manage_users
PUT    /users/:id                       manage_users
POST   /users/:id/deactivate            manage_users
GET    /roles                           manage_users
GET    /permissions                     manage_users

GET    /settings                        manage_settings
PUT    /settings                        manage_settings
GET    /profile                         auth
PUT    /profile                         auth
POST   /profile/password                auth

GET    /public/confirm/:token           public
POST   /public/confirm/:token           public

GET    /search                          auth (needs login only)
GET    /audit-logs                      view_audit_logs
GET    /i18n/:lang                      public
POST   /i18n/set                        auth
```

**Test Requirements (Task-1 TRs, self-verify):**
- **rule TR-1.1**: Browse `/api/v1/auth/login` returns 200 JSON (even if 405 for GET method or 422 for missing fields — returns JSON envelope).
- **rule TR-1.2**: Ensure `SHOW TABLES LIKE 'api_tokens'` returns 1 row after first call to auth/login route.
- **rule TR-1.3**: CORS: `curl -X OPTIONS /api/v1/auth/login` returns HTTP 200, empty body, `Access-Control-Allow-Origin`, `Allow-Headers`, `Allow-Methods` present.
- **rule TR-1.4**: Rate limiter: 6 rapid POST calls to `/api/v1/auth/login` with same IP within 60s must return 1+ HTTP 429 response at last call (or at call #6).
- **rubric TR-1.5**: Architecture quality; 0-5 scale. 0=mixed spaghetti, 3=clear route map and response helpers 5=immaculate structure with separate includes. PASS >=4.

---

## Task 2: Auth / Session / Profile Endpoints

**Status**: pending  
**Priority**: high  
**Depends On**: Task 1  
**Files to create**:
- `api/v1/endpoints/auth.php` → login, logout, me actions.
- `api/v1/endpoints/profile.php` → profile GET/PUT /password POST handler.

**Handler implementations:**
- **POST /auth/login**: accept username+password (device_name optional). throttle (existing enforce_login_throttle — reuse). success: issue token, return {access_token, token_type:"Bearer", expires_at, user:{id,name,username,email,phone,role_id,role_name,permissions:[]}}. failure: record_failed_login + 401.
- **POST /auth/logout**: revoke current token, 200 message.
- **GET /auth/me**: return user + permissions array + current lang.
- **GET /profile** → same user but separate handler.
- **PUT /profile** → update name, email, phone; validate email unique.
- **POST /profile/password** → old_password + new_password + confirm_password; min 8, new==confirm, old password hash check; update password_hash; auditSecurity password_changed via api audit.

**Test Requirements:**
- **rule TR-2.1**: Login POST with `{"username":"admin","password":"admin123"}` returns HTTP 200, success:true, access_token (length > 64 chars).
- **rule TR-2.2**: wrong password returns HTTP 401 success false + error_code invalid_credentials.
- **rule TR-2.3**: GET /auth/me with Bearer token returns user.id=1 (admin), role_name=Administrator, permissions array non-empty.
- **rule TR-2.4**: POST /auth/logout → success=true; subsequent /auth/me call with same token returns 401.
- **rule TR-2.5**: profile/password changes; new password < 8 returns 422 errors.new_password; wrong old password 422 errors.old_password.

---

## Task 3: Dashboard Endpoints

**Status**: pending  
**Priority**: high  
**Depends On**: Task 2  
**Files to create**: `api/v1/endpoints/dashboard.php`

**Handlers:**
- GET /dashboard/stats → exactly replicate index.php kpi queries into returned JSON data object with numbers + rates; accept date_from / date_to Y-m-d optional params.
- GET /dashboard/recent_activity → audit_logs left join users ORDER BY created_at DESC page per_page.

**Test Requirements:**
- **rule TR-3.1**: with seeded sample booking data present, stats returns bookings >=0, collected, pending >=0, array structure matches.
- **rule TR-3.2**: recent_activity returns rows with user relation (id, name, action, entity_type, created_at.

---

## Task 4: Bookings API

**Status**: pending  
**Priority**: high  
**Depends On**: Task 3  
**Files to create**: `api/v1/endpoints/bookings.php`

**Handlers:**
- GET /bookings (list filters, pagination, search). Perm view_bookings.
- GET /bookings/:id (detail with nested items[] (with item_type relation) and payments[]).
- POST /bookings (create with client_id, date_from, date_to, location, quoted_amount, dj_rak_amount, event_start_time, event_end_time, status?, internal_notes, items: [{item_type_id,quantity,rental_value}]. Validation: required fields, dates, item_type_id exists, qty >=1; quoted_amount sum of items or optional. Return 201 with id + booking_number. Generate booking_number automatically via generateBookingNumber(). Created_by = current user id.
- PUT /bookings/:id → same rules as POST; upsert booking_items DELETE old ones.
- POST /bookings/:id/cancel → set status Canceled.
- POST /bookings/:id/status body to= status progression.
- POST /bookings/:id/regenerate_token → generates new 32-byte random token, save, returns `{confirmation_token, confirmation_url}`.
- GET /bookings/:id/invoice → returns data object { company, booking, client, items, totals, quoted, collected, pending formatted}.

**Test Requirements:**
- **rule TR-4.1**: POST creates a booking returns 201 id booking_number.
- **rule TR-4.2**: GET /bookings/:id returns same id nested items quantity items with item_type_id.
- **rule TR-4.3**: Cancel POST status becomes Canceled.
- **rule TR-4.4**: Regenerate token returns confirmation_token length 64 hex chars (32 bytes → 64).

---

## Task 5: Calendar API

**Status**: pending  
**Priority**: high  
**Depends On**: Task 4  
**Files to create**: `api/v1/endpoints/calendar.php`

**Handlers:**
- GET /calendar → bookings start end date filter → return `[{id, title:BK + client, start:date_from + event_start_time?, end:date_to?, status, color:#hex}]`.
- GET /calendar/download/:id → returns JSON with {mime:"text/calendar", filename, ics_base64 or url. Simpler: returns ical_data string.

**Test Requirements:**
- **rule TR-5.1**: calendar returns array of at least >=0 entries; status per booking map.
- **rule TR-5.2**: /download/:id returns ical string data; contains BEGIN:VCALENDAR when decoded/base64 decoded.

---

## Task 6: Clients API + Statement

**Status**: pending  
**Priority**: medium  
**Depends On**: Task 2  
**Files to create**: `api/v1/endpoints/clients.php`

**Handlers:**
- CRUD GET /clients list filters active, q, page, per_page.
- GET /clients/:id full with array bookings summary.
- POST /clients create validate required name/phone active.
- PUT /clients/:id update.
- DELETE /clients/:id soft active=0.
- GET /clients/:id/statement → data {summary{booked,collected,pending}, recent_bookings[], recent_payments[]}.

**Test Requirements:**
- **rule TR-6.1**: /clients/:id statement returns numeric summary fields booked >=0.
- **rule TR-6.2**: delete returns 200 and then re-GET active=0.

---

## Task 7: Inventory (Categories / Item Types + Availability / Inventory Items)

**Status**: pending  
**Priority**: high  
**Depends On**: Task 2  
**Files to create**:
- `api/v1/endpoints/inventory.php` (categories, item-types, inventory-items, availability)

**Handlers:**
- CRUD categories manage_setup.
- CRUD item_types manage_setup.
- GET /item-types/:id/availability?date_from&date_to → data qty_booked vs qty_available.
- GET /availability/item-types → bulk list item types with qty avail.
- CRUD inventory_items manage_inventory.

**Test Requirements:**
- **rule TR-7.1**: POST category returns id.
- **rule TR-7.2**: availability returns integers >= 0 for seeded item type.
- **rule TR-7.3**: CRUD inventory_items returns status enum one of ('Available', 'Booked', etc.).

---

## Task 8: Expense Types + Expenses API

**Status**: pending  
**Priority**: medium  
**Depends On**: Task 2  
**Files to create**: `api/v1/endpoints/expenses.php`

**Handlers:**
- CRUD expense_types (manage_setup).
- GET expenses list filters date_from date_to type_id booking_id q page per.
- POST expenses (manage_expenses) type_id date amount description method ref booking_id optional reference, notes.
- PUT /:id, DELETE /:id.

**Test Requirements:**
- **rule TR-8.1**: POST expense creates row with created_by = current user id.
- **rule TR-8.2**: date filter returns rows within date range.

---

## Task 9: Payments API

**Status**: pending  
**Priority**: high  
**Depends On**: Task 4 (needs bookings)  
**Files to create**: `api/v1/endpoints/payments.php`

**Handlers:**
- GET /payments list.
- POST /payments record booking_id, date, amount (float>0), payment_method enum/references from t_payment_method, notes, reference. Validate booking existence and if amount will exceed quoted - prevent unless override_inventory.
- DELETE /payments/:id (only user own or admin, permission record_payments).

**Test Requirements:**
- **rule TR-9.1**: POST payment creates payment with booking_id, sets recalc status of booking payment_status after save.
- **rule TR-9.2**: DELETE payment soft remove or hard remove.

---

## Task 10: Users / Roles / Permissions API

**Status**: pending  
**Priority**: medium  
**Depends On**: Task 2  
**Files to create**: `api/v1/endpoints/users.php`

**Handlers:**
- GET users list.
- GET /users/:id detail with role relation.
- POST /users create name username email password role_id phone → server-side min pw 8.
- PUT /users/:id update (password optional if set → hash).
- POST /users/:id/deactivate → set active=0.
- GET /roles list with perms array nested perms.
- GET /permissions full list.
- GET /users/:id/permissions → array of strings.

**Test Requirements:**
- **rule TR-10.1**: create user with password <8 returns 422 validation error.
- **rule TR-10.2**: deactivate returns active 0.
- **rule TR-10.3**: /roles returns Administrator entry with permissions[] count == 21 (per schema).

---

## Task 11: Settings API

**Status**: pending  
**Priority**: low  
**Depends On**: Task 2  
**Files to create**: `api/v1/endpoints/settings.php`

**Handlers:**
- GET /settings → all key value pairs object from system_settings table.
- PUT /settings body object with pairs upsert via INSERT ON DUPLICATE KEY UPDATE; validate keys from whitelist or allow any with sanitize alphanumeric.

**Test Requirements:**
- **rule TR-11.1**: GET returns company_name string with length >=1.
- **rule TR-11.2**: PUT updates a new value and second GET reflects it.

---

## Task 12: Audit Logs & Global Search & i18n endpoints

**Status**: pending  
**Priority**: medium  
**Depends On**: Task 2  
**Files to create**: `api/v1/endpoints/misc.php`

**Handlers:**
- GET /audit-logs view_audit_logs perm paginated filters.
- GET /search?q → combined union search for bookings client, clients name phone, inventory serial asset, items names.
- GET /i18n/:lang returns dictionary (en.php ar.php content).
- POST /i18n/set (if future mobile user lang preference per token field). Currently just returns 200 ok message current lang.

**Test Requirements:**
- **rule TR-12.1**: search returns array with distinct entity types.
- **rule TR-12.2**: /i18n/en JSON parse success and contains at least 500 keys.

---

## Task 13: Reports Endpoints (Bookings, Financial, Expenses, Inventory, Client Statement + CSV)

**Status**: pending  
**Priority**: high  
**Depends On**: Tasks 4, 8, 9, 6 (booking data, payments, expenses, clients)  
**Files to create**: `api/v1/endpoints/reports.php`

**Handlers:**
- GET /reports/bookings → rows + summary: total_count, booked, collected, pending, dj_rak_amount (same reports_bookings.php logic).
- GET /reports/bookings/export/csv → JSON {mime:"text/csv", filename, base64_content}; same logic as CSV export.
- GET /reports/financial-summary date range → totals booked, collected, pending, expenses, net, collection_pct, dj_rak_amount, avg value.
- GET /reports/expenses rows + total; CSV export same pattern.
- GET /reports/inventory.
- GET /reports/client-statement/:id rows + summary; pagination? No simple.

**Test Requirements:**
- **rule TR-13.1**: financial-summary returns JSON numeric keys.
- **rule TR-13.2**: CSV export base64 decoded opens as valid CSV with header row.
- **rule TR-13.3**: client statement returns summary with numeric totals.

---

## Task 14: Public Confirmation Endpoint (no auth)

**Status**: pending  
**Priority**: high  
**Depends On**: Task 1 (routing) + Task 4 (bookings model)  
**Files to create**: `api/v1/endpoints/public.php`

**Handlers:**
- GET /public/confirm/:token → 404 if missing/invalid token; returns data: booking[], client company items[], status. Audit invalid token.
- POST /public/confirm/:token body {action:confirm|change|decline}. idempotency. Same rules as confirm.php. 400 on unknown action, 200 with updated status info.

**Test Requirements:**
- **rule TR-14.1**: Random invalid token returns 404 success false.
- **rule TR-14.2**: POST confirm on a booking sets customer_confirmed_at and status='Confirmed'. Two calls idempotent (no error, response same).
- **rule TR-14.3**: Change/Decline actions set correct response enum.

---

## Task 15: CORS + Rate Limiter (final polish + hardening) + PHP Syntax Check

**Status**: pending  
**Priority**: medium  
**Depends On**: Tasks 1-14  
**Files to create**:
- `api/.htaccess` → RewriteEngine On: RewriteCond %{REQUEST_FILENAME} !-f, RewriteCond %{REQUEST_FILENAME} !-d → RewriteRule ^(.*)$ index.php [QSA,L]

**Handler:**
- Tighten CORS: add header always Access-Control-Allow-Origin (dynamic origin if match list or just * for mobile flexibility).
- Login endpoint brute force 5 attempts / minute → 429.
- **Syntax Lint all new PHP files** — php -l all api/**/*.php.

**Test Requirements:**
- **rule TR-15.1**: Syntax check all PHP files zero errors.
- **rule TR-15.2**: /api/v1/ nonexistent route → JSON 404 {success:false,message:"Not found",error_code:"not_found"}.

---

## Task 16: Postman Collection v2.1 + Environment files

**Status**: pending  
**Priority**: high  
**Depends On**: Tasks 1-15 (all endpoints exist)  
**Files to create**:
- `docs/postman/DJ_RAK_API_v1.postman_collection.json` — v2.1 schema; 1 folder per resource (Auth, Dashboard, Bookings, Calendar, Clients, Inventory, Expenses, Payments, Users, Settings, Reports, Public/Misc) — each folder with happy-path and 1 sad path (validation).
- `docs/postman/DJ_RAK_API_v1.postman_environment.json` — variables: `baseUrl` (default http://localhost/project/MS/api/v1), `token`, `admin_user=admin`, `admin_pass=admin123`, `clientId`, `bookingId`, `itemTypeId`, `paymentId`, `userId`.
- **Pre-request Script at Collection Level**: check if {{token}} missing → auto-login POST to /auth/login and set token var.
- **pm.test() on every request**:
  - 1) HTTP status == expected status (e.g., 200, 201 create).
  - 2) Response body JSON parse success → pm.response.to.be.json.
  - 3) Response time < 2000 ms (or 3s for list endpoints 5k entries).
  - 4) Required fields present for resource (e.g., booking has id booking_number).
  - 5) For happy paths: `success === true`.
  - 6) For sad paths: `success === false` + HTTP status 4xx.

**Test Requirements:**
- **rule TR-16.1**: Both JSON files parse via json_last_error() === JSON_ERROR_NONE.
- **rule TR-16.2**: Collection info.schema matches Postman v2.1 schema URL.
- **rule TR-16.3**: Total requests in collection >= 50 (approximate 1+ per endpoint).
- **rubric TR-16.4**: Postman quality: organized folders, clean descriptions, environment file with all required variables, tests assertions meaningful (not just `pm.test("ok")`. PASS >=4/5.

---

## Task 17: API Documentation (API_DOCUMENTATION.md)

**Status**: pending  
**Priority**: high  
**Depends On**: Tasks 1-16  
**Files to create**:
- `API_DOCUMENTATION.md` — Root of project next to README or docs/ folder. Sections:
  - 1. Introduction. Base URL.
  - 2. Authentication (how to call login, get token, Authorization header, logout, token expiry, revoke.
  - 3. Conventions: Envelope, Errors Error Codes, HTTP Statuses, Pagination, Filtering, Localization headers/params, Dates & Times, Money formats, Permissions Matrix (table of endpoint to permission).
  - 4. API Reference: each endpoint, Method, URL, Permissions, Query Params, Body Schema Example, Success Response Example, Error Response Example, curl.
  - 5. Postman Usage: how to import collection + environment; run the collection; auth auto via pre-request.
  - 6. Troubleshooting: CORS, 401, 403, 422, 500.

**Test Requirements:**
- **rule TR-17.1**: File exists and length > 5KB.
- **rule TR-17.2**: Contains all 20+ endpoint URL patterns with HTTP method.
- **rule TR-17.3**: Has 5+ curl examples (login, booking list, create booking, cancel booking, report csv).
- **rubric TR-17.4**: Documentation quality; PASS >=4/5 (readability, examples, completeness).

---

## Review Phase Checklist (Independent)

Performed by independent reviewer in review.md.
- AC-1 AC-20 all re-checked with actual curl call or simulated PHP verification.
- Check security: no password in response, tokens hashed, errors generic, rate limit kicks in, permission 403 correct.
- Postman syntax JSON parse valid.
- Docs has all endpoints mentioned with method.
- PHP syntax check all files again, no errors.
