# DJ & RAK Mobile REST API Specification
================================

## 1. Problem, Users, Goals, and Non-Goals

### 1.1 Problem
The DJ & RAK Inventory & Rental Management System currently exposes all functionality via server-rendered HTML pages only. To build a native mobile app, all existing CRUD services, reports, booking confirmation, inventory, clients, payments, expenses, dashboard stats, user profile, and system-configuration endpoints must be exposed as a well-structured, versioned REST API that consumes and returns JSON only.

### 1.2 Primary Users
- **Mobile App Developer** (primary human user of the API): builds a mobile application to run the business from iOS/Android: login, dashboard, bookings, calendar, clients, inventory, payments, expenses, reports, user profile, settings, and calendar).
- **API consumers (secondary): system integrators building workflows.

### 1.3 Goals
- Deliver a JSON-only v1 REST API surface that exposes every business service currently implemented in the web UI.
- Mobile-token bearer-token authentication with token issuance and revocation (logout), token persistence (DB `api_tokens` lazy-table, token rotation, and scope=expiry).
- Consistent envelope for all endpoints respect the 20- permission model (role, permission names from schema.sql).
- Uniform response envelope with `success`, `message`, `data`, `pagination`, `errors`; consistent error codes; proper HTTP status codes (200, 201, 400, 401, 403, 404, 405, 409, 422, 500; consistent with problem plus.

### 1.4 Non-Goals
- No GraphQL or third-party OAuth social login, nor openid connect.
- No new business beyond what exists in web UI (stick to exact permission names, enum values, DB schemas — API-only feature parity, no new tables except API tokens table).
- No websockets, push notifications, or real-time calendar sync.
- No Swagger/OpenAPI 3.0 yaml (user prefers Postman).

## 2. Functional Requirements (FR)

### FR-1 API Architecture & Bootstrap
- **FR-1.1 Entry Point & Versioning: All API endpoints live under `/api/v1/... and return JSON with Content-Type: application/json; charset=utf-8 and emit X-Content-Type-Options: nosniff.
- **FR-1.2 Front Controller pattern: a single `api/index.php` (or `api/v1/index.php` rewrite-friendly) bootstrap loads config/db/functions, then routes by HTTP method + PATH_INFO path segments.
- **FR-1.3 API Token Auth scheme:
  - Login: `POST /api/v1/auth/login` accepts username+password returns `{username, password,device_name??}` → returns `{token_type:"Bearer", access_token:"..."expires_at`, user object}, and writes hashed or raw token in new `api_tokens` table (id, user_id, token (SHA256 or bcrypt hash, device_name, ip_address, user_agent, last_used_at, expires_at, created_at; PK id, INDEX user_id).
  - Token lookup: any authenticated endpoint requires `Authorization: Bearer <token>` header. If missing or invalid or expired → 401.
  - Logout: `POST /api/v1/auth/logout` deletes the current token row.
  - `POST /api/v1/auth/me` returns current authenticated user (id, name, username, email, phone, role_id, role_name, permissions array, last_login, active.
- **FR-1.4 Response envelope: Every response body structure:
  - Success 2xx: `{"success":true,"message":"...","data":<payload,"pagination":{page,per_page,total,total_pages} or null}`.
  - Error 4xx/5xx: `{"success":false,"message":"human","error_code":"...","errors":{"field":["msg"]} or null}`.

### FR-2 Dashboard & Aggregates
- `GET /api/v1/dashboard/stats?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD` returns dashboard KPIs identical to index.php dashboard: bookings, booked, quoted, collected, pending, dj_rak_amount, expenses, net, collection_pct, dj_rak_pct, confirmed, pending_events, canceled, total_clients, total_units, total_item_types, today_bookings_quantity, recent_bookings (array of 5 recent bookings by date. Also `GET /api/v1/dashboard/recent_activity` returns latest 20 audit_logs rows (paginated) with user, action, entity_type, entity_id, created_at.

### FR-3 Bookings
Full CRUD + sub-resources:
- `GET /api/v1/bookings?page=&per_page=&status=&payment_status=&client_id=&date_from=&date_to=&q= ` → paginated list, each row has booking_number, client relation {id,name,phone}, status, payment_status, date_from, date_to, quoted_amount, dj_rak_amount, collected, pending, created_by relation.
- `GET /api/v1/bookings/:id` → booking full detail with nested `items[] (with item_type relation, calculated collected sum, status badges, confirm_url, invoice_url) + payments[] relation.
- `POST /api/v1/bookings` → create booking (needs create_bookings) with items array, validate item_type_id existence, quantities, date_from <= date_to, quoted_amount >=0, dj_rak >=0. Return 201 on success.
- `PUT /api/v1/bookings/:id` → edit booking (edit_bookings) (same items rules, guard items upsert delete removed ones.
- `POST /api/v1/bookings/:id/cancel` → cancel booking cancel_bookings). Set status='Canceled' via hasPermission cancel_bookings.
- `POST /api/v1/bookings/:id/status` → body `{to:"Draft,Quotation,Confirmed,Event Completed,Closed"} → status change.
- `POST /api/v1/bookings/:id/regenerate_token` → reset customer_confirmation_token + regenerate. Return public confirmation URL.
- `GET /api/v1/bookings/:id/invoice` → return invoice view as HTML (optionally returns HTML), as view or data structure for mobile rendering.

### FR-4 Booking Confirmation Public Endpoint (public)
- `GET /api/v1/public/confirm/:token` → public booking info without auth. Returns booking, items[], company settings. 404 with invalid token.
- `POST /api/v1/public/confirm/:token` → public confirm/change/decline action. Body `{action:confirm|change|decline}`. Same idempotency rules confirm.php.
- 400 on unknown action. 200 with updated booking status.

### FR-5 Calendar
- `GET /api/v1/calendar?start=&end=` → array of booking calendarsimplified: booking_id, title (BK + client name, start (date_from), end date_to, status color).
- `GET /api/v1/calendar/download/:id` → ICS data as text/calendar base64 or direct URL in data or base64 or raw? Return JSON with `{ical_url} or `{ics_base64_content}.

### FR-6 Clients
Full CRUD: `GET /api/v1/clients?q=&page=&per_page=&active=`, `GET /api/v1/clients/:id`, `POST /api/v1/clients` (manage_clients), `PUT /api/v1/clients/:id` (manage_clients), `DELETE /api/v1/clients/:id` → set active=0 (soft). Also `GET /api/v1/clients/:id/statement` → client statement summary: bookings_summary{total_booked,total_collected,total_pending}, recent_payments[], recent_bookings[].

### FR-7 Inventory
- Categories `GET /api/v1/categories`, POST PUT DELETE (manage_setup).
- Item types: `GET/POST/PUT/DELETE /api/v1/item-types` (manage_setup). Also `GET /api/v1/item-types/:id/availability?date_from=&date_to=` → returns booked qty vs quantity available qty per item type.
- Inventory items: `GET/POST/PUT/DELETE /api/v1/inventory-items` (manage_inventory). List filter: status, item_type_id, q=, page=.

### FR-8 Expenses
- Expense types: CRUD /api/v1/expense-types (manage_setup).
- Expenses: `GET/POST/PUT/DELETE /api/v1/expenses` (view/manage_expenses). Filter date_from date_to expense_type_id booking_id q= page per_page.

### FR-9 Payments
- `GET /api/v1/payments?booking_id=&date_from=&date_to=&method=&q=&page=&per_page=`
- `POST /api/v1/payments` record_payments — booking_id, date, amount, method, reference, notes. Validate booking exists, amount>0, collected + amount <= quoted_amount + tiny margin unless override_inventory. payment_status recalculated.
- `DELETE /api/v1/payments/:id` → delete.

### FR-10 Reports
Protected by view_reports or view_financials permissions.
- `GET /api/v1/reports/bookings?date_from&date_to&status=&payment_status=&client_id=&user_id=` → bookings report aggregates + rows.
- `GET /api/v1/reports/financial-summary?date_from&date_to=` → totals booked, collected, pending, expenses, net, dj_rak_amount, collection_pct, avg_booking_value.
- `GET /api/v1/reports/expenses?date_from&date_to=&expense_type_id=` → expenses aggregates + rows.
- `GET /api/v1/reports/inventory?item_type_id=&status=` → inventory item count by status and aggregate.
- `GET /api/v1/reports/client-statement/:client_id?date_from&date_to=` → full statement rows + summary.
- Reports return `GET /api/v1/reports/bookings/export/csv` & /api/v1/reports/expenses/export/csv → return `{"download_url": "... or CSV content base64 encoded? Prefer base64 content + filename + mime type in response body data object `{mime,filename,base64}

### FR-11 Users / Roles / Permissions
(manage_users permission)
- `GET /api/v1/users?page=&per_page=&q=&active=`, `POST /api/v1/users`, `GET /api/v1/users/:id`, `PUT /api/v1/users/:id`, `POST /api/v1/users/:id/deactivate`.
- `GET /api/v1/roles` → list roles with permissions[] array.
- `GET /api/v1/permissions` → list all permissions.
- `GET /api/v1/users/:id/permissions` → array of permission names for user.

### FR-12 System Settings + Profile
- Settings: manage_settings: `GET /api/v1/settings`, `PUT /api/v1/settings` (object of key-value pairs.
- Profile: `GET /api/v1/profile` → own user's own profile (same as /auth/me including created date joined settings). `PUT /api/v1/profile` body {name, email phone}. `POST /api/v1/profile/password` {old,new,confirm}. old pw verify password_verify + bcrypt new >= 8, new == confirm. audit security event.

### FR-13 Search & Availability + Lookup
- `GET /api/v1/search?q=` → global search bookings booking client inventory combined results array.
- `GET /api/v1/availability/item-types?date_from=&date_to=&exclude_booking_id=` → array of item_type.id available qty. This mirrors ajax_availability.php logic.

### FR-14 Audit Logs (view_audit_logs)
- `GET /api/v1/audit-logs?page=&per_page=&action=&entity_type=&user_id=&date_from=&date_to=` paginated.

### FR-15 Localization (I18n)
- `GET /api/v1/i18n/:lang` → returns full translations dictionary en or ar.
- `POST /api/v1/i18n/set` → sets lang on token (or header `Accept-Language` also works or query param `?lang=en| ar per request, override token user has lang preference).

## 3. Non-Functional Requirements (NFR)

- **NFR-1 (Security)**
  - (A) All endpoints continue to use OWASP hardened: rate limits login 5/min per IP (forgot not; token endpoints 60/min auth per user; never return never; PII leaked; errors generic; DB queries use PDO prepared; never string interp; inputs validated server-side.
  - (B) Permission enforcement for every mutation endpoint using hasPermission() from existing PHP logic; same permission names from schema.sql.
  - (C) API password in api_tokens hash sha256 token storage; raw token returned only once (at issue time via login response).
  - (D) PDO prepared statements throughout; raw query parameter binding; never string interpolation for identifiers for identifiers.
- **NFR-2 Consistency): JSON `application/json charset utf-8 nosniff always.
- **NFR-3 Performance): Indexed where possible via existing indexes; list queries LIMIT/OFFSET paginate; max per_page= default 20, max 100.
- **NFR-4 Portability: PHP 7.3+ compatible; same host; no Composer new packages; pure procedural PHP following existing convention no framework).
- **NFR-5 Maintainability: Routes in a route map array; endpoint handlers in separate per-resource include files under api/v1/endpoints/... (auth,dashboard,bookings,calendar,clients,inventory,expenses,payments,reports,users,settings,profile,public,misc); shared helpers (response,auth,query,pagination, validation error_messages).
- **NFR-6 Testable via Postman: Postman Collection v2.1 exported JSON file in repo `docs/postman/DJ_RAK_API_v1.postman_collection.json` with variables:
  - Environment file `DJ_RAK_API.postman_environment.json baseUrl, token, admin_user, admin_pass clientId, bookingId vars.
  - Pre-request script for /auth/login sets {{token}} var.
  - pm.test() assertions for ALL endpoints: status code success true, ResponseTime < 2000, Content-Type JSON, required fields present.
  - 1 happy path for 1 example per list (list endpoints: 1x nested data array).
  - Example values for filters.

## 4. Constraints, Dependencies, Assumptions, Open Questions

### 4.1 Constraints
- Must use existing procedural pattern and global $conn.
- No new Composer/NPM packages.
- No changes to existing tables new except `api_tokens`.
- Existing UI code untouched (no controllers. APIs only adds new `/api/` subtree only.
- API must be HTTPS-aware (cookies/tokens secure flag over https.

### 4.2 Dependencies
- relies on PDO MySQL, functions.php, existing schema.sql 21 permissions, en/ar.php.

### 4.3 Assumptions
- Mobile app will send auth via Authorization header (no cookie sessions! (not; 99% stateless token approach.
- All dates will be accepted and returned as YYYY-MM-DD, datetime Y-m-d H:i:s in Asia timezone.
- Money returned in two-decimal float / strings? strings for safety with currency code.

### 4.4 Open Questions (User must resolve before approval.
N/A. We proceed under default assumption all FR deliver.

## 5. Acceptance Criteria
| ID   | Type | Rule Description |
|------|------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| AC-1 | rule | `POST /api/v1/auth/login` with correct admin/admin123 (from schema returns `success:true, access_token, user:{id:1} |
| AC-2 | rule | Any request to a protected endpoint without `Authorization: Bearer <correct_token` → HTTP 401 `success:false, error_code=unauthenticated or token_invalid |
| AC-3 | rule | request with token for user missing required permission returns HTTP 403 with audit_logs row. |
| AC-4 | rule | `api_tokens` table auto-created lazy; token_hashed stored using sha256; never returned raw token only issued (not the raw; SELECT lookup verifies SELECT COUNT EXISTS in tokens when user_id and hashed match). |
| AC-5 | rule | `GET /api/v1/dashboard/stats` returns bookings stats matching dashboard numbers when seeded data. |
| AC-6 | rule | Booking POST /bookings creates a booking in DB with items rows correctly; `GET /bookings/:id returns nested items payments. |
| AC-7 | rule | `POST /bookings/:id/cancel returns 200; bookings.status is 'Canceled'; cancel_bookings perm user without it 403. |
| AC-8 | rule | `POST /public/confirm/:valid_token with body action=confirm sets customer_confirmed_at and status Confirmed. Duplicate call is idempotent no audit invalid token 404. |
| AC-9 | rule | CSV export endpoints return `{mime,filename,base64}` and base64 decodes valid CSV. |
| AC-10 | rule | Users POST /users deactivate on a user → active=0. |
| AC-11 | rule | `POST /profile/password changes correctly password bcrypt hashes; old password wrong returns 422 errors:old. |
| AC-12 | rule | All endpoint responses obey envelope (success/message/data (+errors? Error  401, 403, 404, 422, 500 HTTP 200/201 status codes. |
| AC-13 | rule | I18n /i18n/en returns valid JSON dictionary; lang=ar returns Arabic. |
| AC-14 | rule | Postman collection JSON parseable valid v2.1 format; at least test per endpoint group; has environment file; pre-request auto login script. |
| AC-15 | rubric | API code quality / completeness; scoring scale 0-5: 1=1 2=partial, 3=good parity good coverage >80%, 4=excellent>=90%, = complete & well-structured route map; PASS threshold >=4/5. |
| AC-16 | rubric | Documentation in `API_DOCUMENTATION.md.md structure: intro, auth, pagination, errors, all endpoints with example request curl example, Postman usage instruction, permissions matrix, rate limits notes. PASS >=4/5. |
| AC-17 | rule | PHP syntax check passes for every new PHP file created count >= (api files). |
| AC-18 | rule | CORS headers for mobile app friendly: `Access-Control-Allow-Origin` * or configurable; `Access-Control-Allow-Headers Authorization,Content-Type,X-Requested-With; methods GET,POST,PUT,DELETE,OPTIONS; OPTIONS requests return 200 empty. |
| AC-19 | rule | Rate limit login endpoint: > failed attempts per minute ip returns 429 Too Many Requests. |
| AC-20 | rule | Logout POST /auth/logout deletes current api_tokens row; subsequent same token request 401. |
