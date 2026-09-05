# DJ RAK Inventory & Rental Management System

**Version:** 1.0  
**Date:** 2026-08-31  
**Architecture:** PHP Native (PDO) + MySQL + Bootstrap 4.6  
**Languages:** Bilingual (English / Arabic RTL)

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Directory Structure](#2-directory-structure)
3. [Technology Stack](#3-technology-stack)
4. [Database Schema](#4-database-schema)
5. [Configuration & Bootstrapping](#5-configuration--bootstrapping)
6. [Core Modules](#6-core-modules)
7. [Authentication & Authorization](#7-authentication--authorization)
8. [Internationalization (I18N)](#8-internationalization-i18n)
9. [Booking Workflow](#9-booking-workflow)
10. [Financial & Expense Model](#10-financial--expense-model)
11. [Reports & Analytics](#11-reports--analytics)
12. [Inventory Management](#12-inventory-management)
13. [Invoicing & Printing](#13-invoicing--printing)
14. [WhatsApp Integration](#14-whatsapp-integration)
15. [Audit Logging](#15-audit-logging)
16. [System Settings](#16-system-settings)
17. [Frontend UI Framework](#17-frontend-ui-framework)
18. [Engineering Conventions & Hard Constraints](#18-engineering-conventions--hard-constraints)
19. [Installation](#19-installation)
20. [File Reference Index](#20-file-reference-index)

---

## 1. System Overview

DJ RAK Inventory & Rental Management System is a comprehensive business management platform designed for a DJ equipment rental company. The system handles:

- **Equipment Rental Bookings** — End-to-end quoting, confirmation, and event lifecycle
- **Inventory Tracking** — Categories, item types, serialised assets, and availability checking
- **Client Management** — CRM with contact details, booking history, and statements
- **Payments & Expenses** — Multi-payment tracking, per-booking expenses, and general business expenses
- **Financial Reporting** — 5 KPI dashboard, revenue trend charts, expense breakdowns, CSV export
- **Calendar View** — Visual rental schedule with exportable iCal feeds
- **Invoicing** — A4 grid-bordered printable invoices with bilingual support
- **WhatsApp Quotations** — Customer-facing quotation messages (line-item prices hidden)
- **Role-Based Access** — 4 roles: Administrator, Booking User, Finance User, Viewer
- **Audit Trail** — Full activity logging of all create/update/delete operations

---

## 2. Directory Structure

```
MS/
├── assets/
│   ├── css/
│   │   ├── style.css          # Main stylesheet (sidebar, cards, tables, print)
│   │   └── rtl.css            # RTL overrides (Arabic layout)
│   └── js/
│       └── app.js             # Client-side interactivity (filters, toggles, modals)
├── database/
│   └── schema.sql             # Full DB schema + seed data
├── includes/
│   ├── db.php                 # PDO Database singleton class
│   ├── functions.php          # ~60 helper functions (business logic, I18N, formatters)
│   ├── header.php             # Sidebar + top navigation (all authenticated pages)
│   └── footer.php             # Closing markup + JS includes
├── lang/
│   ├── en.php                 # English translation dictionary (~250 keys)
│   └── ar.php                 # Arabic translation dictionary (mirror of en.php)
├── uploads/                   # PHP session files (writable)
├── config.php                 # Bootstrap: DB constants, SITE_URL, session, i18n init
├── install.php                # One-click schema installer
├── login.php                  # Auth entry (split login card with gradient)
├── logout.php                 # Session destroy
├── change_lang.php            # Toggle EN/AR (persists in session)
├── search.php                 # Global search (bookings, clients, items)
├── index.php                  # Dashboard (KPIs + charts + upcoming events)
├── calendar.php               # Full booking calendar view (monthly)
├── calendar_download.php      # iCal (.ics) export endpoint
# ===== Bookings =====
├── bookings.php               # Booking list + advanced filters
├── booking_form.php           # Create/edit booking (equipment selector, avail. check)
├── booking_view.php           # Booking detail view (tabs, actions, WA link)
├── booking_action.php         # Booking state mutations (cancel, etc.)
├── confirm.php                # Public customer confirmation page (token-based)
# ===== Inventory =====
├── categories.php             # Equipment categories CRUD
├── item_types.php             # Item types (catalog) CRUD with default rates
├── inventory_items.php        # Serialised individual assets (status tracking)
# ===== Clients =====
├── clients.php                # Client CRM (list, add, edit, quick-create in booking)
# ===== Payments =====
├── payments.php               # Payment ledger
├── payment_action.php         # Payment record/create endpoint
# ===== Expenses =====
├── expense_types.php          # Expense category CRUD
├── expenses.php               # Expense ledger (booking-linked + general)
# ===== Reports =====
├── reports_bookings.php       # Bookings report (tab in reports_financial)
├── reports_expenses.php       # Expenses report
├── reports_financial.php      # Financial report: 5 KPIs + trends (main reports hub)
├── reports_inventory.php      # Inventory utilisation report
├── reports_client_statement.php # Per-client statement
# ===== System =====
├── users.php                  # User management + role assignment
├── profile.php                # Current user profile
├── settings.php               # System settings (company, currency, tax, etc.)
├── audit_logs.php             # Read-only audit trail viewer
├── invoice.php                # Printable invoice view (A4)
└── ajax_availability.php      # XHR endpoint: live availability lookups
```

---

## 3. Technology Stack

| Layer | Technology | Version | Notes |
|-------|-----------|---------|-------|
| Backend | PHP | 7.4+ | Native PDO, no framework |
| Database | MySQL / MariaDB | 5.7+ | InnoDB, utf8mb4_unicode_ci |
| Frontend | Bootstrap | 4.6.2 | CDN-loaded, responsive |
| Icons | Font Awesome | 5.15.4 | CDN |
| Dropdowns | Select2 | 4.1.0-rc.0 | Bootstrap 4 theme |
| Date Picker | flatpickr | latest | `DD/MM/YYYY` UI format, `YYYY-MM-DD` DB format |
| Charts | Chart.js | via CDN | Line + Bar + Doughnut |
| Session | PHP native | files | Stored in `uploads/` directory |
| CSS | Custom + RTL | — | `style.css` + `rtl.css` with `html[dir="rtl"]` overrides |
| Timezone | Asia/Riyadh | UTC+3 | Set in `config.php` + schema.sql |

---

## 4. Database Schema

Full schema defined in [schema.sql](file:///c:/xampp/htdocs/MS/database/schema.sql).  
**14 tables** with InnoDB foreign keys + cascade rules.

### 4.1 Core Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| **roles** | User roles | `id`, `name` (Administrator/Booking User/Finance User/Viewer) |
| **permissions** | 20 granular permissions | `permission_name` (e.g. `create_bookings`) |
| **role_permissions** | Many-to-many | `role_id`, `permission_id` (CASCADE delete) |
| **users** | Staff accounts | `username`, `password_hash`, `role_id`, `phone`, `active`, `last_login` |
| **categories** | Equipment categories | `name`, `active` |
| **item_types** | Equipment catalog items | `category_id`, `name`, `default_rental_value`, `quantity` |
| **inventory_items** | Serialised individual assets | `item_type_id`, `serial_number`, `status` (7-state ENUM), `asset_code`, `purchase_date` |
| **clients** | Customer CRM | `name`, `phone`, `alt_phone`, `email`, `address`, `notes`, `active` |
| **expense_types** | Expense categories | `name`, `fixed_value` (optional default), `description` |
| **bookings** | Rental events | `booking_number`, `client_id`, `date_from/date_to`, `event_start_time/end_time`, `location`, `quoted_amount`, `dj_rak_amount`, `status` (7-state), `payment_status` (4-state), `customer_confirmation_token` |
| **booking_items** | Line items per booking | `booking_id`, `item_type_id`, `quantity`, `rental_value` |
| **payments** | Payment transactions | `booking_id`, `payment_date`, `amount`, `payment_method`, `reference` |
| **expenses** | Spending records | `expense_type_id`, `date`, `amount`, `booking_id` (NULLABLE = general expense), `payment_method`, `reference` |
| **audit_logs** | Activity trail | `user_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `user_agent` |
| **system_settings** | Key-value config | `setting_key`, `setting_value`, `description` |

### 4.2 ENUM States

**Booking Status:**
- `Draft` → `Quotation` → `Confirmed` → `Change Requested` → `Event Completed` → `Closed` / `Canceled`

**Payment Status:**
- `Not Collected` → `Partially Collected` → `Fully Collected` / `Canceled`

**Inventory Item Status:**
- `Available`, `Booked`, `Out for Event`, `Maintenance`, `Damaged`, `Lost`, `Retired`

### 4.3 Foreign Key Cascade Rules
- `role_permissions`: CASCADE on both role + permission delete
- `booking_items`: CASCADE delete when booking is deleted
- `payments`: CASCADE delete when booking is deleted
- `expenses`: SET NULL on `booking_id` when booking is deleted (preserves general expenses)

---

## 5. Configuration & Bootstrapping

### 5.1 [config.php](file:///c:/xampp/htdocs/MS/config.php)

Every page requires `config.php` first. It performs:

1. **Timezone** — `date_default_timezone_set('Asia/Riyadh')`
2. **Error Reporting** — `E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT`
3. **DB Constants** — `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` (default `dj_rak_system`)
4. **URL Resolution** — `SITE_URL` computed from `$_SERVER['HTTP_HOST']` + script dir; `SITE_PATH` as realpath
5. **Display Constants** — `CURRENCY_SYMBOL='JOD'`, `DATE_FORMAT='d/m/Y'`, `DATETIME_FORMAT='d/m/Y H:i'`
6. **Session** — `SESSION_TIMEOUT=3600`, auto-starts unless CLI
7. **Uploads** — Creates `uploads/` dir if missing (0755)
8. **Includes** — `functions.php`, then `db.php`
9. **DB** — Instantiates `Database` class, exposes `$conn` globally
10. **System Settings** — Calls `loadSystemSettings()` to hydrate `SYS_*` constants
11. **I18N** — Calls `initI18n()` and defines `LANG_CODE` + `IS_RTL`

### 5.2 [includes/db.php](file:///c:/xampp/htdocs/MS/includes/db.php)

Singleton-style `Database` class:
- PDO DSN with `charset=utf8mb4`
- `ATTR_ERRMODE` → ERRMODE_EXCEPTION
- `ATTR_DEFAULT_FETCH_MODE` → FETCH_ASSOC
- `ATTR_EMULATE_PREPARES` → false (true native prepared statements)
- Fails silently; callers check `$conn !== null`

---

## 6. Core Modules

### 6.1 Dashboard — [index.php](file:///c:/xampp/htdocs/MS/index.php)
Displays a 6-KPI header (Bookings, Booked Amount, Collected, Pending, Expenses, Net Collected) plus:
- 12-month revenue trend chart (Booked / Collected / Expenses dashed-red line)
- Top expense categories bar chart
- Top 5 clients by booking value
- Upcoming bookings list (next events)
- Pending payments list
- Inventory summary: total units, available today, booked today
- Date filters default to **current month** (1st → today)
- **5 KPI requirement:** Blue=Total Booked, Green=Total Collected, Red=Total Expenses, Amber=Total Pending, Net=Collected−Expenses

### 6.2 Calendar — [calendar.php](file:///c:/xampp/htdocs/MS/calendar.php)
Monthly grid view. Each day shows booking cards with client, status color, and quick-links. Export to `.ics` via [calendar_download.php](file:///c:/xampp/htdocs/MS/calendar_download.php).

### 6.3 Global Search — [search.php](file:///c:/xampp/htdocs/MS/search.php)
Unified search across bookings (number, location), clients (name, phone), and equipment items.

---

## 7. Authentication & Authorization

### 7.1 Authentication
[login.php](file:///c:/xampp/htdocs/MS/login.php) — Split-screen card form (gradient left + form right):
- Accepts `username` OR `email` + `password`
- Verifies via `password_verify()` against `password_hash` (bcrypt `$2y$10$`)
- Requires `active=1`
- On success: sets `$_SESSION['user_id']`, updates `last_login`, logs `auditLog('login')`
- Redirects to [index.php](file:///c:/xampp/htdocs/MS/index.php)
- Default admin seeded in schema: **admin / admin123**

Session timeout handled by `SESSION_TIMEOUT` (3600s) — checked on page load.

### 7.2 Authorization (RBAC)
Implemented via `hasPermission()` in [functions.php](file:///c:/xampp/htdocs/MS/includes/functions.php#L48-L63).

**20 Permissions** seeded (each with unique `permission_name`):

| ID | Permission | Purpose |
|----|-----------|---------|
| 1 | `manage_users` | Create/edit users + assign roles |
| 2 | `manage_setup` | Categories / Item types / Expense types / Settings |
| 3 | `manage_inventory` | CRUD individual inventory items |
| 4 | `manage_clients` | Add/edit clients |
| 5 | `view_clients` | View client list only |
| 6 | `create_bookings` | New bookings form |
| 7 | `edit_bookings` | Modify existing bookings |
| 8 | `cancel_bookings` | Cancel bookings |
| 9 | `view_bookings` | View booking list & details |
| 10 | `record_payments` | Record payment transactions |
| 11 | `view_financials` | Financial dashboard & reports |
| 12 | `view_dj_rak` | View DJ RAK commission info |
| 13 | `manage_expenses` | CRUD expenses |
| 14 | `view_expenses` | View expense ledger |
| 15 | `view_reports` | All reports access |
| 16 | `view_calendar` | Calendar page |
| 17 | `view_dashboard` | Dashboard page |
| 18 | `send_whatsapp` | Generate WhatsApp links |
| 19 | `view_audit_logs` | Audit log viewer |
| 20 | `manage_settings` | System settings page |
| 21 | `override_inventory` | Allow booking quantities above available stock |

**Role Assignments (default):**
- **Administrator (role=1):** All permissions (auto-granted by `role_name === 'Administrator'` short-circuit)
- **Booking User (role=2):** 3,4,5,6,7,9,10,13,15,16,17,18 (operational booking flow)
- **Finance User (role=3):** 5,9,10,11,12,13,14,15,17 (financial ops)
- **Viewer (role=4):** 5,9,11,14,15,16,17 (read-only everywhere)

Guarded by `requirePermission($permName)` which redirects to dashboard with flash error if the user lacks the permission.

### 7.3 Users & Profile Pages
- [users.php](file:///c:/xampp/htdocs/MS/users.php) — Admin CRUD + role picker
- [profile.php](file:///c:/xampp/htdocs/MS/profile.php) — Self-service name/email/password change

---

## 8. Internationalization (I18N)

### 8.1 Architecture
Full I18N layer in [functions.php](file:///c:/xampp/htdocs/MS/includes/functions.php#L301-L385):
- 2 languages: `en` (English, default) + `ar` (Arabic, RTL)
- Detection order: `$_SESSION['lang']` → `Accept-Language` header → fallback `en`
- Dictionaries loaded from [lang/en.php](file:///c:/xampp/htdocs/MS/lang/en.php) and [lang/ar.php](file:///c:/xampp/htdocs/MS/lang/ar.php) (PHP array return)
- Active language is always merged on top of English dictionary (English as fallback)
- Locale set via `setlocale(LC_TIME, ...)` for month/day names

### 8.2 API
- `t($key, $params)` — translate with optional `{param}` or `%s` / `%1$s` placeholders
- `te($key, $params)` — `e(t(...))` for HTML-safe output
- `t_booking_status($s)`, `t_payment_status($s)`, `t_payment_method($m)`, `t_inv_status($s)`, `t_inv_condition($c)` — enum translators
- `t_month($idx0)`, `t_day($idx0)` — calendar helpers (1-based month mapping to 0-based array)
- `isRtlLang()` — returns `true` when active lang is Arabic

### 8.3 Language Switching
[change_lang.php](file:///c:/xampp/htdocs/MS/change_lang.php) toggles `?lang=en` / `?lang=ar` and stores in session. Switcher UI is in the top navbar (header.php line 128-131) as `EN` / `ع` pill buttons.

### 8.4 RTL CSS
[rtl.css](file:///c:/xampp/htdocs/MS/assets/css/rtl.css) loaded conditionally when `IS_RTL === true`. Selectors are scoped under `html[dir="rtl"]` for:
- Text direction, float flipping, margin/padding mirroring
- Equipment cards: right-side accent bars (per project rules)
- Sidebar scroll + table alignment overrides

---

## 9. Booking Workflow

### 9.1 Booking Creation — [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php)

**Step 1 — Client:**
- Dropdown from existing clients OR quick-create (`new_client_name` + `new_client_phone` fields → auto-creates client row)

**Step 2 — Event Metadata:**
- `date_from` / `date_to` (flatpickr `DD/MM/YYYY`)
- `event_start_time` / `event_end_time` (optional time pickers)
- `location` (free text)

**Step 3 — Equipment (core):**
- Categorised equipment selector with collapsible category cards
- Each row: `item_type_id` → `quantity` → `rental_value` (auto-fills default_rental_value)
- Live availability check via `getAvailableQuantity()` on submit
- If `qty > available` AND user lacks `override_inventory` → hard error with item name + requested/available numbers
- Search filters must call `refreshAllCatToggles` (see lessons learned)

**Step 4 — Pricing:**
- **Automatic quoted amount calculation:** `Σ(qty × rental_value) + dj_rak_amount`
- **Manual override allowed:** `quoted_amount` field is user-editable (bold emphasis)
- `dj_rak_amount` is a separate commission/fee field (tracked independently)

**Step 5 — Status & Save:**
- Initial status: `Quotation` (default), `Draft`, or `Confirmed` (if user skips quoting step)
- Submit actions: `Save` or `Save & Send WhatsApp` (redirects to booking_view with `&wa=1`)
- All DB writes wrapped in transaction: booking INSERT/UPDATE + DELETE-then-INSERT booking_items
- Post-save: `updateBookingPaymentStatus()` recalculates `payment_status`

### 9.2 Booking Number Format
`generateBookingNumber()` produces:
```
{BK}{YYYY}{MM}{NNNN}
e.g. BK2026080001
```
Counter resets per month.

### 9.3 Booking List — [bookings.php](file:///c:/xampp/htdocs/MS/bookings.php)
Filters: Search text, booking status, payment status, client, date-from / date-to (all optional).  
Table columns: Booking #, Client, Dates, Location, Quoted (right), Collected (right, green), Pending (right, red if >0), Status badge, Payment badge, Actions (view/edit/cancel).

### 9.4 Booking Detail — [booking_view.php](file:///c:/xampp/htdocs/MS/booking_view.php)
Full booking detail with tabs:
- Overview (dates, client, quoted/collected/pending KPI strip)
- Equipment list with per-item pricing
- Payments timeline with add-payment form
- Expenses linked to this booking
- Activity / timeline with status transitions
- Action buttons: Send WhatsApp (generates pre-filled message), Print Invoice, Edit, Cancel, Change Status

### 9.5 Customer Confirmation — [confirm.php](file:///c:/xampp/htdocs/MS/confirm.php)
Public page accessible via token URL:
```
confirm.php?token={customer_confirmation_token}
```
Shows: Booking summary, client name, event details, equipment list (no prices), bold Total Quoted, Confirm / Decline / Request Change buttons. On click, updates `customer_confirmed_at`, `customer_response`. Fires audit log.

---

## 10. Financial & Expense Model

### 10.1 Two Expense Types (Critical)

The expenses table supports **both booking-tied and general business expenses**:

```sql
-- Per-booking expense
INSERT INTO expenses (expense_type_id, date, amount, booking_id, ...) VALUES (1, '2026-08-31', 50, 12, ...);
-- General (unlinked) expense
INSERT INTO expenses (expense_type_id, date, amount, booking_id, ...) VALUES (7, '2026-08-31', 300, NULL, ...);
```

**HARD RULE:** All financial reports and dashboards **must** aggregate both types unconditionally. The SUM query **does not** INNER JOIN bookings (which would drop `booking_id IS NULL` rows). See [reports_financial.php](file:///c:/xampp/htdocs/MS/reports_financial.php#L133-L138) for the unconditional pattern:

```sql
SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date >= ? AND date <= ?
```

### 10.2 Payments
[payments.php](file:///c:/xampp/htdocs/MS/payments.php) — Full payment ledger across all bookings.  
[payment_action.php](file:///c:/xampp/htdocs/MS/payment_action.php) — Create endpoint.

Payment fields: `booking_id` (FK, required), `payment_date` (DATE), `amount` (DECIMAL 12,2), `payment_method` (Cash/Transfer/CliQ/Bank Transfer/Other), `reference` (free text: cheque #, transaction ID), `notes`, `created_by`.

**Auto-calculated state:**
- `updateBookingPaymentStatus($bookingId)` recomputes `payment_status`:
  - `quoted <= 0 OR collected <= 0` → `Not Collected`
  - `collected >= quoted` → `Fully Collected`
  - else → `Partially Collected`
- Called after every payment INSERT/DELETE and booking edit

### 10.3 Booking Totals
For any booking, three amounts are computed on-demand:
- **Quoted:** `bookings.quoted_amount` (manually editable, includes `dj_rak_amount`)
- **Collected:** `SUM(payments.amount)` — via `getBookingCollectedAmount()`
- **Pending:** `MAX(0, quoted - collected)` — via `getBookingPendingAmount()`

### 10.4 Financial KPIs (5 Cards — Required)

All report headers and dashboard show these 5 colored cards:

| Card | Color | Formula |
|------|-------|---------|
| Total Booked | Blue (primary) | `SUM(quoted_amount)` for non-canceled bookings in range |
| Total Collected | Green (success) | `SUM(payments.amount)` for non-canceled bookings in range |
| Total Expenses | Red (danger) | `SUM(expenses.amount)` — ALL expenses incl. general (no INNER JOIN) |
| Total Pending | Amber (warning) | `SUM(MAX(0, quoted - collected))` — non-canceled |
| Net | Purple (info) | `Total Collected − Total Expenses` |

### 10.5 Expense Types CRUD
[expense_types.php](file:///c:/xampp/htdocs/MS/expense_types.php) — 10 pre-seeded: Transportation, Fuel, Maintenance, Equipment Repair, Staff, Marketing, Storage, Insurance, Software, Other. Each has an optional `fixed_value` default.

---

## 11. Reports & Analytics

### 11.1 Hub Architecture
All reports are accessible via sidebar **Reports** submenu. The tabbed hub is [reports_financial.php](file:///c:/xampp/htdocs/MS/reports_financial.php), which embeds `reports_expenses.php`, `reports_inventory.php`, or `reports_client_statement.php` via `$tab` GET param.

### 11.2 Date Filter Defaults (Critical Rule)
**Every report page defaults its date range to the current month** — 1st day → last day, in `DD/MM/YYYY` format. Applied in:
- [reports_financial.php](file:///c:/xampp/htdocs/MS/reports_financial.php#L100-L103)
- [reports_bookings.php](file:///c:/xampp/htdocs/MS/reports_bookings.php)
- [reports_expenses.php](file:///c:/xampp/htdocs/MS/reports_expenses.php)
- [reports_inventory.php](file:///c:/xampp/htdocs/MS/reports_inventory.php)
- [reports_client_statement.php](file:///c:/xampp/htdocs/MS/reports_client_statement.php)
- [index.php](file:///c:/xampp/htdocs/MS/index.php#L12-L13)

Pattern:
```php
$mStart = new DateTime('first day of this month');
$mEnd   = new DateTime('last day of this month');
if ($df === '') $df = $mStart->format('d/m/Y');
if ($dt === '') $dt = $mEnd->format('d/m/Y');
```

### 11.3 Financial Report — `reports_financial.php?tab=financial`
- **5 KPI cards** (blue/green/red/amber/purple)
- **Revenue Trend Line Chart (12 months):** 3 series
  - Booked (solid blue)
  - Collected (solid green)
  - Expenses (**red dashed** with `borderDash: [6,4]` — required by project rules)
- Booking detail table with per-row Quoted/Collected/Pending
- Grand totals footer
- CSV export (UTF-8 BOM + totals summary rows)

### 11.4 Bookings Report — `reports_bookings.php` / `?tab=bookings`
- Aggregates by status, by client, by month
- Status distribution doughnut chart
- Filter: status / payment status / client / date range

### 11.5 Expenses Report — `reports_expenses.php` / `?tab=expenses`
- Per-type breakdown table
- Expense-by-type bar chart (top 6)
- Pivot: monthly × type grid
- Filter: expense type / date range / booking-linked vs general toggle

### 11.6 Inventory Report — `reports_inventory.php` / `?tab=inventory`
- Per-item-type utilisation: owned qty, booked qty, available qty, utilisation %
- Status distribution across all individual inventory items
- Low-stock / overbooked warnings

### 11.7 Client Statement — `reports_client_statement.php` / `?tab=client`
- Requires client selector dropdown
- Per-client statement: booking line items, payments, per-booking running balance, final total outstanding
- PDF/CSV print-friendly layout

### 11.8 CSV Export
All reports support `?export=csv` query string. Pattern:
```
Content-Type: text/csv; charset=utf-8
Content-Disposition: attachment; filename="financial_report_YYYYMMDD.csv"
```
With `\xEF\xBB\xBF` BOM for Arabic Excel compatibility.

---

## 12. Inventory Management

### 12.1 Three-Level Hierarchy

```
categories (10 seeded: Speakers, Subwoofers, DJ Controllers, ...)
  └── item_types (catalog models: JBL PRX812W, Pioneer XDJ-XZ, ...)
        └── inventory_items (individual serialised assets: SN#1, SN#2, ...)
```

### 12.2 Availability Engine — `getAvailableQuantity()`
[functions.php](file:///c:/xampp/htdocs/MS/includes/functions.php#L189-L197):

```
available = total_quantity_in_item_type − booked_quantity_in_date_range
```

Booked quantity counts any booking with status in the **reservation set**: `Quotation`, `Confirmed`, `Change Requested`, `Event Completed`, `Closed` — explicitly **excluding** `Canceled`.

Date overlap logic: `b.date_from <= $dateTo AND b.date_to >= $dateFrom` (full overlap test).

Optional `$excludeBookingId` param for edit-mode: "how many are available **not counting** this booking itself?"

### 12.3 AJAX Availability
[ajax_availability.php](file:///c:/xampp/htdocs/MS/ajax_availability.php) — XHR endpoint returning JSON for live forms.

### 12.4 Individual Assets — `inventory_items.php`
Tracks 7-state ENUM status (Available/Booked/Out for Event/Maintenance/Damaged/Lost/Retired), serial number, asset code, purchase date, location, notes.

### 12.5 Item Types
[item_types.php](file:///c:/xampp/htdocs/MS/item_types.php) — Catalog items with `default_rental_value` (auto-populated into `booking_items.rental_value`), `quantity` (the stock count used by availability engine), active flag.

---

## 13. Invoicing & Printing

[invoice.php](file:///c:/xampp/htdocs/MS/invoice.php) — A4-optimised grid-bordered invoice.

### 13.1 Print Constraints (Hard Rules)
- **Grid-style borders** required on invoices (inner + outer cell borders)
- `html, body { width: 100% }` + `.invoice-shell { margin: auto }` inside `@page` margins for A4 centering
- Print styles use `pt` units (not `px` or `em`) for precision
- Borders: inherited screen borders are disabled; `@media print` re-applies them explicitly
- Mobile (320px) headers kept single-line (wrapping forbidden)

### 13.2 Invoice Sections
1. **Brand:** Company name, tagline, address, VAT #, phone, email
2. **Recipient:** Client name, phone, alt-phone, email, address
3. **Invoice metadata:** Invoice # = booking_number, Invoice date, Due date (+7 days), Event period (days count)
4. **Line items grid:** Grouped by category, columns: #, Item, Qty, Rate, Subtotal, Notes
5. **Totals block:** Subtotal, DJ RAK Amount, Tax, Discount, Grand Total = Quoted Amount
6. **Payments received table:** Date, Method, Reference, Amount
7. **Amount Due:** Grand Total − Collected (bold, red if >0)
8. **Footer:** Payment instructions + thank-you text (bilingual via t())

---

## 14. WhatsApp Integration

### 14.1 Message Formatting (Critical Hard Rule)
WhatsApp messages **MUST NOT** display per-item or per-unit prices. Only:
```
- {qty} × {item_name}
...
*Total Quoted: JOD 1,500.00*
```
The bold `*Total Quoted*` is the ONLY price shown to the customer. This avoids confusion when `quoted_amount` is manually overridden (catalogue line totals don't add up to the custom quote).

Implemented in [functions.php](file:///c:/xampp/htdocs/MS/includes/functions.php#L255-L287) `buildWhatsAppMessage()` and the I18N-aware `buildWhatsAppMessageI18n()`.

### 14.2 Message Types

**1. Quotation / Confirmation Link**
```
Hello {client},

Here is your DJ equipment rental quotation from {company}:

Booking #BK2026080001
Date: 15/09/2026
Location: Golden Palace Venue

Equipment:
- 2 × JBL PRX812W
- 1 × Pioneer XDJ-XZ
- 4 × Shure SM58 (Wireless)

*Total Quoted: JOD 1,500.00*

Please confirm your booking by clicking the link below:
{confirm.php?token=xxx}

Thank you!
```

**2. Payment Reminder** (`buildWhatsAppReminderI18n`)
Includes: Booking #, Event Date, Quoted, Collected, **Pending (bold)**.

**3. Pending Balance Notice** (`buildPendingBalanceWhatsAppI18n`)
Short message with just the pending amount (bold) for client statement follow-ups.

### 14.3 Link Generation
All messages are URL-encoded and prefixed with:
```
https://wa.me/{sanitizePhone(client_phone, whatsapp_country_code)}?text={encoded_message}
```
`sanitizePhone()` normalises: strips non-digits, prepends `whatsapp_country_code` (default 966) for local numbers, removes `00` or `+` prefixes.

---

## 15. Audit Logging

[audit_logs.php](file:///c:/xampp/htdocs/MS/audit_logs.php) — Read-only viewer (requires `view_audit_logs` perm).

**Schema:** `user_id`, `action` (create/update/delete/login/cancel/confirm), `entity_type` (Booking/Client/Payment/Expense/User/Item/etc.), `entity_id`, `old_value` (JSON), `new_value` (JSON), `ip_address`, `user_agent`, `created_at`.

Called from every mutation:
```php
auditLog('update', 'Booking', $bookingId, $oldRow, $newRow);
auditLog('create', 'Client', $conn->lastInsertId(), null, ['name' => $x]);
auditLog('login', 'User', $user['id']);
```

---

## 16. System Settings

[settings.php](file:///c:/xampp/htdocs/MS/settings.php) + `system_settings` table (key-value store).

Hydrated into `SYS_*` PHP constants at bootstrap via `loadSystemSettings()`.  
Accessed via `getSetting('company_name', 'default')` which looks up `SYS_COMPANY_NAME`.

**Seeded keys:**
| Key | Default |
|-----|---------|
| `company_name` | DJ RAK Entertainment |
| `company_phone` | +966 50 000 0000 |
| `company_email` | info@djrak.com |
| `company_address` | Riyadh, Saudi Arabia |
| `currency_code` | JOD |
| `currency_symbol` | JOD |
| `date_format` | d/m/Y |
| `timezone` | Asia/Riyadh |
| `booking_prefix` | BK |
| `whatsapp_country_code` | 966 |

Additional runtime keys: `company_tagline`, `company_vat_no`, `company_cr_no`, `tax_rate`, `invoice_footer_text`, `payment_instructions`.

---

## 17. Frontend UI Framework

### 17.1 Layout: Sidebar + Content Wrapper
[includes/header.php](file:///c:/xampp/htdocs/MS/includes/header.php):
- Fixed sidebar (260px) with collapsible submenus (Inventory, Expenses, Reports, Setup)
- Top navbar: hamburger toggle, global search input, language switcher, user dropdown (Profile / Logout)
- Sidebar footer user card (desktop only)
- Mobile: sidebar overlay + `#sidebarCollapse` button (Bootstrap collapse JS)

### 17.2 CSS Assets
- `style.css` — Sidebar, nav items, cards, status badges, KPI tiles, invoice grid, print media queries, responsive breakpoints
- `rtl.css` — `html[dir="rtl"]` mirror overrides (float/text-align, margin swaps, equipment accent bar on right)
- BS4 gap utilities are not supported natively → use margin-based fallbacks instead

### 17.3 Plugins (all CDN)
- **Bootstrap 4.6.2** — grid, buttons, cards, modals, dropdowns, collapse
- **Font Awesome 5.15.4** — 1400+ icons (fas/far/fab)
- **Select2 4.1.0-rc.0** — Searchable dropdowns with Bootstrap 4 theme (client picker, category selects)
- **flatpickr** — Date + time pickers with `d/m/Y` format, range mode for date_from/to
- **jQuery 3.6.0** — Required by BS4 + Select2 + flatpickr

### 17.4 Responsive Baseline
Tested minimum: **320px mobile viewport**. Rules enforced:
- Sidebar collapses to off-canvas on <768px
- Filter rows stack vertically on <576px
- Tables wrapped in `.table-responsive` (horizontal scroll)
- KPI cards become 2-column on tablet, 1-column on mobile
- Headers remain **single-line** on 320px (no wrapping)

---

## 18. Engineering Conventions & Hard Constraints

### 18.1 MUST FOLLOW (Project Rules)
From [project_memory.md](file:///c:/xampp/htdocs/MS/../Users/HP/.trae/memory/projects/-c-xampp-htdocs-MS--p2-27618dbc27026d2190a2/project_memory.md):

**Booking & Payments:**
- `Quoted Amount` = `Σ(qty × rate) + DJ RAK Amount` by default — but **user can manually override** (do NOT auto-recompute on edit)
- `updateBookingPaymentStatus()` after every payment/booking mutation
- **WhatsApp messages:** NO line-item prices. Only `{qty} × {item}` lines + single bold *Total Quoted*
- `booking_id IS NULL` expenses are GENERAL. Never INNER JOIN bookings in expense SUM queries. Always include unlinked expenses.

**Reports:**
- **5 KPI cards are mandatory** on every financial view: Booked (Blue), Collected (Green), Expenses (Red), Pending (Amber), Net (Collected−Expenses)
- Report date filters **default to current month** (1st → last day, `DD/MM/YYYY`)
- Revenue trend charts **must include Expenses as red dashed line** (`borderDash: [6,4]`)
- Expense sums computed unconditionally (not IF-filtered behind date checkboxes)

**UI / RTL:**
- Invoices: grid-style borders (cells + outer), `@media print` with `pt` units
- A4 print: `html,body { width:100% }`, `.invoice-shell { margin:auto }` within `@page` margins
- Mobile 320px: headers MUST stay single-line; use margin-based fallbacks for BS4 gap utilities
- RTL: Equipment cards have right-side accent bars; `html[dir="rtl"]` CSS selectors

**Date Formats:**
- **UI (flatpickr):** `DD/MM/YYYY` (d/m/Y)
- **Database (MySQL):** `YYYY-MM-DD`
- Conversion: `DateTime::createFromFormat('d/m/Y', $input)->format('Y-m-d')`
- No `moment.js` anywhere (footer removed it)

**JS Compatibility:**
- Avoid ES2020 syntax (no optional chaining `?.`, no nullish `??`)
- Map 1-based `date('n')` months to 0-based array indices for calendar
- Search filters trigger `refreshAllCatToggles` after filter

### 18.2 Code Style
- All SQL uses **prepared statements** (PDO `prepare()` + `execute($params)`) — never string interpolation
- `e()` = `htmlspecialchars(ENT_QUOTES, UTF-8)` for ALL echo output (XSS prevention)
- `te()` = translate + escape (most common in templates)
- Flash messages via `setFlash('success'|'error'|'info', $msg)` / `flashMessages()`
- Timeout: 3600s session lifetime

---

## 19. Installation

### 19.1 Prerequisites
- PHP 7.4+ with extensions: `pdo_mysql`, `mbstring`, `json`
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with `mod_rewrite` (friendly URLs not strictly required)
- Write permissions for `MS/uploads/` directory

### 19.2 Steps

**Option A — Automated installer:**
1. Place `MS/` directory under web root (e.g. `htdocs/MS`)
2. Ensure MySQL server is running with default `root` / empty password (or edit `config.php`)
3. Navigate to: `http://localhost/MS/install.php`
4. Wait for success message → tables + seed data created
5. Log in at `http://localhost/MS/login.php` with:
   - Username: **admin**
   - Password: **admin123**
6. **IMMEDIATELY change the default admin password via Profile page!**

**Option B — Manual schema import:**
1. Import [database/schema.sql](file:///c:/xampp/htdocs/MS/database/schema.sql) into MySQL
2. Update DB credentials in [config.php](file:///c:/xampp/htdocs/MS/config.php) if non-default
3. Navigate to `http://localhost/MS/login.php`

### 19.3 Post-Install Checklist
- [ ] Change admin password
- [ ] Update System Settings → Company name, phone, email, currency, WhatsApp country code
- [ ] Verify uploads/ directory is writable (PHP sessions store there)
- [ ] Set PHP timezone to `Asia/Riyadh` in `php.ini` (also enforced in code)
- [ ] If HTTPS: update `SITE_URL` logic or hard-code it in config.php

---

## 20. File Reference Index

### Core
- [config.php](file:///c:/xampp/htdocs/MS/config.php) — Bootstrap (DB, URLs, session, settings, I18N)
- [install.php](file:///c:/xampp/htdocs/MS/install.php) — One-click installer
- [login.php](file:///c:/xampp/htdocs/MS/login.php) — Authentication
- [logout.php](file:///c:/xampp/htdocs/MS/logout.php) — Session destroy
- [index.php](file:///c:/xampp/htdocs/MS/index.php) — Dashboard (KPIs + charts)

### Includes
- [includes/db.php](file:///c:/xampp/htdocs/MS/includes/db.php) — PDO Database class
- [includes/functions.php](file:///c:/xampp/htdocs/MS/includes/functions.php) — All helpers (~60 funcs)
- [includes/header.php](file:///c:/xampp/htdocs/MS/includes/header.php) — Sidebar + top nav
- [includes/footer.php](file:///c:/xampp/htdocs/MS/includes/footer.php) — Closing tags + JS

### Database
- [database/schema.sql](file:///c:/xampp/htdocs/MS/database/schema.sql) — Full schema + seed data (14 tables, 4 roles, 20 perms, admin user, sample data)

### Languages
- [lang/en.php](file:///c:/xampp/htdocs/MS/lang/en.php) — English dictionary (~250 keys)
- [lang/ar.php](file:///c:/xampp/htdocs/MS/lang/ar.php) — Arabic dictionary
- [change_lang.php](file:///c:/xampp/htdocs/MS/change_lang.php) — Language toggle endpoint

### Bookings
- [bookings.php](file:///c:/xampp/htdocs/MS/bookings.php) — Booking list + filters
- [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php) — Create/Edit booking (equipment selector + avail. check)
- [booking_view.php](file:///c:/xampp/htdocs/MS/booking_view.php) — Booking detail view (tabs, payments, WA)
- [booking_action.php](file:///c:/xampp/htdocs/MS/booking_action.php) — Booking mutations (cancel, etc.)
- [confirm.php](file:///c:/xampp/htdocs/MS/confirm.php) — Public customer confirmation (token)

### Inventory
- [categories.php](file:///c:/xampp/htdocs/MS/categories.php) — Categories CRUD
- [item_types.php](file:///c:/xampp/htdocs/MS/item_types.php) — Item types (catalog) CRUD
- [inventory_items.php](file:///c:/xampp/htdocs/MS/inventory_items.php) — Serialised assets CRUD
- [ajax_availability.php](file:///c:/xampp/htdocs/MS/ajax_availability.php) — Live availability XHR

### Clients & CRM
- [clients.php](file:///c:/xampp/htdocs/MS/clients.php) — Client CRM

### Payments & Expenses
- [payments.php](file:///c:/xampp/htdocs/MS/payments.php) — Payment ledger
- [payment_action.php](file:///c:/xampp/htdocs/MS/payment_action.php) — Payment create
- [expense_types.php](file:///c:/xampp/htdocs/MS/expense_types.php) — Expense categories CRUD
- [expenses.php](file:///c:/xampp/htdocs/MS/expenses.php) — Expense ledger (booking-linked + general)

### Reports
- [reports_financial.php](file:///c:/xampp/htdocs/MS/reports_financial.php) — Financial report hub + main tab (KPIs + trend + CSV)
- [reports_bookings.php](file:///c:/xampp/htdocs/MS/reports_bookings.php) — Bookings report
- [reports_expenses.php](file:///c:/xampp/htdocs/MS/reports_expenses.php) — Expenses report
- [reports_inventory.php](file:///c:/xampp/htdocs/MS/reports_inventory.php) — Inventory utilisation report
- [reports_client_statement.php](file:///c:/xampp/htdocs/MS/reports_client_statement.php) — Per-client statement

### Calendar & Search
- [calendar.php](file:///c:/xampp/htdocs/MS/calendar.php) — Monthly booking calendar
- [calendar_download.php](file:///c:/xampp/htdocs/MS/calendar_download.php) — iCal (.ics) export
- [search.php](file:///c:/xampp/htdocs/MS/search.php) — Global search

### System
- [users.php](file:///c:/xampp/htdocs/MS/users.php) — User management + roles
- [profile.php](file:///c:/xampp/htdocs/MS/profile.php) — My profile
- [settings.php](file:///c:/xampp/htdocs/MS/settings.php) — System settings
- [audit_logs.php](file:///c:/xampp/htdocs/MS/audit_logs.php) — Audit trail viewer

### Financial Output
- [invoice.php](file:///c:/xampp/htdocs/MS/invoice.php) — Printable A4 invoice (grid-bordered)

### Assets
- [assets/css/style.css](file:///c:/xampp/htdocs/MS/assets/css/style.css) — Main stylesheet
- [assets/css/rtl.css](file:///c:/xampp/htdocs/MS/assets/css/rtl.css) — RTL overrides
- [assets/js/app.js](file:///c:/xampp/htdocs/MS/assets/js/app.js) — Client-side interactions

---

**End of Documentation**

*DJ RAK Inventory & Rental Management System — System Documentation v1.0*
