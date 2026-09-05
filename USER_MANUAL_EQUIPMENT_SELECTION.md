# User Manual — Equipment Selection in Create Booking
# دليل المستخدم — اختيار المعدات في إنشاء الحجز

> **Awwad Event Management** | **نظام عواد لإدارة الحفلات**
>
> This manual is bilingual: **PART 1** = English / **الجزء الأول** = الإنجليزية, then **PART 2** = Arabic / **الجزء الثاني** = العربية.
>
> هذا الدليل ثنائي اللغة: الجزء الأول بالإنجليزية والجزء الثاني بالعربية.

---

# ════════════════════════════════════════════
# PART 1 — ENGLISH USER MANUAL
# ════════════════════════════════════════════

---

## 1. What is Equipment Selection?

When you create a **Booking** (حجز) for a client event (wedding, DJ set, corporate party, etc.), the **Equipment Selection** panel is where you **pick every physical piece of gear** the client will rent for that event.

Example items you can select:
- Speakers / Subwoofers / Microphones
- DJ CDJ players + DJ mixers + DJ Controllers
- Lighting (moving heads, PAR bars, lasers, uplights)
- Cables (power 5m, XLR, RCA, HDMI)
- Stands (speaker stands, mic stands, laptop stands)
- DJ Furniture (booths, tables, facade)
- Accessories (flight cases, covers, tape)

When you check an item, you can set:
1. **Quantity** — how many of that item the client will rent.
2. **Quoted Rental Rate** — the AGREED per-unit rate for THIS booking (can be different from the catalog default; for example a bulk discount).

The panel automatically shows:
- 📋 **Equipment Summary** (right sidebar or stacked below on mobile)
  - **Number of Items** — total physical items (sum of all quantities)
  - **Equipment Subtotal** — sum of `(Qty × Quoted Rate)` for every checked item

---

## 2. How to open Equipment Selection

1. Open your browser and go to: **http://localhost/MS/login.php**
2. Log in with your admin username + password.
3. In the left menu, click **Bookings** → **Create Booking**.
4. On the Create Booking page, fill in the **Client**, **Event Date**, **Start/End Time**, and **Location** first.
5. The **Equipment Selection** panel lives **below the Event details** in its own single large card with a gradient header:
   > 🅵 **Equipment Selection** · Tick items to add them to this booking · [🔍 Search…] · [⊡ All Visible] · [⌫ Clear All]

---

## 3. Anatomy of the Equipment Selection panel

```
╔══════════════════════════════════════════════════════════════════╗
║ 🅵 Equipment Selection  Tick items…  [🔍 Search… ] [⊡All] [⌫Clear] ← GRADIENT HEADER + TOOLBAR (one box!)
╠══════════════════════════════════════════════════════════════════╣
║ ☑ 🎤  Microphones  (3)  ───────────────────────────────────  ▴ ← CATEGORY HEADER (bold + blue bar left)
║ ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐    │
║ │☑ SM58 Wired     │  │☑ SM58 Wireless  │  │☑ Sennheiser E835│    │ ← 4-PER-ROW GRID (on desktop)
║ │   JOD 20.00     │  │   JOD 40.00     │  │   JOD 30.00     │    │   Card shows: [ ] check + name
║ │ ✅ Available: 12│  │ ✅ Available: 6  │  │ ✅ Available: 8  │    │            default rate badge
║ │ [Qty] [Rate]   │  │ [Qty] [Rate]    │  │ [Qty] [Rate]    │    │            Available count
║ │  [2] [20.00]   │  │  [1] [40.00]    │  │  [0] (disabled) │    │            Qty / Rate inputs
║ │Check availability│  │Check availability│ │Check availability│    │            Availability hint row
║ └─────────────────┘  └─────────────────┘  └─────────────────┘    │
║ ☑ 💡  Lighting  (3)  ─────────────────────────────────────  ▴     │ ← NEXT CATEGORY, SAME BOX
║ ...                                                                  │
╚══════════════════════════════════════════════════════════════════╝
```

### Panel parts explained

| Part | What it does |
|---|---|
| **Gradient Header** (the ONE unified card header) | Title of the panel + toolbar (search + All/Clear buttons). This is the ONLY outer bordered box for ALL 10 categories. No more 10 separate boxes! |
| **Search box 🔍** | Type the item NAME (e.g. `shure`, `Pioneer CDJ-3000`) or CATEGORY NAME (e.g. `lighting`, `cables`) to instantly filter. Non-matching items + categories HIDE; shows "No equipment matches your search." if 0 results. |
| **[All Visible] button** | Checks EVERY item currently **shown** (respects current search filter). Use with search to e.g. type `cables` → click All → all cable types selected at once. |
| **[Clear All] button** | Unchecks EVERYTHING in the panel; resets summary count and subtotal to 0. |
| **Category header** (one per category, 10 total) | Bold category name, `(count)` of visible items in category, **3-state checkbox** to select all items in that category, and a **chevron ▴** to collapse/expand the list. |
| **Category 3-state checkbox** | `☐` = none checked / `▤` = some checked (indeterminate "-" symbol) / `☑` = **all VISIBLE** items in this category checked. Click to toggle. |
| **Category chevron ▴ / ▾** | Click to fold long lists. Collapsed = items hidden; chevron flips to ▾ via CSS. |
| **Item card** (20 total, one per catalog item) | The small rounded selectable card with border, hover lift, blue glow when selected. **CLICK ANYWHERE ON THE CARD** to toggle (no need to aim tiny checkbox!). |
| **Qty input** | Number of units client wants for that item. Default = 1 when you first check. |
| **Rate (per-unit rental)** | Agreed per-unit rate in JOD. Default = catalog default price when you first check; **you should edit this to the NEGOTIATED quote per-item for the booking BEFORE sending to client!** |
| **Availability** badge (green / amber / red `Available: N`) | How many we have in stock right now (read-only info — does not block booking, just warning info). |
| **"Check availability" hint row** | You can click HERE (bottom 8px of the card) to toggle the card ON/OFF — this is the EXACT region we fixed to be clickable! |

---

## 4. How to select equipment (step by step)

### Step 1: Fill in booking details FIRST (required!)
Before touching equipment, choose **Client**, **Event Date**, **Start / End Time**, **Location**, **Status** (usually Quotation first, then later Confirmed when deposit paid).

### Step 2: Find items to book — 3 methods (choose fastest!)

#### Method A: SCAN + CLICK CARDS (fast for a standard package)
Just scroll the 10 categories; **click anywhere on the card for every item you want**.
- Best for: standard events (e.g. a DJ set with 2x CDJ-3000, 1x DJM-A9, 2x top speakers, 2x subs, cables, stands).
- Remember: you don't have to click the little square checkbox. Card body, name, even the "Check availability" row ALL toggle the item.

#### Method B: SEARCH + SELECT (fast for 1-2 specific items)
1. Click into **🔍 Search** field in toolbar.
2. Type an item name, e.g. type `shure` → instantly panel shows ONLY the 2 Shure microphones. The other 9 categories + other 18 items are HIDDEN.
3. Click the Microphones category **☑** → BOTH Shure items get checked (the 3rd mic is hidden and NOT touched, so non-Shure mic remains unchecked — CORRECT behaviour).
4. Erase the search text to go back to full panel. The hidden mic that was not checked stays unchecked AND the Microphones category checkbox correctly shows **▤ indeterminate (some)**.

#### Method C: BULK SELECT A WHOLE CATEGORY (e.g. All Cables)
1. Click the category checkbox next to **Cables & Connectors**.
2. All 3 cable types inside light up as selected.
3. If you want to then UNCHECK only one cable you don't need, click its card → category checkbox flips to **indeterminate**.

### Step 3: For each checked item, edit Qty + quoted Rate
Every checked card now has **Qty** and **Rate** inputs enabled (grey disabled cards mean item unchecked).

Example for a wedding DJ package:
| Item | Qty | Rate (per unit, JOD) | Why edit? |
|---|---|---|---|
| Pioneer CDJ-3000 (Pair) | 2 | 200.00 | Default was 200.00 — OK, no discount. |
| Pioneer DJM-A9 4ch DJ Mixer | 1 | 120.00 | Default was 150.00 — give client bulk package discount. |
| RCF HDL 20-A Line Array Top | 4 | 180.00 | |
| RCF SUB 8006-AS | 2 | 220.00 | |
| Shure SM58 Wired | 3 | 18.00 | |
| Power Cable (5m) | 8 | 5.00 | |
| Speaker Stand (Heavy Duty) | 4 | 10.00 | |

The panel instantly recalculates:
- Number of items: `2 + 1 + 4 + 2 + 3 + 8 + 4 = 24`
- Equipment subtotal: `(2×200)+(1×120)+(4×180)+(2×220)+(3×18)+(8×5)+(4×10)` = `400+120+720+440+54+40+40 = 1 814.00 JOD`

### Step 4 (optional): Collapse long categories to reduce scrolling
If you've chosen all Speakers and want to scroll to Cables without passing the full 20-item list, click **▴ chevron** on Speakers → list collapses. Click again ▾ to expand.

### Step 5: Double-check the Equipment Summary
Look at the right-sidebar Summary box (or stacked **below** equipment on mobile):
```
━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Summary
━━━━━━━━━━━━━━━━━━━━━━━━━
Number of items     24
Equipment Subtotal  JOD 1,814.00
━━━━━━━━━━━━━━━━━━━━━━━━━
```
If anything looks wrong, click back into equipment and edit.

### Step 6: Finish the rest of the booking form
Add **Discount %**, **Deposit paid**, **Internal notes** (never sent to client), **Customer notes** (sent to client on confirmation). Then click **Save Booking**.

---

## 5. All keyboard + mouse shortcut tips (faster booking entry!)

| Action | Shortcut |
|---|---|
| Select item | **Click anywhere on card** (name, badge, avail row, padding — all work!) |
| Bulk select a category | Click the **big category checkbox** |
| Bulk select SEARCH results only | Type search first → click **[All Visible]** |
| Bulk select one specific category with current filter | Type search first → click that ONE category checkbox |
| Undo (clear a single item) | Click card AGAIN anywhere → untoggles |
| Undo (clear everything) | Click **[Clear All]** button (top right of equipment panel) |
| Fold a long category to scroll faster | Click **▴ / ▾ chevron** on right side of category header |
| Know how many are selected at any time | Look at **Number of Items** in Summary box right side |

---

## 6. Send to customer — what the client sees (PRIVACY note!)

After you save the booking, click the **green WhatsApp** button on the Booking View page.

**IMPORTANT PRIVACY / PRICING CHANGE (implemented 2026-08):**
The old WhatsApp message used to show a **per-unit rate** in parentheses, for example:
```
- 4 × Power Cable (5m) (JOD 75.00)   ❌ OLD BAD BEHAVIOUR — exposed per-unit rate
```

This was confusing to clients (they compared single unit prices to competitors). Now, the message shows **QUOTED LINE TOTAL ONLY**:
```
- 4 × Power Cable (5m) = JOD 300.00   ✅ NEW CORRECT BEHAVIOUR
- 2 × Pioneer CDJ-3000 (Pair) = JOD 400.00
Equipment subtotal: JOD 700.00
```

Same rule on:
- **Customer Confirmation page (`confirm.php`)** — sent via email/SMS link
- **Client Printable Invoice (`invoice.php`)** — NO "Rate" column at all! Only Qty and Line Total columns.

Your negotiated per-unit rates are never exposed to the client alone — only the agreed per-line total. ✅

---

## 7. Mobile (phone) usage tips

The panel was fully redesigned for mobile phones. All the rules from above still apply; only the layout is compressed:

| Layout change | Desktop | Mobile (< 576px width) |
|---|---|---|
| Header layout | Title + hint + search + buttons on 1-2 lines | 3 stacked rows: `Title` → `🔍 Search (full width)` → `[All Visible] … [Clear All]` |
| Hint text "Tick items…" | Visible | **Hidden** (saves ~16px vertical space) |
| Items per row | 4 / 3 / 3 / 2 | **1 single column** (scroll one big list — easiest to tap cards) |
| Category headers (10) | Name + count + checkbox + chevron all 1 line (10/10) | **Still all 1-line!** — no wrapping, collapse chevron stays reachable |
| Qty/Rate inputs | Standard height | **Compact 32px height** (more items visible without scrolling) |
| Left indent (category) | 17px (desktop) | 6px (saves horizontal for content) |
| Summary box position | Right side (`sticky-top`, visible always) | **Stacked BELOW Equipment Selection** after you tap Save |

### Booking on your phone (examples: wedding site visit, client café meeting)
1. Open http://localhost/MS/login.php in Chrome/Safari on phone.
2. Log in → Create Booking.
3. Tap cards. No finger gymnastics! Cards are ~280px wide × ~140px tall on a 320px screen; **any tap on the 90% of the card that isn't a small input toggles it.**
4. The search bar is the VERY FIRST thing after the title on mobile — so you can instantly search for the item a client just asked about ("Do you have Shure SLX24 wireless mics?").

---

## 8. Troubleshooting (common staff questions)

| Issue / Staff question | Answer |
|---|---|
| ❓ "I clicked the category select-all but 1 item didn't check!" | This is CORRECT: the filter excludes **search-hidden** items. If you searched for "shure" and then clicked Microphones checkbox, only the 2 visible Shure items are checked — the 3rd Sennheiser mic (hidden) is not auto-selected because it wasn't shown. Erase the search box to see indeterminate state. |
| ❓ "I can't edit Qty/Rate, the inputs are grey!" | You haven't CHECKED the item yet. Click anywhere on the card to toggle it ON first. |
| ❓ "What rate should I type?" | Always type the **AGREED PER-UNIT rate for THIS booking for THIS client**. Not the default catalog rate. Especially if you are giving a bulk package discount! |
| ❓ "If I click Clear All will it erase my Client / Date?" | No! **[Clear All] only affects Equipment Selection** — your Client / Date / Status / Discount / Notes fields stay untouched. |
| ❓ "My Arabic screen has the blue bar on the right! Bug?" | No — this is **RTL correct behaviour**. In Arabic `dir="rtl"` the accent bar flips to the right edge of the category header on purpose (matches reading direction). |
| ❓ "On iPhone when I click SEARCH nothing happens?" | iOS 12+ Safari: no issue. If cursor does not appear inside search box on older iOS, tap the box twice. |
| ❓ "Customer says WhatsApp message doesn't open?" | They may be on an old version of WhatsApp that can't handle very long URLs — click the "Copy message text" button instead and paste it manually into WhatsApp desktop/app. |
| ❓ "I need to add a NEW item type (new gear we just bought)?" | Equipment list comes from the **Item Types** admin page. Go to Dashboard → Items → Item Types → Add Item Type (set category, name, default rental value, stock quantity). Then it appears in this panel on next booking page reload. |

---

*End of Part 1 (English) manual.*

---

# ════════════════════════════════════════════
# PART 2 — دليل المستخدم باللغة العربية
# ════════════════════════════════════════════

> التبديل إلى العربية: من الشريط العلوي أيقونة اللغة → اختر العربية / AR.
> يتم تبديل كل أزرار الواجهة إلى اليمين تلقائياً (RTL) ويظهر الشريط الأزرق بجانب اسم الفئة على اليمين.

---

## ١. ما هو لوحة اختيار المعدات؟

عند إنشاء **حجز** جديد لحدث ما (عرس، حفل شركة، ديجي، وغيرها)، توجد لوحة **"اختيار المعدات"** وهي المكان الذي **تختار فيه كل قطعة معدات سوف يستأجرها العميل لهذا الحجز**.

أمثلة للمعدات:
- مكبرات الصوت / سوبر ووفرز / ميكروفونات
- مشغلات دي جي CDJ + خلاطات دي جي Mixer + وحدات تحكم DJ
- إضاءة (موفينغ هيد، بار أضواء PAR، ليزر، أضواء أرضية)
- كابلات (تيار 5 متر، XLR، RCA، HDMI)
- حاملات (حامل مكبر صوت، حامل ميكروفون، حامل لابتوب)
- أثاث دي جي (بيت دي جي، طاولات، واجهة زخرفية)
- ملحقات (حافظات رحلات، أغطية، لاصق لصق)

عند تحديد أي صنف يمكنك تعيين:
1. **الكمية** — عدد وحدات هذا الصنف التي سيستأجرها العميل.
2. **سعر الإيجار المتفق عليه (للوحدة)** — السعر المتفق عليه **لهذا الحجز فقط** ويمكن أن يكون أقل من السعر الافتراضي في الكتالوج (مثلاً خصم حزمة).

تُحسب اللوحة تلقائياً:
- 📋 **ملخص الحجز** (في الشريط الأيمن على سطح المكتب، أو أسفل اللوحة في الموبايل):
  - **عدد الأصناف** — مجموع الكميات لكل المعدات المختارة.
  - **المجموع الفرعي للمعدات** — مجموع `(الكمية × السعر لكل وحدة)` لكل صنف مختار.

---

## ٢. كيف تفتح لوحة اختيار المعدات

١. افتح المتصفح واذهب إلى: **http://localhost/MS/login.php**
٢. سجل الدخول باسم المستخدم وكلمة المرور.
٣. من القائمة اليسرى: **الحجوزات** → **إنشاء حجز**.
٤. في صفحة إنشاء الحجز، املأ أولاً: **اسم العميل**، **تاريخ الحدث**، **وقت البداية / النهاية**، **موقع الحدث**.
٥. ستظهر لوحة **اختيار المعدات** **تحت تفاصيل الحدث** داخل بطاقة واحدة كبيرة بعنوان متدرج اللون:
> 🅵 **اختيار المعدات** · حدد المربعات لإضافة الأصناف لهذا الحجز · [🔍 ابحث…] · [⊡ تحديد المرئي] · [⌫ مسح الكل]

---

## ٣. أجزاء لوحة اختيار المعدات (توضيح)

```
╔══════════════════════════════════════════════════════════════════╗
║  🅵 اختيار المعدات  حدد الأصناف…  [ابحث…🔍]  [تحديد المرئي⊡]  [مسح الكل ⌫]   ← عنوان متدرج + شريط الأدوات (بطاقة واحدة!)
╠══════════════════════════════════════════════════════════════════╣
║  ▴ ───────────────────────────  (3)  🎤 الميكروفونات  ☑          ← عنوان الفئة (خط عريض + شريط أزرق على اليمين في الوضع العربي)
║  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐   │
║  │  SM58 سلكي    ☑ │  │  SM58 لاسلكي  ☑ │  │ Sennheiser E835☑│   │   شبكة 4 أعمدة (على سطح المكتب الكبير)
║  │   JOD 20.00     │  │   JOD 40.00     │  │   JOD 30.00     │   │   البطاقة: مربع تحديد + اسم الصنف
║  │ ✅ متوفر: 12    │  │ ✅ متوفر: 6      │  │ ✅ متوفر: 8      │   │   شارة سعر الوحدة الافتراضي
║  │ [كمية] [السعر] │  │ [كمية] [السعر]  │  │ [كمية] [السعر]  │   │   عدد المتوفر من المخزون
║  │  [2] [20.00]   │  │  [1] [40.00]    │  │  [0] (غير مفعل) │   │   حقول الكمية والسعر
║  │ تحقق من التوفر   │  │ تحقق من التوفر   │  │ تحقق من التوفر   │   │   سفل البطاقة نص "تحقق من التوفر" (يمكن النقر عليه لتبديل الاختيار!)
║  └─────────────────┘  └─────────────────┘  └─────────────────┘   │
║  ▴ ───────────────────────────   (3)  💡 الإضاءة   ☑             │   الفئة التالية — داخل **نفس البطاقة** الكبيرة (لا بطاقات منفصلة!)
║  ...                                                                │
╚══════════════════════════════════════════════════════════════════╝
```

### شرح أجزاء اللوحة

| الجزء | الوظيفة |
|---|---|
| **عنوان البطاقة المتدرج** | عنوان لوحة المعدات + شريط الأدوات (بحث + زرّا تحديد/مسح). هذا هو **الإطار الوحيد** لكل الـ10 فئات (لم يعد هناك 10 بطاقات منفصلة!). |
| **مربع البحث 🔍** | اكتب اسم الصنف (مثال `shure`، `Pioneer CDJ-3000`) أو اسم الفئة (مثال `إضاءة` أو `cables`) ليقوم بالفلترة فوراً. الأصناف والفئات غير المتطابقة تختفي، وإن لم يجد أي نتيجة يظهر رسالة "لا توجد معدات تطابق بحثك." |
| **زر [تحديد المرئي]** | يقوم بتحديد **كل الأصناف الظاهرة حالياً فقط** (مع احترام الفلترة الحالية). مثال: اكتب "cables" في البحث → اضغط تحديد المرئي → يتم اختيار كل أنواع الكابلات فوراً. |
| **زر [مسح الكل]** | يقوم بإلغاء تحديد كل شيء في اللوحة ويعيد عدّاد الأصناف والمجموع إلى صفر. |
| **عنوان الفئة** (10 فئات) | اسم الفئة بخط عريض + `(عدد)` الأصناف الظاهرة فيها + مربع تحديد ثلاثي الحالات + سهم ▴ لطي/فرد القائمة. |
| **مربع تحديد الفئة ثلاثي الحالات** | `☐` = لا شيء محدد / `▤` = بعض الأصناف محددة (العلامة "-") / `☑` = كل الأصناف **الظاهرة** في الفئة محددة. |
| **سهم طي الفئة ▴ / ▾** | انقر لطي القائمة الطويلة وتقليل التمرير. عند الطي، يتحول السهم إلى ▾ عبر تنسيق CSS. |
| **بطاقة الصنف** (20 بطاقة) | بطاقة صغيرة ذات حواف دائرية، تطفو عند المرور بالماوس، وتتوهج باللون الأزرق عند التحديد. **انقر في أي مكان داخل البطاقة لتبديل التحديد!** (لا تحتاج إلى استهداف المربع الصغير!) |
| **حقل الكمية** | عدد وحدات هذا الصنف المطلوبة للعميل. الافتراضي = 1 عند التحديد. |
| **حقل سعر الوحدة** | سعر الوحدة المتفق عليه بالدينار الأردني. عند أول تحديد يأخذ السعر الافتراضي من الكتالوج — **قم بتعديله إلى السعر المتفاوض عليه للعميل قبل إرسال العرض!** |
| **شارة التوفر** (أخضر/برتقالي/أحمر "متوفر: N") | الكمية المتوفرة حالياً في المخزون (معلومة للقراءة فقط — لا تمنع الحجز لكنها تنبيه). |
| **سطر أسفل البطاقة "تحقق من التوفر"** | يمكن النقر **هنا تحديداً** (أعلى السطر النصي) لتبديل حالة البطاقة! |

---

## ٤. طريقة اختيار المعدات (خطوة بخطوة)

### الخطوة ١: املأ بيانات الحجز أولاً (إلزامي!)
قبل لمس أي معدات، اختر **العميل**، **تاريخ الحدث**، **وقت البداية / النهاية**، **الموقع**، **الحالة** (عادةً "عرض سعر" أولاً ثم "مؤكد" عند دفع العربون).

### الخطوة ٢: ابحث عن الأصناف المطلوبة بـ 3 طرق (اختر الأسرع!)

#### الطريقة أ: التمرير + نقرة على البطاقات (سريع للحجوزات العادية)
مرر على الـ10 فئات؛ **انقر في أي مكان على بطاقة كل صنف تريده**.
- الأنسب: الحفلات القياسية (مثلاً: ديجي مع 2x CDJ-3000، 1x DJM-A9، 2x مكبر صوت، 2x سوبر ووفر، كابلات، حاملات).
- تذكر: لا تحتاج للنقر داخل المربع الصغير! جسم البطاقة واسم الصنف وحتى سطر "تحقق من التوفر" كلها تقوم بتبديل التحديد.

#### الطريقة ب: البحث + التحديد (لصنف واحد محدد)
١. انقر في مربع **🔍 البحث** في أعلى اللوحة.
٢. اكتب اسم الصنف، مثلاً اكتب `shure` → تختفي كل الفئات إلا فئة الميكروفونات وتظهر فقط صنفين Shure.
٣. انقر على مربع تحديد **فئة الميكروفونات ☑** → يتم تحديد صنفي Shure الظاهرين فقط. الميكروفون الثالث (Sennheiser المخفي) **لا يتم لمسه** — هذا هو السلوك الصحيح!
٤. امحُ نص البحث للعودة إلى اللوحة الكاملة. ستلاحظ أن فئة الميكروفونات تظهر علامة **▤** (بعض الأصناف محددة) لأن الميكروفون الثالث لم يُحدد.

#### الطريقة ج: تحديد فئة كاملة بضغطة واحدة (مثلاً: كل الكابلات)
١. انقر على مربع تحديد الفئة بجانب **"كابلات وملحقات التوصيل"**.
٢. تختار كل 3 أنواع كابلات داخل الفئة فوراً وتتوهج باللون الأزرق.
٣. إن أردت بعد ذلك إلغاء تحديد نوع واحد فقط غير مطلوب → انقر على بطاقته → يتحول مربع تحديد الفئة إلى **▤** (بعض المحدد).

### الخطوة ٣: لكل صنف محدّد، عدّل الكمية وسعر الوحدة المتفق عليه
كل بطاقة محددة ستفعل حقول **الكمية** و**السعر** (البطاقات الرمادية غير المفعلة = غير محددة).

مثال حزمة عرس دي جي:

| الصنف | الكمية | سعر الوحدة (دينار) | سبب التعديل |
|---|---|---|---|
| Pioneer CDJ-3000 (زوج) | 2 | 200.00 | سعر افتراضي = 200.00 موافق |
| خلاط دي جي Pioneer DJM-A9 4 قنوات | 1 | 120.00 | الافتراضي 150.00 → خصم على حزمة |
| RCF HDL 20-A مكبر صوت خطي علوي | 4 | 180.00 | |
| RCF SUB 8006-AS سوبر ووفر | 2 | 220.00 | |
| Shure SM58 سلكي | 3 | 18.00 | |
| كابل كهرباء (5 متر) | 8 | 5.00 | |
| حامل مكبر صوت ثقيل | 4 | 10.00 | |

تقوم اللوحة بإعادة الحساب فوراً:
- عدد الأصناف = `2+1+4+2+3+8+4 = 24`
- المجموع الفرعي = `(2×200)+(1×120)+(4×180)+(2×220)+(3×18)+(8×5)+(4×10)` = `400+120+720+440+54+40+40 = 1,814.00 دينار أردني`

### الخطوة ٤ (اختياري): طي الفئات الطويلة لتقليل التمرير
بعد اختيار كل مكبرات الصوت، إذا كنت تريد الانتقال إلى الكابلات دون المرور بكل القائمة الطويلة → انقر على **▴** بجانب فئة مكبرات الصوت → تُطوى. انقر مجدداً ▾ لفتحها.

### الخطوة ٥: راجع ملخص المعدات
انظر إلى مربع الملخص في الشريط الأيمن (أو أسفل المعدات في الموبايل):
```
━━━━━━━━━━━━━━━━━━━━━━━━━
📊 الملخص
━━━━━━━━━━━━━━━━━━━━━━━━━
عدد الأصناف           24
المجموع الفرعي للمعدات  JOD 1,814.00
━━━━━━━━━━━━━━━━━━━━━━━━━
```
إن وجدت شيئاً غير صحيح → عدّل في المعدات.

### الخطوة ٦: أكمل بقية الحقول في الحجز
أضف **نسبة الخصم**، **المبلغ المدفوع (العربون)**، **الملاحظات الداخلية** (لا تُرسل للعميل أبداً)، **ملاحظات العميل** (ترسل مع التأكيد). ثم اضغط **"حفظ الحجز"**.

---

## ٥. اختصارات الماوس ولوحة المفاتيح (حجز أسرع!)

| الإجراء | الاختصار |
|---|---|
| تحديد صنف | **انقر في أي مكان على البطاقة** (الاسم، الشارة، سطر التوفر، المساحات الفارغة — كلها تعمل!) |
| تحديد فئة كاملة | انقر على **مربع تحديد الفئة الكبير** |
| تحديد نتائج البحث فقط | اكتب في البحث أولاً → اضغط **[تحديد المرئي]** |
| تحديد فئة واحدة مع فلترة البحث | اكتب في البحث أولاً → انقر مربع تحديد تلك الفئة فقط |
| إلغاء تحديد صنف واحد | انقر على بطاقته **مجدداً** في أي مكان → يُلغى التحديد |
| إلغاء كل شيء | اضغط **[مسح الكل]** (يمين أعلى لوحة المعدات) |
| طي فئة طويلة | انقر على السهم **▴ / ▾** بجانب اسم الفئة |
| معرفة عدد المحددات في أي لحظة | انظر إلى **عدد الأصناف** في مربع الملخص على اليمين |

---

## ٦. إرسال العرض للعميل — ماذا يراه العميل؟ (ملاحظة هامة للخصوصية!)

بعد حفظ الحجز، اضغط على زر **واتساب الأخضر** في صفحة عرض الحجز.

**تغيير هام في الخصوصية والأسعار (مُطبق منذ أغسطس 2026):**
كانت رسالة واتساب القديمة تعرض **سعر الوحدة** بين قوسين، مثل:
```
- 4 × كابل كهرباء (5 متر) (JOD 75.00)   ❌ سلوك قديم سيء — كشف سعر الوحدة للعميل
```

كان هذا مربكاً للعملاء (يقارنون سعر كل وحدة مع المنافسين). الآن الرسالة تعرض **فقط المجموع الإجمالي لكل سطر (المتّفق عليه)**:
```
- 4 × كابل كهرباء (5 متر) = JOD 300.00   ✅ السلوك الصحيح الجديد
- 2 × Pioneer CDJ-3000 (زوج) = JOD 400.00
المجموع الفرعي للمعدات: JOD 700.00
```

نفس القاعدة تنطبق على:
- **صفحة تأكيد الحجز للعميل (`confirm.php`)** — التي يصل للعميل رابطها عبر البريد أو الرسالة النصية
- **فاتورة العميل القابلة للطباعة (`invoice.php`)** — لا يوجد **عمود "سعر الوحدة"** على الإطلاق! فقط أعمدة الكمية والمجموع لكل سطر.

سعر الوحدة المتفاوض عليه لا يظهر للعميل منفرداً أبداً — فقط المجموع المتفق عليه لكل سطر. ✅

---

## ٧. نصائح الاستخدام على الهاتف المحمول

تمت إعادة تصميم اللوحة بالكامل لشاشات الهواتف، وتبقى جميع القواعد نفسها؛ مع بعض التغييرات في التخطيط:

| التغيير في التخطيط | سطح المكتب | الهاتف (<576 بكسل عرض) |
|---|---|---|
| عنوان اللوحة | عنوان + تلميح + بحث + أزرار في ١-٢ سطر | 3 صفوف مكدسة: `العنوان` → `🔍 البحث (العرض كامل)` → `[تحديد المرئي] … [مسح الكل]` |
| نص التلميح "حدد المربعات…" | ظاهر | **مخفي** (يوفر ~16 بكسل رأسياً) |
| عدد الأصناف في الصف | 4 / 3 / 3 / 2 | **عمود واحد** (تمرير قائمة طويلة أسهل للنقر بالإصبع) |
| عناوين الفئات (10 فئات) | كلها اسم + عدّاد + مربع + سهم في سطر واحد | **لا تزال كلها في سطر واحد!** — لا التفاف، وسهم الطي يظل في متناول الإصبع. |
| حقول الكمية والسعر | ارتفاع عادي | **ارتفاع مضغوط 32 بكسل** (ظهور أصناف أكثر دون تمرير) |
| المسافة البادئة اليسرى للفئة | 17 بكسل (سطح المكتب) | 6 بكسل فقط (يوفر مساحة أفقية للمحتوى) |
| موقع مربع الملخص | في الشريط الأيمن (ثابت أثناء التمرير) | **مكدّس أسفل لوحة المعدات** مباشرة قبل زر حفظ الحجز |

### مثال الحجز على الهاتف (زيارة موقع عرس، اجتماع عميل في مقهى)
١. افتح http://localhost/MS/login.php في Chrome/Safari بالهاتف.
٢. سجل الدخول → إنشاء حجز.
٣. اضغط على البطاقات بالإصبع. لا تحتاج لدقة عالية! البطاقات بأبعاد ~280×140 بكسل على شاشة 320 بكسل؛ **أي نقرة في 90% من مساحة البطاقة (غير الحقول الصغيرة) تقوم بتبديل التحديد.**
٤. مربع البحث يظهر **فوراً** بعد العنوان على الموبايل — لذا يمكنك البحث فوراً عن صنف يسأل عنه العميل ("عندكم ميكروفون لاسلكي Shure SLX24؟").

---

## ٨. استكشاف الأخطاء وإصلاحها (أسئلة الموظفين الشائعة)

| المشكلة / سؤال الموظف | الجواب |
|---|---|
| ❓ "ضغطت تحديد الكل للفئة لكن صنفاً واحداً لم يتم تحديده!" | هذا صحيح! لأن الفلتر يستثني **الأصناف المخفية بسبب البحث**. إذا كنت قد بحثت عن "shure" ثم ضغطت مربع الفئة، يتم تحديد صنفي Shure الظاهرين فقط دون المس بالميكروفون الثالث المخفي. امحُ البحث لترى الحالة غير المحددة جزئياً. |
| ❓ "لا أستطيع تعديل الكمية أو السعر والحقول رمادية!" | لم تقم **بتحديد** الصنف بعد! انقر في أي مكان على البطاقة لتشغيله أولاً. |
| ❓ "ما السعر الذي يجب أن أكتبه؟" | اكتب دائماً **سعر الوحدة المتفاوض عليه لهذا العميل في هذا الحجز**. ليس السعر الافتراضي من الكتالوج — خصوصاً لو كنت تعطي خصم على الحزمة! |
| ❓ "لو ضغطت مسح الكل هل يحذف بيانات العميل والتاريخ؟" | لا! **[مسح الكل] يؤثر فقط على اختيار المعدات**. اسم العميل / التاريخ / الحالة / الخصم / الملاحظات كلها تبقى كما هي. |
| ❓ "في الواجهة العربية الشريط الأزرق على اليمين! خطأ؟" | لا — هذا هو **السلوك الصحيح لاتجاه RTL**. في الواجهة العربية `dir="rtl"` ينتقل الشريط الأزرق إلى الحافة اليمنى لعنوان الفئة، ليتناسب مع اتجاه القراءة. |
| ❓ "على أيفون عندما أضغط مربع البحث لا يظهر المؤشر؟" | على iOS 12+ Safari لا توجد مشكلة. في إصدارات أقدم، اضغط داخل المربع مرتين. |
| ❓ "العميل يقول إن رسالة واتساب لا تفتح!" | قد يكون لديهم نسخة قديمة من واتساب لا تدعم الروابط الطويلة — استخدم زر "نسخ نص الرسالة" ثم الصقه يدوياً في واتساب. |
| ❓ "أريد إضافة صنف جديد للمعدات (شراء جهاز جديد)؟" | قائمة المعدات تأتي من صفحة **"أنواع الأصناف"** في لوحة الإدارة. انتقل إلى القائمة الرئيسية → الأصناف → أنواع الأصناف → إضافة نوع صنف (حدد الفئة والاسم والسعر الافتراضي والكمية في المخزون). سيظهر الصنف في لوحة اختيار المعدات عند إعادة تحميل صفحة إنشاء الحجز. |

---

*نهاية الدليل — الإنجليزية + العربية.*
