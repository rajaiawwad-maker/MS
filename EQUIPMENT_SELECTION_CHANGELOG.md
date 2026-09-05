# Awwad Event Management — Equipment Selection: Full Engineering Process & Changelog

> Project: **Awwad Event Management** (formerly DJ RAK) — PHP 8 + MySQL (PDO) + Bootstrap 4 + Font Awesome 5 + jQuery booking system running on XAMPP at `http://localhost/MS/`.
>
> Admin login: `admin / admin123`  *(location only — do not commit credentials to public repos)*.
>
> Compiled: 2026-08-30. Covers 4 sequential user-requested improvements + 2 runtime bugs discovered during verification.

---

## 1. Summary of all user requests completed

| # | User request (verbatim) | Status | Primary surface affected |
|---|---|---|---|
| 1 | *"Category headings should be bold and item selection should use checkboxes for easy selection."* | ✅ DONE | [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php) Equipment Selection HTML |
| 2 | *"whatsapp massage & Confitmation to client should not contain the Equipment Defult Rate only the quoated amount"* | ✅ DONE | [includes/functions.php](file:///c:/xampp/htdocs/MS/includes/functions.php) (2 WA builders) + audit of [confirm.php](file:///c:/xampp/htdocs/MS/confirm.php) + [invoice.php](file:///c:/xampp/htdocs/MS/invoice.php) |
| 3 | *"make Equipment Selection in one box and fix the design to be easy to be choose by the user"* | ✅ DONE | [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php#L228-L359) HTML + [assets/css/style.css](file:///c:/xampp/htdocs/MS/assets/css/style.css#L332-L466) CSS + [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php#L586-L724) JS |
| 4 | *"fix the mobile broswer view"* | ✅ DONE | [style.css:420-466](file:///c:/xampp/htdocs/MS/assets/css/style.css#L420-L466) mobile `@media` block + BS4 gap fallback + 1 tiny HTML class in [booking_form.php:233](file:///c:/xampp/htdocs/MS/booking_form.php#L233) |

---

## 2. Task 1 — Bold categories + checkbox selection

### 2.1 Problem
- Equipment categories displayed with the same visual weight as items; hard to visually group.
- Items were shown as clickable table rows or cards without a real `<input type="checkbox">`; user had to click a tiny unknown region to toggle.

### 2.2 Changes applied
| File | Changes |
|---|---|
| [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php) | (a) Wrap category name in `<span class="eq-category-name" style="font-weight:700">` → **bold categories**; (b) Rewrote each item row as a **card + real checkbox** (`.eq-item-check`) with label `[✓] Item Name`; (c) qty/rv inputs `.prop('disabled')` until checkbox checked; (d) Added `.eq-cat-toggle` **per-category select-all checkbox** with 3-state `indeterminate`. |
| [style.css](file:///c:/xampp/htdocs/MS/assets/css/style.css) | Styling for bold category headers, checkbox scale, disabled inputs greyed out. |
| [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php) JS | New `syncHiddenRows()` rebuilds POST arrays `item_type_id[]`, `quantity[]`, `rental_value[]` from checked UI state + `applyItemCheckState()` enables/disables qty/rv, restores `default_rental_value` on first-check, and `recalc()` updates summary `#equipmentCount` (number of items) + `#equipmentSubtotal` (sum of qty × rv). |

### 2.3 Verification (✓)
- Power Cable (id=18) qty=3 rv=25 + SM58 Wired (id=11) qty=2 rv=20 → count=5, subtotal=115.00 JOD, hidden POST rows=2. ✅
- Lighting (id=6, 3 items) → cat-toggle checked=3/3, indeterminate=false ✅

---

## 3. Task 2 — WA / Confirmation never expose catalog default rental rate

### 3.1 Problem
- WhatsApp quotation message built items like:
  ```
  - 4 × Power Cable (5m) (JOD 75.00)
  - 2 × Pioneer CDJ-3000 (Pair) (JOD 200.00)
  ```
  This always showed the **per-unit saved rate** `rental_value` identical to the catalog `default_rental_value`, so customers thought they were seeing a default template price rather than the negotiated quote subtotal.
- Staff worried customers would compare the per-unit numbers to competitors' *per-item* published prices even after we'd given custom bulk discounts.

### 3.2 Investigation
Scanned all client-facing surfaces for `default_rental_value` vs `rental_value` exposure:

| Surface | Exposes per-unit rate? | Line(s) | Verdict |
|---|---|---|---|
| WA legacy builder `buildWhatsAppMessage()` | ✅ OLD YES (changed to NO) | [functions.php:267-280](file:///c:/xampp/htdocs/MS/includes/functions.php#L267-L280) | 🔧 FIXED |
| WA i18n builder `buildWhatsAppMessageI18n()` | ✅ OLD YES (changed to NO) | [functions.php:430-443](file:///c:/xampp/htdocs/MS/includes/functions.php#L430-L443) | 🔧 FIXED |
| Customer `confirm.php` booking confirmation | ❌ Already correct — shows `rental_value × qty` line subtotal only | [confirm.php:155-162](file:///c:/xampp/htdocs/MS/confirm.php#L155-L162) | ✅ NO CHANGE |
| Client `invoice.php` printable invoice | ❌ Already safe — table has **no Rate column**; only Line Total rendered | [invoice.php:494-521](file:///c:/xampp/htdocs/MS/invoice.php#L494-L521) | ✅ NO CHANGE |
| Staff `booking_view.php` WA href generator | ❌ Maps saved `bi.rental_value` correctly | [booking_view.php:L54-66](file:///c:/xampp/htdocs/MS/booking_view.php#L54-L66) | ✅ NO CHANGE |

### 3.3 Fix in both WA builders
**OLD pattern** (both functions):
```php
$lines[] = "- {$qty} × {$name}" . (!empty($it['rental_value']) ? " ({$currency} " . number_format($it['rental_value'], 2) . ")" : '');
```
**NEW pattern**:
```php
$lineTotal = (float)$it['rental_value'] * (int)$qty;
$lines[] = "- {$qty} × {$name} = {$currency} " . number_format($lineTotal, 2);
```

### 3.4 Verification (✓ on real DB data)
- **Booking BK2026080005 (id=5)**: 2 saved booking_items —
  - qty=4 Power Cable (saved rv=75 → 4×75 = 300)
  - qty=2 Pioneer CDJ-3000 (Pair) (saved rv=200 → 2×200 = 400)
- Decoded WA href text on `booking_view.php?id=5&wa=1`:
  ```
  Equipment:
  - 4 × Power Cable (5m) = JOD 300.00
  - 2 × Pioneer CDJ-3000 (Pair) = JOD 400.00
  ```
  ✅ No **per-unit** `(JOD 75.00)` / `(JOD 200.00)` visible anymore.
- Arabic AR booking (BK1, older): decoded WA message showed identical quoted subtotals (no per-unit rate). ✅

---

## 4. Task 3 — Equipment Selection in ONE unified single box + UX improvements

### 4.1 Problem
Equipment Selection had **10 separate bordered card boxes** (one per category). On a laptop screen this looked like 10 distinct forms. Staff had to scroll back up to find the search (none existed), find category controls in different boxes, and overall it felt overwhelming. Staff wanted ONE big unified list with search and bulk actions at the top.

### 4.2 Design spec written & implemented

**Visual hierarchy (inside ONE outer `.eq-selection-box` card):**
1. **Gradient header** (`linear-gradient(135deg, #f8fafc, #eef2f7)`) containing:
   - LEFT: `🅵 Equipment Selection` + small hint "Tick items to add..." (hidden on mobile)
   - RIGHT: **Inline toolbar** — search input, `[All Visible]`, `[Clear All]`
2. Inside card body → 10 `.eq-category-block.eq-inner` sections stacked inside ONE box
   - NO individual rounded borders per category! Only thin 1px `#eef1f5` divider between cats.
   - Each category header: 3px solid `#0b5ed7` **left accent bar** (→ swaps to **right** on `dir=rtl` Arabic) + bold category name + (visible count badge) + 3-state select-all checkbox + `▾` collapse chevron button.
3. Under each category header: `.eq-items-grid` row with Bootstrap responsive column classes:
   `col-xl-3 col-lg-4 col-md-4 col-sm-6 col-12` = **4/3/3/2/1 column layout** across 1200/992/768/576/0 breakpoints.
4. Each item: full clickable card with hover lift and selected highlight.

### 4.3 Interaction features added
| Feature | JS/CSS | Lines |
|---|---|---|
| **Unified single box** (no 10 separate boxes) | HTML + CSS new classes | [booking_form.php:228-359](file:///c:/xampp/htdocs/MS/booking_form.php#L228-L359) / [style.css:332-418](file:///c:/xampp/htdocs/MS/assets/css/style.css#L332-L418) |
| **Toolbar Search** `#equipmentSearch` — live filters items by `data-name` OR `data-cat-name`; hides non-matching `.eq-item-wrap` and entire categories with zero visible items; shows dashed empty-state "No equipment matches your search." when 0 results | JS `applyEquipmentFilter(q)` + `.eq-hidden {display:none}` + `.eq-no-results` dashed box | [booking_form.php:604-632](file:///c:/xampp/htdocs/MS/booking_form.php#L604-L632) |
| **Select All Visible** `.eq-select-all-visible` button — checks every item that is currently **non-hidden** (respects search) and NOT collapsed; triggers all side-effects | Delegated handler | [booking_form.php:700-708](file:///c:/xampp/htdocs/MS/booking_form.php#L700-L708) |
| **Clear All** `.eq-clear-all` button — unchecks every item in panel, clears summary to 0 / 0.00 | Delegated handler | [booking_form.php:710-715](file:///c:/xampp/htdocs/MS/booking_form.php#L710-L715) |
| **Whole card click toggles checkbox** | Delegated `.eq-item-card` click handler (safely bails out if you click inputs/buttons/links) | [booking_form.php:650-661](file:///c:/xampp/htdocs/MS/booking_form.php#L650-L661) |
| **Per-category collapse/expand** `.eq-cat-collapse-btn` → toggles `collapsed-category` class; CSS swaps chevron glyph `\f077` ↔ `\f078` | Handler + `::before` CSS font icon swap | [booking_form.php:663-670](file:///c:/xampp/htdocs/MS/booking_form.php#L663-L670) / [style.css:399-401](file:///c:/xampp/htdocs/MS/assets/css/style.css#L399-L401) |
| **Category 3-state toggle that EXCLUDES hidden items** | Rewrote `refreshCatToggle(catId)` to skip `.eq-hidden` wraps when counting "all" / "checked" / total. Also new helper `refreshAllCatToggles()` loops all 10 cats | [booking_form.php:586-602](file:///c:/xampp/htdocs/MS/booking_form.php#L586-L602) |
| **Category checkbox click only selects VISIBLE (search-filtered) items** | Rewrote `.eq-cat-toggle` change handler to only toggle VISIBLE non-hidden wraps in its block | [booking_form.php:672-689](file:///c:/xampp/htdocs/MS/booking_form.php#L672-L689) |
| **Hover lift + selected blue glow** | CSS: `translateY(-1px)`, blue shadow, selected blue gradient background + 1px inset glow | [style.css:377-386](file:///c:/xampp/htdocs/MS/assets/css/style.css#L377-L386) |

### 4.4 Files written in Task 3
```
booking_form.php  L228-L359   HTML (unified card + toolbar + grid)
booking_form.php  L586-L724   JS (all new filter/collapse/toggle)
style.css         L332-L418   CSS (unified box, grid, hover, selected, collapse)
lang/en.php       L257-L263   i18n keys (bk.search_items_ph / select_all_short / clear_all_short / no_items_match / visible_items)
lang/ar.php       L252-L258   i18n keys (same, Arabic translations)
```

### 4.5 Runtime bugs DISCOVERED during Task 3 verification & fixed

#### 🐛 Bug A: `refreshAllCatToggles is not defined`
- Root cause: during the code reshuffle I replaced the block containing `refreshCatToggle()` AND accidentally deleted the sibling `refreshAllCatToggles()` that `applyEquipmentFilter()` calls.
- Fix: add the missing helper back at [booking_form.php:597-602](file:///c:/xampp/htdocs/MS/booking_form.php#L597-L602):
  ```js
  function refreshAllCatToggles() {
      $('.eq-cat-toggle').each(function() {
          var catId = $(this).data('cat');
          refreshCatToggle(catId);
      });
  }
  ```

#### 🐛 Bug B: Card click non-label regions never toggled anything (Power Cable click = false)
- Root cause: the guard condition in `.eq-item-card` click handler said "if click is nearest to input/button/select/textarea/a/**label** → return". This meant: if you click anywhere inside `<label class="eq-item-label">` it would bail out (good, label does native toggle — but the label wraps almost the entire TOP of the card so clicks there are fine). But **everything outside the label** (`avail-info` region at bottom, `eq-item-controls` disabled region when unchecked) had NO toggle mechanism — because our own guard excluded them.
- Fix: **Simplify the guard** — only skip native HTML interactive elements; and separately, if click is inside `<label>`, don't double-toggle (let label do its native job), otherwise toggle programmatically. Rewrote handler at [booking_form.php:650-661](file:///c:/xampp/htdocs/MS/booking_form.php#L650-L661).
- Verified: click on Power Cable `.avail-info` → toggled ON ✅, clicked again on `.eq-item-controls` → toggled OFF ✅.

### 4.6 EN interaction test results (7/7 ✅)
| Test | Expected | Got |
|---|---|---|
| Search "shure" | 2 Microphone items visible, rest hidden | 2 visible / 18 hidden ✅ |
| Select All Visible | 20 checked + 20 POST rows + sum=6595.00 JOD | 20 / 20 / 6595.00 ✅ |
| Clear All | 0 checked / 0 rows | 0 / 0 ✅ |
| Click Power Cable card (non-label region) | before=false → after=true | false → true ✅, qty/rv enabled, highlight blue applied |
| Second click on same card | toggles to false | OFF ✅ |
| Filter "shure" → click cat-toggle on the 1 visible Microphones category | 2 Shure items checked, 0 other items hidden outside touched | 2 checked ✅ |
| Collapse Accessories (last category) | `collapsed-category` class applied, items hidden | applied ✅ |

### 4.7 AR RTL interaction test results (13/13 ✅)
- `html dir=rtl lang=ar` applied correctly ✅
- **Category accent bar on RIGHT side** (`border-right: 3px solid #0b5ed7`) ✅ — mirror of LTR left bar ✅
- All labels/buttons rendered Arabic:
  - Header: `اختيار المعدات` ✅
  - Buttons: `تحديد المرئي` / `مسح الكل` ✅
  - Placeholder: `ابحث في الأصناف بالاسم أو الفئة…` ✅
- Search "shure" → 2 visible items ✅
- First card `.avail-info` click → false → true + highlight ✅
- Select All → 20 checked, 20 POST rows; Clear → 0 checked ✅
- First category collapse → class applied ✅

---

## 5. Task 4 — Fix mobile viewport (320 / 375 / 425 px)

### 5.1 Bugs discovered at 320px (simulated small Android) via injected viewport style:
| Bug | Measurement |
|---|---|
| Equipment header **139px tall** (3 wrapped lines — title, search, buttons) | headerH=139 ✗ |
| Search min-width 180 → can't fill 320px row cleanly | searchW=242 sum 566 vs headerW=270 diff +214px overflow layout ✗ |
| **All 10 category collapse buttons wrapped to 2nd line** (long names like "Cables & Connectors (3)") | wraps=10/10, tallest=48px ✗ |
| `gap-2` spacing class **HAS NO EFFECT IN BOOTSTRAP 4** (only BS5 has gap utilities) — toolbar buttons & controls had zero inter-element spacing | visually squished ✗ |
| 17px left item-list indent is 7% of 320px viewport → card content too narrow | cards 239px wide inside 311px container ✗ |
| Qty/rv inputs too tall for 240px cards | input height unmeasured, waste of vertical ✗ |

### 5.2 Fixes applied
| Fix | Location |
|---|---|
| **Bootstrap 4 gap fallback** (global desktop+mobile) for both toolbar + controls: adjacency rule `> * + *` margin-left=0.5rem; RTL mirror rule | [style.css:341-343](file:///c:/xampp/htdocs/MS/assets/css/style.css#L341-L343) + [style.css:394-396](file:///c:/xampp/htdocs/MS/assets/css/style.css#L394-L396) |
| **Category label flex + shrink collapse btn**: `.eq-cat-label { flex:1 1 auto; min-width: 0; }` so name wraps instead of pushing collapse to new row; `.eq-cat-collapse-btn { flex-shrink: 0 }` | [style.css:360](file:///c:/xampp/htdocs/MS/assets/css/style.css#L360) + [style.css:365](file:///c:/xampp/htdocs/MS/assets/css/style.css#L365) |
| **New `@media (max-width: 575.98px)` block** — 46 lines: | [style.css:420-466](file:///c:/xampp/htdocs/MS/assets/css/style.css#L420-L466) |
| - Hide hint `.eq-select-hint { display:none }` on mobile | added `class="eq-select-hint"` to hint span | [booking_form.php:233](file:///c:/xampp/htdocs/MS/booking_form.php#L233) |
| - Force title-span + toolbar to `width: 100%` each → 3 clean rows (title / search / All+Clear) | [style.css:423-436](file:///c:/xampp/htdocs/MS/assets/css/style.css#L423-L436) |
| - Search 100% width on mobile, kill min/max clamp | [style.css:428-433](file:///c:/xampp/htdocs/MS/assets/css/style.css#L428-L433) |
| - Cat name font 0.88rem + toggle scale(1.02) smaller, chevron 0.8rem, header padding 4/6/4/8 | [style.css:438-446](file:///c:/xampp/htdocs/MS/assets/css/style.css#L438-L446) |
| - Item-list padding 2/2/8/6 (LTR) and 2/6/8/2 (RTL) — saved ~11px horizontal | [style.css:444-445](file:///c:/xampp/htdocs/MS/assets/css/style.css#L444-L445) |
| - Inputs compact: height `calc(1.5em + .5rem + 2px)` → 32px (was ~38) — labels font 0.75rem | [style.css:451-455](file:///c:/xampp/htdocs/MS/assets/css/style.css#L451-L455) |
| - `.col-6.px-1` → `padding-left:2px`; row margin-left/right -2px (max qty/rv column width inside card) | [style.css:456-457](file:///c:/xampp/htdocs/MS/assets/css/style.css#L456-L457) |
| - avail-info font 0.75rem | [style.css:463](file:///c:/xampp/htdocs/MS/assets/css/style.css#L463) |
| - no-results box tighter, smaller | [style.css:465](file:///c:/xampp/htdocs/MS/assets/css/style.css#L465) |

### 5.3 Mobile verification results (3 simulated widths)
| Width (px) | Header height | Cat collapse 1-line (10/10?) | Horizontal offenders (>width+3) |
|---|---|---|---|
| **320** (small Android) | 124px (was 139px, Δ-15) | **10/10 same-line ✅** (was 0/10) | 0 (sidebar-only cosmetic) |
| **375** (iPhone SE/X) | 124px | **0 wraps / 10 ✅** | 0 ✅ |
| **425** (Pixel XL) | 124px | **0 wraps / 10 ✅** | 0 ✅ |

**AR RTL mobile 320/375 also 100% passes:**
- accent bar RIGHT side (3px) ✅, Arabic UI text ✅, card-click toggle ✅, search shure 2 items ✅, All 20 rows / Clear 0 ✅.

---

## 6. Final complete list of files modified (final diff set)

| File | Lines changed | Purpose |
|---|---|---|
| [booking_form.php](file:///c:/xampp/htdocs/MS/booking_form.php) | L228-359 HTML, L490-632 JS + L586-724 new JS, 2 runtime bug fixes | Equipment Selection HTML/JS (check boxes → cards → unified box → search → collapse → card click → filters) + one tiny `eq-select-hint` class addition |
| [includes/functions.php](file:///c:/xampp/htdocs/MS/includes/functions.php) | L255-442 (inside 2 WA builders) | Both WhatsApp builders render QUOTED per-line subtotal `= JOD 300.00` instead of per-unit `(JOD 75.00)` |
| [assets/css/style.css](file:///c:/xampp/htdocs/MS/assets/css/style.css) | L328-466 (entire equipment + mobile sections) | Unified single-box CSS, RTL, hover/selected states, collapse, grid, Bootstrap 4 gap fallback, mobile @media block 46 lines |
| [lang/en.php](file:///c:/xampp/htdocs/MS/lang/en.php) | L257-263 | 6 EN i18n keys for toolbar / search / no-results |
| [lang/ar.php](file:///c:/xampp/htdocs/MS/lang/ar.php) | L252-258 | Same 6 keys translated to Arabic |

### Files audited, NO changes needed (already correct):
- [confirm.php](file:///c:/xampp/htdocs/MS/confirm.php#L153-L162) — customer confirmation page
- [invoice.php](file:///c:/xampp/htdocs/MS/invoice.php#L494-L521) — client invoice (no "Rate" column!)
- [booking_view.php](file:///c:/xampp/htdocs/MS/booking_view.php#L54-L66) — WA href generator
- [change_lang.php](file:///c:/xampp/htdocs/MS/change_lang.php) — language switcher mechanism

---

## 7. Reproduction / QA checklist for future deployments

### Step 1 — Server side
1. Ensure XAMPP Apache + MySQL running.
2. `cd c:\xampp\htdocs\MS && php -l booking_form.php && php -l includes\functions.php && php -l lang\en.php && php -l lang\ar.php` → should report **No syntax errors** for all 4.
3. IDE `GetDiagnostics` → should be empty `[]`.

### Step 2 — Desktop (≥992px width) EN
1. Log in: http://localhost/MS/login.php → admin/admin123.
2. Go to Create Booking: http://localhost/MS/booking_form.php.
3. Confirm: ONE outer Equipment Selection box with gradient header; search to right of "Equipment Selection"; below that 10 stacked categories inside same box; each has bold name + blue left bar + 3-state toggle + chevron up; below each 4-per-row card grid on ≥1200px.
4. Click **anywhere on Power Cable card** (not the checkbox itself — try the "Check availability" text) → turns blue, qty/rv inputs now enabled. Set Qty=4, Rate=6.00.
5. In toolbar, type **shure** into search → only Microphones category shows 2 cards (SM58 Wired, SM58 Wireless).
6. Click the Microphones category checkbox → only the 2 visible Shure items get checked (not 3/3 which is correct: the 3rd hidden mic should be skipped).
7. Erase search → now see all 3 mics: 2 checked, 1 not → category toggle shows **indeterminate** `-` state (CORRECT).
8. Click **All Visible** → 20 checked, Equipment Summary count=20, Subtotal=6595.00 JOD, POST hidden rows=20.
9. Click **Clear All** → 0 checked, count=0, subtotal=0.00, POST rows=0.
10. Click **chevron ▴** next to any category → items collapse, chevron CSS flips to ▾. Click again → expanded.

### Step 3 — Desktop AR (Arabic RTL)
1. Switch language via top-right dropdown (or visit: http://localhost/MS/change_lang.php?lang=ar).
2. Confirm Equipment Selection header is Arabic (`اختيار المعدات`).
3. All category headers show blue accent bar on RIGHT side.
4. Repeat any 3 of EN tests — they should all pass identically (with Arabic button labels).

### Step 4 — Mobile widths (320 / 375 / 425)
Use Chrome DevTools Device Toolbar (F12 → Ctrl+Shift+M) or Firefox Responsive Mode. For each width confirm:
- Header ≤ ~125px height (3 rows, no 4th); hint span hidden.
- Search 100% full width.
- All 10 cat headers show name + chevron on the **same line** (no 2-line wrap for collapse button).
- Cards 1 per row, qty/rv side-by-side inside card, inputs compact 32px height.
- No horizontal scrolling (swipe right/left should not reveal blank space).
- All buttons/chevrons tappable (≥40px tap region — card tap region is 240×140px so excellent).

### Step 5 — WhatsApp quotation (critical privacy fix regression test)
1. Log in → Bookings list → open BK2026080005 (M. farouq, 2026-08-29).
2. Click the **green WhatsApp** button on Staff booking view.
3. Hover / right-click + Inspect the href attribute → should decode to:
   ```
   - 4 × Power Cable (5m) = JOD 300.00
   - 2 × Pioneer CDJ-3000 (Pair) = JOD 400.00
   ```
   ✅ PASS. If you see per-unit `(JOD 75.00)` anywhere → FAIL (regression, recheck functions.php WA builders L267-280 and L430-443).

---

*End of process / changelog document. Next document: `USER_MANUAL_EQUIPMENT_SELECTION.md` (bilingual EN/AR end-user instructions).*
