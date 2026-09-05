# DJ & RAK Inventory Rental System - OWASP Top 10 Security Hardening

## Overview
- **Summary**: Apply the OWASP Top 10 (2021) security controls to the DJ & RAK PHP/MySQL inventory rental system. This is a full-stack security hardening initiative covering authentication, session management, access control, input/output sanitization, transport security, and integrity protections.
- **Purpose**: Close all identifiable security gaps in the current codebase so the application is safe to expose in production.
- **Target Users**: System administrators, authenticated staff users, and external booking confirmation recipients (clients via confirm.php).

## Goals
1. Eliminate zero-day CSRF coverage on all state-changing forms and endpoints.
2. Harden session/cookie security (HttpOnly, Secure if HTTPS, SameSite, regenerate ID at login).
3. Enforce strict access controls on every state-changing endpoint including public confirm.php.
4. Remove or disable production-dangerous configuration (error display, install.php, HTTP-only SITE_URL).
5. Add security response headers (CSP strict-dynamic, X-Frame-Options, X-Content-Type-Options, HSTS when TLS, Referrer-Policy, Permissions-Policy).
6. Protect authentication (brute-force throttling, password length >= 8, session timeout enforced, failed login audit trail).
7. Defend against injection vectors (SQL identifier quoting in installer, dynamic query whitelisting).
8. Ensure third-party CDN assets are loaded with Subresource Integrity (SRI) hashes.
9. Provide defense-in-depth output escaping and input validation patterns.
10. Add required security logging for denied access, failed authentication, and invalid token use.

## Non-Goals
- Rewrite the application to a different framework.
- Implement fully-fledged 2FA / OAuth integrations (out of scope).
- Add client-side WAF or server-level mod_security rules (host-level).
- Conduct external penetration testing or bug bounty (process only).

## Background & Context
Current codebase baseline audit (2026-09-05):
- 47 source PHP files, 46 tables/queries use PDO prepared statements (good).
- **ZERO CSRF tokens** across 18 state-changing forms and 6 `action=...` GET endpoints (`booking_action.php`, `payment_action.php`, `audit_logs.php`, `users.php?delete=`, `change_lang.php` open redirect).
- **Session cookies** use PHP defaults: no `httponly`, no `secure`, no `samesite`; `session_start()` called with default params.
- `login.php` no `session_regenerate_id()`, no login attempt throttling, failed logins not recorded in audit_logs.
- `confirm.php` public page: NO rate limit, NO one-time token, NO CSRF, booking status escalated to 'Confirmed' by anonymous POST with any valid token.
- `config.php`: `display_errors = 1` + `error_reporting = E_ALL`; SITE_URL hardcoded `http://`; HSTS/CSP/X-Frame-Options headers absent.
- `install.php`: Always accessible (warning-only), leaks PDO exceptions with full stack traces; uses string-interpolated SQL identifiers `CREATE DATABASE \`$dbName\`` and `USE \`$dbName\`` with no identifier validation.
- `change_lang.php`: open redirect via `HTTP_REFERER` with minimal `change_lang.php` substring check.
- CDN assets (Bootstrap 4.6.2, jQuery 3.6.0, FontAwesome 5.15.4, select2, flatpickr) served without SRI `integrity` attributes.
- Passwords hashed with `password_hash($pw, PASSWORD_DEFAULT)` (good bcrypt) but minimum length = 6 chars (too weak).
- `SESSION_TIMEOUT = 3600` defined but never enforced against `login_time`.
- All GET-based delete/cancel/status actions (`booking_action.php?action=cancel`, `payment_action.php?action=delete`, `users.php?delete=ID`) are CSRF-prone and lack strict referrer/permission validation beyond page-level check.

## Functional Requirements
- **FR-1 (CSRF)**: Every POST form and every state-changing GET/POST endpoint MUST include and validate a per-session CSRF token; missing/invalid tokens return HTTP 403 and an audit log entry.
- **FR-2 (Session)**: Login MUST call `session_regenerate_id(true)`, cookies MUST have `httponly=1`, `samesite=Lax`, and `secure=1` when SITE_URL starts with `https://`; session MUST be expired after `SESSION_TIMEOUT` seconds of inactivity or login age.
- **FR-3 (Authentication)**: Login form MUST track failed attempts per username+IP in `login_attempts` table and require 2^n seconds throttle (min 1s, cap 15s) after 3 consecutive fails; failed attempts MUST be written to audit_logs.
- **FR-4 (Password Policy)**: Min password length raised to >= 8 characters; user creation, user edit with new password, profile password change MUST all reject <8.
- **FR-5 (Access Control - confirm.php)**: Anonymous `confirm.php` submission MUST be idempotent (repeated POSTs with same token do not overwrite confirmed status), MUST log the IP and user-agent, and MUST reject any `action` not in the explicit whitelist.
- **FR-6 (Access Control - actions)**: GET-based action endpoints (`booking_action.php`, `payment_action.php`, `users.php?delete=`) MUST be converted to POST-only with CSRF, OR carry a signed single-use token; page-level `requirePermission` alone is insufficient.
- **FR-7 (HTTP Response Headers)**: Every HTML-producing endpoint MUST emit: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`; plus a CSP with `strict-dynamic` nonce for inline scripts/styles; HSTS `Strict-Transport-Security: max-age=31536000; includeSubDomains` only when request is HTTPS or config is HTTPS.
- **FR-8 (Error Disclosure)**: `display_errors`, `display_startup_errors` MUST be Off by default in `config.php` (and installer-generated `config.php`); errors MUST be written to log file only via `ini_set('log_errors',1)`.
- **FR-9 (Installer)**: `install.php` MUST refuse to run if `config.php` exists and contains DB credentials; installer-generated SQL identifier `$dbName` MUST be validated against regex `^[A-Za-z0-9_]+$` and rejected otherwise; PDO exception messages MUST NOT be echoed verbatim to client.
- **FR-10 (Integrity - SRI)**: All CDN `<link>` stylesheet and `<script>` elements MUST have `integrity` (SHA-384) and `crossorigin="anonymous"` attributes using canonical upstream SRI hashes.
- **FR-11 (Open Redirect)**: `change_lang.php` MUST validate that `HTTP_REFERER` (or redirect target) points to the same origin (`SITE_URL`) before issuing the redirect; otherwise fallback to `SITE_URL.'/index.php'`.
- **FR-12 (Audit Events)**: Security events that MUST appear in audit_logs: failed_login (username + IP), permission_denied (endpoint + perm), invalid_csrf, invalid_confirmation_token, install_script_access, password_changed, user_deactivated.

## Non-Functional Requirements
- **NFR-1 (No Regressions)**: All existing functionality (bookings CRUD, payments, calendar, WhatsApp quoting, client confirmation flow, search, CSV export, filters) MUST continue to work after hardening.
- **NFR-2 (Performance)**: Total added latency per request MUST remain <15ms (SRI is client-side; CSRF token compare is O(1); throttle lookup is single indexed select).
- **NFR-3 (Portability)**: Works on PHP 7.4+, MySQL 5.7+; no new extensions required beyond pdo_mysql, json, session.
- **NFR-4 (Code Quality)**: Follow existing code style (Procedural + global $conn, helper functions in functions.php, Bootstrap 4 UI).

## Constraints
- **Technical**: PHP procedural codebase (not OOP/MVC); new security helpers must be added to `includes/functions.php`.
- **Business**: Booking confirmation flow via public confirm.php must remain accessible (no login) but hardened.
- **Dependencies**: Only existing CDN vendors; no new composer/npm packages. `login_attempts` table to be added to existing schema via in-app lazy-create SQL.

## Assumptions
- Users already access the app via a hostname that will eventually be served over HTTPS; we set `secure=1` cookie flag dynamically based on request scheme/SITE_URL.
- Upstream CDN (jsdelivr, cdnjs) SRI hashes for exact pinned versions are available and canonical; we use those exact strings.
- Single PHP process per request; throttle sleep is acceptable anti-bruteforce without needing Redis/APCu at this scale.

## Acceptance Criteria

### AC-1: CSRF tokens issued and validated everywhere
- **Type**: `rule`
- **Given**: An authenticated session with a valid CSRF token
- **When**: Any POST form is submitted without a matching `csrf_token` field OR a state-changing GET endpoint is invoked without a valid CSRF query param
- **Then**: Request is rejected with HTTP 403 (or 302 with error flash + audit log)
- **Pass Condition**: Grep confirms zero remaining `<form method=POST>` blocks without `csrf_token` hidden input; manual submission of a POST with empty token returns 403 or flash-error redirect and does NOT mutate database state
- **Evidence**: Static scan output + manual test via curl (POST to booking_form create) without token, showing no DB row inserted

### AC-2: Session cookie flags and regeneration
- **Type**: `rule`
- **Given**: A fresh browser login over HTTP or HTTPS
- **When**: Inspecting `Set-Cookie` response header after successful login
- **Then**: `PHPSESSID` cookie has `HttpOnly`, `SameSite=Lax`; and `Secure` flag IF request scheme is HTTPS or SITE_URL starts with `https://`; login response also indicates session ID changed (new Set-Cookie)
- **Pass Condition**: Login response headers contain both `Set-Cookie` (regenerate) AND cookie params match httponly / samesite as specified
- **Evidence**: Network capture / header inspection from login.php POST 302

### AC-3: Session timeout enforced
- **Type**: `rule`
- **Given**: A logged-in user session with `login_time` set to 2 hours ago
- **When**: Any page requiring authentication is loaded
- **Then**: Session is destroyed, user redirected to login.php with flash "Session timed out"
- **Pass Condition**: Manually setting `$_SESSION['login_time']` to `time()-7201` then reloading index.php redirects to login
- **Evidence**: Step-by-step reproduction result

### AC-4: Login brute-force throttling + audit log
- **Type**: `rule`
- **Given**: 4 consecutive failed login attempts from same IP for username `admin`
- **When**: Attempt #4 is submitted
- **Then**: Response delays >= 2 seconds before returning invalid credentials error; a row exists in audit_logs with action='failed_login' and entity_type='User' for each fail
- **Pass Condition**: Timed curl attempts show cumulative throttle delay; audit_logs table count for failed_login matches attempt count
- **Evidence**: Audit log query + request timing

### AC-5: Password minimum length 8 enforced at all 3 paths
- **Type**: `rule`
- **Given**: Submissions of 7-character password
- **When**: Creating user (users.php), updating user password (users.php update), profile password change (profile.php)
- **Then**: All 3 paths reject with error message and no password change in DB
- **Pass Condition**: Single test case per path: POST with 7-char pw returns error flash; users.password_hash unchanged for profile/update tests
- **Evidence**: Per-path request/response pairs

### AC-6: confirm.php idempotent + validation
- **Type**: `rule`
- **Given**: A booking token that has already been Confirmed via POST action=confirm
- **When**: Client submits POST action=confirm again OR submits action=foobar OR empty action
- **Then**: Database status remains 'Confirmed' unchanged; invalid action rejected with error; each attempt recorded in audit_logs
- **Pass Condition**: Two consecutive action=confirm POSTs show booking.status identical; action=invalid returns error 400/flash
- **Evidence**: SQL SELECT before/after 2x POST + invalid action response

### AC-7: State-changing GET actions removed/POST-only
- **Type**: `rule`
- **Given**: Logged-in user
- **When**: Visiting `booking_action.php?action=cancel&id=1` directly in URL bar OR `payment_action.php?action=delete&id=1` OR `users.php?delete=2`
- **Then**: No database mutation occurs; either 405 Method Not Allowed OR 302 flash "Please use the form button" + audit_logs entry
- **Pass Condition**: GET requests to action endpoints do not mutate (counters unchanged in DB)
- **Evidence**: curl -X GET vs curl -X POST (with token) comparison showing only POST+token works

### AC-8: Security HTTP headers present on HTML responses
- **Type**: `rule`
- **Given**: Any GET request that returns HTML (login, index, bookings, confirm.php, etc.)
- **When**: Inspecting the Response headers
- **Then**: All of the following are present: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`, `Content-Security-Policy:` with nonce OR strict-dynamic. When request is HTTPS, `Strict-Transport-Security:` header present.
- **Pass Condition**: Header check tool/grep confirms all 5+ headers
- **Evidence**: curl -I output for index.php

### AC-9: Production error display disabled
- **Type**: `rule`
- **Given**: Runtime in production mode (default)
- **When**: Triggering `trigger_error('test');` or referencing undefined constant
- **Then**: No error message visible in response body; message logged to PHP error log
- **Pass Condition**: ini_get('display_errors') returns '0' OR '' from a runtime `<?=ini_get('display_errors')?>` probe on index.php
- **Evidence**: Runtime probe output + config.php source

### AC-10: install.php blocked when system installed
- **Type**: `rule`
- **Given**: config.php exists AND contains non-empty DB_NAME/DB_USER
- **When**: GET request to install.php
- **Then**: install.php returns 404 or redirects to login.php; schema page never reachable
- **Pass Condition**: install.php top-of-file guard fires; HTTP redirect to login
- **Evidence**: GET install.php -> 302 Location: login.php

### AC-11: SQL identifier validation in installer
- **Type**: `rule`
- **Given**: POST install.php?step=config with `db_name = "x'; DROP...bad"`
- **When**: Writing config.php AND attempting CREATE DATABASE
- **Then**: Validation regex fails, error flash shown, NO config.php written, NO SQL executed
- **Pass Condition**: Submission fails before PDO call; no new rows/DBs; error shown
- **Evidence**: Test POST with malicious db_name + DB check

### AC-12: SRI integrity attributes on all CDN resources
- **Type**: `rule`
- **Given**: HTML `<head>` output of any page
- **When**: Grep for `<link rel="stylesheet" href="https://...">` and `<script src="https://...">`
- **Then**: Every external CDN href/src has sibling `integrity="sha384-..."` and `crossorigin="anonymous"` attributes with valid base64 SHA-384 (>=96 chars, base64 alphabet)
- **Pass Condition**: Zero CDN `<link>`/`<script>` missing `integrity` attribute
- **Evidence**: grep -n output from header.php + login.php + confirm.php

### AC-13: Open redirect closed in change_lang.php
- **Type**: `rule`
- **Given**: HTTP_REFERER = `https://evil.com/phish`
- **When**: GET change_lang.php?lang=ar
- **Then**: Redirect Location MUST be a path under SITE_URL (typically `/index.php`), NOT evil.com
- **Pass Condition**: Curl with spoofed Referer shows SITE_URL-only redirect
- **Evidence**: curl -e "https://evil.com" output showing Location header

### AC-14: Security events audited
- **Type**: `rule`
- **Given**: Security events (failed login, invalid CSRF, permission denied, invalid confirmation token, install access, password changed)
- **When**: Triggered each one in sequence
- **Then**: audit_logs contains corresponding row with `action` IN {'failed_login','invalid_csrf','permission_denied','invalid_confirmation_token','install_access','password_changed'} with non-null user_id OR ip_address
- **Pass Condition**: COUNT(*) per action >= 1 after running trigger scenarios
- **Evidence**: SQL SELECT query results

### AC-15: Baseline functional regression pass
- **Type**: `rubric`
- **Dimension**: Functional regressions across core user flows (login, create booking, add payment, delete booking, filter bookings report, search, CSV export, calendar view, WhatsApp message preview)
- **Scale**: 1-5
- **Anchors**: 1 = >=3 flows broken; 3 = 1-2 minor visual/non-blocking issues; 5 = All 10 flows work as before with zero behavioral change
- **Pass Threshold**: >= 4
- **Evidence**: Manual smoke test checklist results

## Open Questions
- [ ] Should HSTS be enabled even on HTTP (redirect first to HTTPS via .htaccess) or strictly only for HTTPS requests? Default plan: only emit on HTTPS responses.
- [ ] Should users be forcefully logged out of other sessions on password change? Current scope: optional, default off for simplicity.
