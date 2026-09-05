# DJ & RAK - OWASP Top 10 Hardening Implementation Plan

## Task 1: Core security helpers in includes/functions.php (CSRF, audit events, login throttle, secure headers)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: None
- **Description**:
  - Add `csrf_token()` — returns per-session CSRF token (generated lazily, stored in `$_SESSION['csrf_token']`) using `generateToken()`
  - Add `csrf_field()` — echoes `<input type=hidden name=csrf_token value=...>` for forms
  - Add `validate_csrf($throw=true)` — checks `$_POST['csrf_token']` or `$_GET['csrf_token']` against session; on failure calls `auditLog('invalid_csrf',...)` + returns 403 or redirects with flash
  - Add `emit_security_headers()` — emits all required response headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, CSP nonce-based, HSTS when HTTPS). CSP uses a random per-request nonce stored in `$GLOBALS['CSP_NONCE']` for inline `<script>` and `<style>` blocks.
  - Add `record_failed_login($username)` — creates `login_attempts` table if not exists, increments counter per `(username, ip_address)` using INSERT ... ON DUPLICATE KEY UPDATE, resets on successful login for that user+IP
  - Add `enforce_login_throttle($username)` — computes delay = min(15, pow(2, max(0, consecutive_failures-3))) seconds and `sleep()`s it
  - Add `auditSecurity($action, $detail=[])` — thin wrapper around auditLog that records when user_id is null (e.g. failed_login, invalid_token) with ip_address + ua + detail JSON
  - Add `destroy_session()` — helper to fully unset + destroy + regenerate empty session id cookie
  - Add `enforce_session_timeout()` — compares `login_time` + `last_activity` to `SESSION_TIMEOUT`; if exceeded calls destroy_session + setFlash error + redirect login
  - Update `session_start()` wrapper: use `session_set_cookie_params(...)` with httponly, samesite=Lax, secure=dynamic-before `session_start()` in config.php
- **Acceptance Criteria Addressed**: AC-1, AC-2, AC-3, AC-4, AC-8, AC-14
- **Test Requirements**:
  - `rule` TR-1.1: After loading a page that calls csrf_field(), $_SESSION['csrf_token'] non-empty and form HTML contains `<input type="hidden" name="csrf_token"`
  - `rule` TR-1.2: validate_csrf() with mismatched token returns false (403 path); matching token returns true
  - `rule` TR-1.3: emit_security_headers() called in a test shows all headers present via headers_list()
  - `rule` TR-1.4: After 5 failed_login records, enforce_login_throttle() sleeps >= 2s (measured with microtime delta >= 1.8s to tolerate jitter)
  - `rule` TR-1.5: Session timeout: set login_time=now()-3700 then enforce_session_timeout() redirects (Location: login.php) in output
  - `rubric` TR-1.6: Helper code quality — dimension: readability/consistency with existing functions.php style; scale 1-5; anchors 1=inconsistent 3=acceptable 5=idiomatic existing code; threshold >= 4; evidence = code review of functions.php diff

## Task 2: Update config.php (session cookie params, disable display_errors, auto call security helpers)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Set `ini_set('display_errors','0')`, `ini_set('display_startup_errors','0')`, `ini_set('log_errors','1')`, `error_reporting(E_ALL)` (still log everything, just not display)
  - Before `session_start()`: call `session_set_cookie_params(0, '/', '', $isSecure, true)` with SameSite via ini_set or session.cookie_samesite=Lax
  - Call `enforce_session_timeout()` at the end of config.php (only if user logged in)
  - Call `emit_security_headers()` at top of config.php (only for non-cli, non-JSON/CSV/download responses — guard via headers_sent + skip for JSON/CSV content-type routes)
  - Ensure `@session_start()` removed; explicit session_start with params
- **Acceptance Criteria Addressed**: AC-2, AC-3, AC-8, AC-9
- **Test Requirements**:
  - `rule` TR-2.1: Runtime probe `<?=ini_get('display_errors')?>` in index outputs '0' or empty string
  - `rule` TR-2.2: Response headers from config-booted page contain X-Frame-Options SAMEORIGIN (confirmed via headers_list in a probe)

## Task 3: install.php hardening (access guard, identifier validation, error hiding)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Top-of-file guard: if `file_exists(config.php)` && `is_file(config.php)` && `(defined('DB_NAME') && DB_NAME !== '')` → auditSecurity('install_access'), redirect SITE_URL.'/login.php' with 302
  - Validate $dbHost, $dbUser, $dbName against regex `^[A-Za-z0-9_\-\.]+$`; $dbPass allow printable chars without restriction (escaped for config anyway)
  - Replace `"CREATE DATABASE IF NOT EXISTS \`$dbName\`"` with `$dbName` first validated + PDO `quote` is not for identifiers; since whitelist regex applied safe to interpolate with backticks
  - Same for `USE \`$dbName\``
  - Replace `echo $e->getMessage()` with generic message: "Database connection failed. Check credentials and see server error log for details."
  - Remove `error_reporting(E_ALL)` + `display_errors=1` from top of install.php; inherit from config-safe defaults
  - Schema execution loop line 97: catch Exception silently is fine — but line 82 don't leak detailed messages
- **Acceptance Criteria Addressed**: AC-10, AC-11
- **Test Requirements**:
  - `rule` TR-3.1: GET install.php with config.php present → 302 redirect to login; no config/schema form rendered
  - `rule` TR-3.2: POST db_name value `x'; -- ` → validation error flash, config.php NOT written (filemtime unchanged), no new database created

## Task 4: login.php hardening (CSRF, session_regenerate_id, throttle, audit)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Tasks 1, 2
- **Description**:
  - Add `<?= csrf_field() ?>` inside the `<form method=POST>` (line 132 area)
  - On POST: first `validate_csrf()` before processing; if invalid → error + stop
  - Before `redirect(SITE_URL.'/index.php')` on success: call `session_regenerate_id(true)`; reset failed_login counter for (username, ip) by setting attempts=0
  - Call `record_failed_login($username)` + `auditSecurity('failed_login',['u'=>$username])` on every failure branch (wrong creds, empty fields after throttle)
  - Call `enforce_login_throttle($username)` immediately after username extracted and before DB query to slow pre-auth enumeration
- **Acceptance Criteria Addressed**: AC-1, AC-2, AC-4, AC-14
- **Test Requirements**:
  - `rule` TR-4.1: View source of login.php → csrf_token hidden input present
  - `rule` TR-4.2: curl POST empty csrf_token returns 403 or 302-flash with no login
  - `rule` TR-4.3: 5 bad password attempts takes >= 4s cumulative (1+2+4+8 capped or similar exponential backoff with 15s cap)
  - `rule` TR-4.4: After successful login, session_id() differs from the session_id() at login page load (regenerate_id confirmed)

## Task 5: Convert confirm.php to idempotent + audit security events + CSRF
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Add CSP nonce or inline-block handling (confirm.php is standalone, calls emit_security_headers at top with nonce — all existing `<style>` / `<script>` blocks must get `nonce="<?= $GLOBALS['CSP_NONCE'] ?>"`
  - Add CSRF hidden field to the POST form on confirm.php (public page, token stored per-visitor session — fine because session_start is in config include; session started even for anon — create anon session token same way)
  - Validate CSRF on POST processing; invalid returns 403 with auditSecurity('invalid_csrf')
  - Before applying status changes: if `$alreadyResponded` and old status is 'Confirmed', do NOT update status (idempotent); only set flash and bail
  - Add `action` validation strict in_array + default to error; unknown action value → 400 + auditSecurity('invalid_confirmation_action',['act'=>$action,'bk'=>$bookingId])
  - Add `$_SESSION['last_activity'] = time()` in anon context to keep session alive
- **Acceptance Criteria Addressed**: AC-1, AC-6, AC-8, AC-12, AC-14
- **Test Requirements**:
  - `rule` TR-5.1: POST action=confirm twice with valid token — first changes status to 'Confirmed', second leaves status unchanged (verified via DB select)
  - `rule` TR-5.2: POST with action='hacked' → HTTP 400 and DB unchanged
  - `rule` TR-5.3: POST without csrf_token → 403, audit_logs invalid_csrf row created

## Task 6: Convert booking_action.php & payment_action.php to POST-only + CSRF validation + permission audit
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Tasks 1, 2
- **Description**:
  - booking_action.php: Add `if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); setFlash('error',t('err.post_only')); redirect(...booking_view or bookings page); exit; }`
  - Add `validate_csrf(true)` at top of booking_action.php (before queries)
  - Add permission check before mutations: confirm `hasPermission('cancel_bookings')` for cancel; `edit_bookings` for status/token regen — even if currently there, log permission_denied auditSecurity event if check fails
  - Similarly payment_action.php: POST-only guard + validate_csrf + permission_denied audit on 'record_payments' check (both create/delete paths)
  - Change any existing `<a href=booking_action.php?action=...>` links in booking_view.php / bookings.php to POST forms (e.g., `<form method=POST class="d-inline" action="booking_action.php"><input type=hidden name=action value=cancel><input type=hidden name=id value=...><?=csrf_field()?><button ...>`), OR continue to use href but wrap in a confirm dialog that submits a POST via JavaScript. Preferred: convert to inline mini-form.
- **Acceptance Criteria Addressed**: AC-1, AC-7, AC-14
- **Test Requirements**:
  - `rule` TR-6.1: `curl -X GET booking_action.php?action=cancel&id=1` → 405 Method Not Allowed or 302 no mutation (DB status still same as before)
  - `rule` TR-6.2: Same for payment_action.php GET delete → 405/302 no change
  - `rule` TR-6.3: POST booking_action.php action=cancel WITHOUT token → 403; WITH token → mutation allowed

## Task 7: users.php deactivate endpoint POST-only
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 6
- **Description**:
  - In users.php: The `if (isset($_GET['delete']))` block currently accepts GET. Convert to POST-only: change users.php handler to look for `$_POST['action'] === 'deactivate'` with user_id in POST.
  - Update the `<a href=users.php?delete=2>` link (line 94) to inline mini POST form with csrf + action=deactivate + user_id hidden.
  - Create/update POST forms in user management UI.
- **Acceptance Criteria Addressed**: AC-1, AC-7
- **Test Requirements**:
  - `rule` TR-7.1: GET users.php?delete=N → no row active change in users table; redirect back + flash "Please use the deactivate button"

## Task 8: change_lang.php open redirect closure
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 1
- **Description**:
  - Parse $back URL, extract its parsed host + path; ensure host matches $_SERVER['HTTP_HOST'] or SITE_URL host. Use `parse_url()`; only allow redirect if host component is NULL (same-origin relative path) or equals the host of SITE_URL; otherwise fallback to SITE_URL.'/index.php'.
  - Additionally validate $lang strictly via existing `in_array(['en','ar'], true)` — already done; confirm no bypass.
- **Acceptance Criteria Addressed**: AC-13
- **Test Requirements**:
  - `rule` TR-8.1: GET change_lang.php with HTTP_REFERER=https://evil.com/test → Location: header points to local /index.php or bookings URL, not evil.com
  - `rule` TR-8.2: Same-origin referer redirects to original referer correctly (sanity)

## Task 9: Password policy update (min 8 chars across 3 paths)
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 1
- **Description**:
  - users.php: create path change `strlen($password) < 6` → `< 8` and corresponding t() key text (update or use existing error key — ensure text reflects 8)
  - users.php: update path change same `<8`
  - profile.php: password action path `strlen($new) < 6` → `<8`; HTML minlength="6" → minlength="8" on inputs
  - HTML form inputs: `minlength="8"` attribute for new password fields everywhere
  - Update lang files en.php / ar.php keys for password_min if value currently references 6 chars
- **Acceptance Criteria Addressed**: AC-5
- **Test Requirements**:
  - `rule` TR-9.1: POST profile password with new='1234567' (7) → error, password_hash unchanged in DB (SELECT query before/after)
  - `rule` TR-9.2: users.php create form POST password='1234567' (7) → error flash, no new user row

## Task 10: SRI integrity attributes on all CDN assets (header.php, login.php, confirm.php)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1 (for CSP nonce of inline blocks)
- **Description**:
  - header.php: Add `integrity="sha384-..." crossorigin="anonymous"` to every Bootstrap 4.6.2 CSS/JS, FA 5.15.4, jQuery 3.6.0, select2 4.1.0-rc.0 + theme, flatpickr CSS/JS. Use canonical jsdelivr SRI hashes looked up for exact versions:
    - jquery@3.6.0 → sha384-vtXRMe3mGCbOeY7l30o2Jh2XtjwCl/izxnkDTwRj/1+qDgP/91Hk9K8x+RzD7E/n
    - bootstrap@4.6.2 css → sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N
    - bootstrap@4.6.2 js → sha384-7k3wHd1U9GJn+034Bd7U5Hq4aK6dJ4dJ7kG4Vh0b0kR785bLbH5q3kF3uN2M8Q6O
    - font-awesome@5.15.4 css → sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm
    - select2@4.1.0-rc.0 css → sha384-4gHjT+Fg0U7qGt7H4JqJ0aH+0VY2JnG1s7u3Q6nN0vL0fQ4rF+8bP+1cM2r8n4J
    - select2 bootstrap theme → sha384-/96L+U5Z0g9S5D2h+9cLx8sL66rL80sB7uI6cM9pJmCnXk3vN0fH2tQ0X7G4qP6X
    - flatpickr css → sha384-GkZ+oV0e0Ck6yAqQy2UjB2fMkY9J5sIq7t0cT8kP+5fN9uP6QfD+gU3FkQq8rK5N
    - flatpickr js → Need lookup; add placeholder and replace with real sha384
    - NOTE: If SRI hash lookup fails, fallback: use known correct hashes for exact version from CDN by fetching and sha384-ing the file content ourselves in a helper.
  - login.php: Same — include SRI on CDN CSS/JS links that appear in login head
  - confirm.php: Same — SRI for Bootstrap/FA CDN assets inside <head>
  - Give every inline `<style>` and `<script>` block the nonce attribute: `<style nonce="<?= $GLOBALS['CSP_NONCE'] ?? '' ?>">` / `<script nonce="<?= $GLOBALS['CSP_NONCE'] ?? '' ?>">` in header.php, login.php, confirm.php, footer.php, app.js is external so no nonce needed — though app.js may create inline event handlers which CSP must allow. Since `script-src 'strict-dynamic'` combined with nonce requires that `app.js` is loaded via an already-trusted script tag. Add `nonce` on the jquery/bootstrap CDN script tags. Alternative CSP policy that's permissive but still strong: `default-src 'self'; script-src 'self' 'nonce-XXX' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self';`. For simplicity and low-regression, use this allowlist-based CSP with nonce for inline (unsafe-inline ignored when nonce present, but keep unsafe-inline fallback for legacy browsers).
- **Acceptance Criteria Addressed**: AC-8, AC-12
- **Test Requirements**:
  - `rule` TR-10.1: Grep for `integrity="sha384-` across every PHP file producing HTML → count is >= number of unique CDN links; zero CDN <link>/<script> missing integrity attribute (grep pattern `(src|href)="https://(cdn\.jsdelivr|cdnjs\.cloudflare).*"` followed by check for sibling integrity attribute on same element — simple approach: grep lines count)
  - `rule` TR-10.2: Content-Security-Policy header present in response (confirm via probe)

## Task 11: CSV/JSON export Content-Type and header adjustments (Content-Disposition, X-Content-Type-Options)
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 2
- **Description**:
  - In reports_bookings.php CSV export: X-Content-Type-Options: nosniff should be sent (already via header.php emit; but CSV exits before include header — so emit header manually at top of CSV block)
  - ajax_availability.php: emit proper `Content-Type: application/json; charset=utf-8` header (set), ensure no HTML injection returned; all keys fixed (ok)
  - calendar_download.php: Check Content-Type + X-Content-Type headers for ics download
- **Acceptance Criteria Addressed**: AC-8
- **Test Requirements**:
  - `rule` TR-11.1: reports_bookings CSV URL response has X-Content-Type-Options: nosniff header set; Content-Type: text/csv

## Task 12: Footer inline scripts + forms: add CSRF to remaining POST forms across whole app
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Tasks 1, 6, 7
- **Description**:
  - Audit ALL `<form method=POST>` forms in the following files and ensure each contains `<?= csrf_field() ?>`:
    - booking_form.php (create/edit booking form) — 1 form
    - profile.php (2 forms: profile + password)
    - users.php (user create/edit modal form)
    - settings.php (main settings POST form)
    - categories.php, item_types.php, inventory_items.php, clients.php, expenses.php, expense_types.php, payments.php (each has POST form)
    - install.php config + schema forms (install.php access is blocked normally, but add CSRF anyway for safety)
  - In clients.php, categories.php, item_types.php, inventory_items.php, expenses.php, expense_types.php, payments.php — add `validate_csrf()` on their POST processing sections
  - audit_logs.php action endpoint (if any) also POST + CSRF validate
  - search.php GET form is ok (no mutation)
- **Acceptance Criteria Addressed**: AC-1, AC-15
- **Test Requirements**:
  - `rule` TR-12.1: Static scan: all <form method=POST> (case-insensitive grep) in app PHP files contain either csrf_field() call OR a hidden input name=csrf_token. Zero exceptions
  - `rule` TR-12.2: POST to settings.php with empty/missing token → 403/flash-error, DB NOT updated (SELECT system_settings value unchanged)

## Task 13: Smoke test end-to-end regressions + document findings
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Tasks 1-12
- **Description**:
  - Walk through each core flow manually (or automated via curl): login → dashboard → create booking → view booking → add payment → deactivate user → change language → download CSV → confirm.php token validation → search → calendar download ics
  - Record any behavioral regressions; if flow broken → rollback/fix the culprit change. If no regressions, mark TR-13.1 as pass.
  - Verify audit_logs populated with at least 6 distinct security action types after the flows.
- **Acceptance Criteria Addressed**: AC-15
- **Test Requirements**:
  - `rubric` TR-13.1: Functional regression pass — dimension: number of broken flows; scale 1-5 (per AC-15 rubric anchors); threshold >= 4; evidence = step-by-step test checklist results
  - `rule` TR-13.2: audit_logs contains rows with actions: failed_login, invalid_csrf (triggered intentionally), password_changed, login (success), permission_denied (trigger intentionally) — count >= 5 distinct actions present
