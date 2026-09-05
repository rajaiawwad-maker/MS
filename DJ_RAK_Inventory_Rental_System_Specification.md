# DJ Equipment Rental & Inventory Management System
## Complete System Specification

**Document Version:** 1.0  
**Date:** 26 August 2026  
**System Type:** Web Portal + Mobile-Friendly Application  
**Primary Purpose:** Inventory, booking, customer, payment, expense, calendar, WhatsApp communication, and financial reporting for DJ equipment rentals.

---

# 1. Project Overview

The system will manage the complete operational cycle of a DJ equipment rental business.

The main workflow is:

**Client Request → Quotation → Equipment Availability Check → Booking → WhatsApp Confirmation → Event → Collection/Payment → Return → Financial Reporting**

The system must prevent double-booking of equipment and provide a clear view of what equipment is available for any requested date range.

The system is intended primarily for internal business users. Customer-facing information must be limited to information explicitly approved for sharing.

---

# 2. Objectives

The system must:

1. Manage users and permissions.
2. Maintain the DJ equipment inventory.
3. Organize equipment by categories and item types.
4. Maintain the customer/client database.
5. Create and manage rental bookings.
6. Check equipment availability in real time.
7. Prevent or clearly warn about equipment conflicts.
8. Track quoted, collected, and pending amounts.
9. Track DJ RAK amounts separately from customer revenue.
10. Manage business expenses.
11. Provide calendar-based booking management.
12. Provide daily, monthly, and yearly dashboards.
13. Generate operational and financial reports.
14. Share quotations and payment reminders through WhatsApp.
15. Allow clients to confirm bookings through a secure web link.
16. Allow confirmed event information to be added to the customer's phone calendar.
17. Maintain internal notes that are never shared with customers.

---

# 3. User Roles & Permissions

## 3.1 Administrator

Full access to all system functions.

Permissions:

- Manage users.
- Manage roles.
- Manage categories.
- Manage item types.
- Manage inventory.
- Manage clients.
- Manage expense types.
- Add/edit/delete expenses.
- Create/edit/cancel bookings.
- View financial information.
- View DJ RAK information.
- Generate all reports.
- Share information through WhatsApp.
- View audit logs.
- Configure system settings.

## 3.2 Booking User

Operational access.

Permissions:

- View inventory.
- Add/edit clients.
- Create bookings.
- Edit bookings.
- Check equipment availability.
- View calendar.
- Send WhatsApp quotation.
- Update booking/payment status.
- View operational reports.

Financial configuration and sensitive administrative settings may be restricted.

## 3.3 Finance User

Permissions:

- View bookings.
- View quoted amounts.
- Update collected amounts.
- View pending amounts.
- Add expenses.
- View financial dashboards.
- Generate financial reports.
- Generate client account statements.

## 3.4 Viewer / Management

Read-only access to:

- Dashboard.
- Calendar.
- Bookings.
- Inventory availability.
- Financial reports.
- Expense reports.

---

# 4. Authentication

The login screen must contain:

- Username/email.
- Password.
- Login button.
- Forgot password.
- Remember me (optional).

Security requirements:

- Passwords must be securely hashed.
- Session timeout.
- Role-based access control.
- Account activation/deactivation.
- Optional two-factor authentication in a future phase.
- Audit log for important actions.

---

# 5. Main Navigation

Recommended navigation:

1. Dashboard
2. Calendar
3. Bookings
4. Inventory
5. Clients
6. Expenses
7. Reports
8. Setup
9. Users & Permissions
10. Audit Log
11. Profile / Logout

---

# 6. Setup Management

## 6.1 Equipment Categories

Fields:

- Category ID
- Category Name
- Description
- Active/Inactive

Examples:

- Speakers
- Subwoofers
- DJ Controllers
- Mixers
- Microphones
- Lighting
- Stands
- Cables
- Accessories
- DJ Furniture

## 6.2 Item Types

Each item type belongs to a category.

Fields:

- Item Type ID
- Category
- Item Type Name
- Description
- Default Rental Value (optional)
- Quantity
- Active/Inactive

Example:

**Category:** Speakers  
**Item Type:** JBL PRX812W

Quantity:

- Total: 4
- Available: calculated
- Booked: calculated
- Maintenance: manually controlled if required

## 6.3 Inventory Items

Although item types represent the rental product, the system should support individual physical units where required.

Recommended fields:

- Item ID
- Item Type
- Serial Number
- Asset/Inventory Code
- Purchase Date
- Status
- Location
- Notes

Possible statuses:

- Available
- Booked
- Out for Event
- Maintenance
- Damaged
- Lost
- Retired

This structure allows the system to grow from quantity-based inventory to individual asset tracking.

---

# 7. Client Management

Client setup screen:

- Client ID
- Client Name
- Phone Number
- Alternative Phone (optional)
- Notes
- Active/Inactive
- Created Date

The booking screen must allow:

**Select Existing Client**

or

**Add New Client**

without leaving the booking process.

---

# 8. Expense Setup

Expense Type setup:

- Expense Type ID
- Expense Type Name
- Fixed Value
- Description
- Active/Inactive

Examples:

- Transportation
- Fuel
- Maintenance
- Equipment Repair
- Staff
- Marketing
- Storage
- Other

The fixed value is optional and should act as a default value only. The user must still be able to modify the actual expense amount when recording an expense.

---

# 9. Booking Management

## 9.1 Booking Creation

The booking screen is the most important operational screen.

Fields:

### Booking Information

- Booking Number
- Client
- Event Date From
- Event Date To
- Event Location
- Booking Status
- Payment Status
- Quoted Amount
- DJ RAK Amount
- Internal Notes

Default dates:

- From = Today
- To = Today

Both dates must be editable.

---

# 10. Equipment Selection

The booking screen must allow the user to select:

- Category
- Item Type
- Quantity

Example:

| Category | Item Type | Requested |
|---|---|---:|
| Speaker | JBL PRX812W | 2 |
| Subwoofer | JBL PRX818XLF | 2 |
| Controller | Pioneer XDJ-XZ | 1 |
| Microphone | Wireless Mic | 2 |

The system must immediately calculate availability.

Example:

**JBL PRX812W**

Total Inventory: 4  
Already Booked: 2  
Available: 2

If the user requests 3:

> Warning: Only 2 units are available for the selected dates.

The system should prevent overbooking unless an Administrator explicitly overrides the restriction.

---

# 11. Availability Calculation

Availability must be calculated based on overlapping booking dates.

For a requested period:

**Available Quantity = Total Quantity - Quantity Reserved by overlapping active bookings**

Canceled bookings must not consume inventory.

Bookings with statuses such as:

- Draft
- Quotation
- Confirmed
- Completed

may consume inventory according to system configuration.

Recommended behavior:

- Draft: does not reserve inventory.
- Quotation: temporarily reserves inventory.
- Confirmed: reserves inventory.
- Completed: historical record.
- Canceled: releases inventory.

The system should clearly display the reason when an item is unavailable.

---

# 12. Booking Editing

Users with permission can edit:

- Client
- Event dates
- Location
- Equipment
- Quoted amount
- DJ RAK amount
- Notes
- Payment status

When equipment or dates are changed, availability must be recalculated immediately.

If the modification creates a conflict, the system must show the conflict before saving.

---

# 13. Payment Status

The booking screen must contain clear payment controls.

Recommended payment statuses:

- Booked
- Partially Collected
- Collected
- Canceled

For better financial control, the system should store actual payment transactions rather than only one status.

Recommended payment fields:

- Payment ID
- Booking ID
- Payment Date
- Amount
- Payment Method
- Reference
- Notes

This allows:

**Quoted Amount = 5,000 SAR**  
**Collected = 2,000 SAR**  
**Pending = 3,000 SAR**

The payment status can then be calculated automatically.

---

# 14. DJ RAK Amount

DJ RAK must be stored separately from the customer quotation.

Important rule:

**DJ RAK must NOT be included in the customer quoted amount or collected revenue.**

It is used for internal reporting.

Example:

Quoted Amount: 5,000 SAR  
DJ RAK Amount: 500 SAR

Customer financial report:

Revenue = 5,000 SAR

DJ RAK report:

DJ RAK = 500 SAR

DJ RAK reporting should support grouping by:

- Date
- Month
- Year
- Item Type
- Category
- Booking
- Client

---

# 15. Internal Notes

Every booking must have an internal free-text notes field.

Example:

> Client requested delivery at 5 PM. Confirm loading access with venue.

This information:

- Is visible internally.
- Is stored with the booking.
- Must NEVER be included in WhatsApp customer messages.
- Must NEVER appear in customer confirmation links.

---

# 16. WhatsApp Quotation

The system must provide a button:

**Send Quotation via WhatsApp**

The customer message should contain:

- Client name
- Event date
- Event date range if applicable
- Event location
- Selected equipment
- Quoted amount
- Confirmation link

Example message structure:

Hello [Client Name],

Here is your DJ equipment rental quotation:

Event Date: [Date]
Location: [Location]

Equipment:
- 2 × JBL PRX812W
- 2 × JBL Subwoofer
- 1 × Pioneer XDJ-XZ

Quoted Amount: SAR 5,000

Please review and confirm your booking using the link below:

[Confirm Booking]

Important:

The following information must NOT be shared:

- DJ RAK amount
- Internal notes
- Internal inventory information
- Internal profit information
- Internal expense information

---

# 17. Customer Confirmation

The WhatsApp message should contain a secure unique URL.

Example:

https://system-domain.com/confirm/ABC123

The customer page should show:

- Event date
- Location
- Equipment summary
- Quoted amount
- Confirmation button

Buttons:

**Confirm Booking**

**Request Changes**

After confirmation:

- Booking status changes to Confirmed.
- Confirmation date/time is recorded.
- Confirmation source is recorded.
- Inventory is reserved.
- Customer can optionally add the event to their phone calendar.

---

# 18. Customer & user Calendar

After confirmation, provide:

**Add to Calendar**

The system should generate a standard calendar event compatible with:

- Google Calendar
- Apple Calendar
- Outlook Calendar
- Android/iOS calendar applications

The event should include:

- Event title
- Date
- Start/end date/time if available
- Location
- Basic booking information

Internal notes and DJ RAK information must never be included.

---

# 19. Calendar Screen

Calendar view options:

- Month
- Week
- Day

Each booking should appear on its relevant date.

Calendar information can show:

- Client
- Event location
- Booking status
- Payment status
- Total quotation
- Equipment summary

Clicking a booking opens its details.

Filters:

- Client
- Date
- Booking status
- Payment status
- Category
- Item Type

---

# 20. Calendar Dashboard

The calendar must show financial summaries.

For selected date/month/year:

- Total Bookings
- Total Quoted
- Total Collected
- Total Pending
- Total Canceled
- Total DJ RAK
- Total Expenses
- Net Revenue

Example:

### August 2026

Bookings: 25  
Quoted: SAR 85,000  
Collected: SAR 60,000  
Pending: SAR 25,000  
DJ RAK: SAR 8,000  
Expenses: SAR 15,000  
Net: SAR 45,000

---

# 21. Expense Transactions

Expense entry screen:

- Expense ID
- Date
- Expense Type
- Amount
- Description / Free Text
- Payment Method (optional)
- Reference (optional)
- Created By
- Created Date

The user selects Expense Type from a dropdown.

If the expense type has a fixed default value, the system automatically fills the amount, but the user can modify it.

---

# 22. Main Dashboard

The dashboard must default to:

**First day of the current month → Current date**

Example:

26 August 2026

Default filter:

**01 August 2026 → 26 August 2026**

The dashboard should display:

### Revenue

- Total Booked
- Total Collected
- Pending Payment
- Expected Income

### Expenses

- Total Expenses

### Profit View

- Collected Revenue
- Expenses
- Net Collected

### Operations

- Number of Bookings
- Confirmed Events
- Pending Events
- Canceled Events

### Inventory

- Total Item Types
- Total Units
- Currently Available
- Currently Booked

---

# 23. Financial Definitions

The system must clearly distinguish:

### Booked Amount

Total quoted amount for bookings that are not canceled.

### Collected Amount

Actual payments received.

### Pending Amount

Amount still due:

**Pending = Booked Amount - Collected Amount**

### Expected Income

Amount expected from future/current valid bookings.

Canceled bookings must not be counted as expected income.

---

# 24. Reports

## 24.1 Daily Booking Report

Filters:

- From Date
- To Date
- Client
- Booking Status
- Payment Status
- Category
- Item Type

Report columns:

- Booking Number
- Client
- Phone
- Date From
- Date To
- Location
- Equipment
- Quoted Amount
- Collected Amount
- Pending Amount
- DJ RAK
- Status
- Notes

Export:

- Excel
- PDF
- CSV

Internal notes should be optional in exports.

---

# 25. Expense Report

Filters:

- From Date
- To Date
- Expense Type

Columns:

- Date
- Expense Type
- Amount
- Description
- Created By

Summary:

- Total Expenses
- Expense count
- Total by Expense Type

Export:

- Excel
- PDF
- CSV

---

# 26. Financial Report

Filters:

- From Date
- To Date
- Client
- Payment Status

Options:

- Booked
- Collected
- Pending
- All

Columns:

- Booking
- Client
- Event Date
- Quoted
- Collected
- Pending
- Status

Summary:

- Total Booked
- Total Collected
- Total Pending

---

# 27. Client Account Statement

Each client must have an account statement.

Example:

### Client: ABC Events

| Date | Booking | Amount | Collected | Pending |
|---|---|---:|---:|---:|
| 01-Aug | #1001 | 5,000 | 3,000 | 2,000 |
| 15-Aug | #1020 | 4,000 | 4,000 | 0 |
| 25-Aug | #1032 | 6,000 | 0 | 6,000 |

Summary:

Total Booked: SAR 15,000  
Total Collected: SAR 7,000  
Total Pending: SAR 8,000

---

# 28. Client Payment Reminder via WhatsApp

The system should allow:

**Send Pending Payment Reminder**

Options:

### Summary Message

Example:

Hello [Client Name],

Your current outstanding balance is:

**SAR 8,000**

Please contact us if you need any clarification.

### Detailed Message

The system can include:

- Event date
- Amount
- Item summary
- Pending amount

Equipment should be displayed as one text line.

Example:

Equipment: 2 × JBL PRX812W, 2 × JBL Subwoofer, 1 × Pioneer XDJ-XZ

The message must not include internal notes or DJ RAK.

---

# 29. Revenue vs Expense Report

This report provides a business-level financial view.

Filters:

- From Date
- To Date
- Monthly
- Yearly

Summary:

**Income**
- Booked
- Collected
- Pending

**Expenses**
- Total Expenses

**Net**

Recommended calculation:

**Net Collected = Collected Amount - Expenses**

The report should also provide detailed transactions.

---

# 30. Revenue Dashboard

Charts recommended:

1. Revenue by Day
2. Collected vs Pending
3. Revenue by Month
4. Expenses by Month
5. Revenue vs Expenses
6. Top Clients
7. Most Rented Equipment
8. DJ RAK by Category/Item Type

---

# 31. Inventory Reports

Recommended inventory reports:

### Inventory Summary

- Category
- Item Type
- Total Quantity
- Available Quantity
- Booked Quantity
- Maintenance Quantity

### Equipment Usage

- Item Type
- Number of bookings
- Quantity rented
- Rental frequency
- Revenue generated by related bookings

### Availability Report

Filters:

- From Date
- To Date
- Category
- Item Type

Shows:

- Total
- Booked
- Available

---

# 32. Booking Status Workflow

Recommended workflow:

**Draft**
↓
**Quotation**
↓
**Confirmed**
↓
**Event Completed**
↓
**Closed**

Alternative:

**Quotation → Canceled**

Cancellation must release reserved equipment.

---

# 33. Payment Workflow

Recommended payment workflow:

**Not Collected**
↓
**Partially Collected**
↓
**Fully Collected**

A booking can also be:

**Canceled**

The system should not allow collected amounts to exceed the quoted amount unless an Administrator has permission to record an adjustment.

---

# 34. Audit Trail

The system should record important actions.

Audit fields:

- User
- Date/Time
- Action
- Record Type
- Record ID
- Old Value
- New Value

Examples:

- Booking created.
- Booking amount changed.
- Equipment added.
- Equipment removed.
- Payment recorded.
- Payment deleted.
- Booking canceled.
- Client updated.

This is especially important for financial information.

---

# 35. Database Structure

Recommended main tables:

### Users

- id
- name
- username
- email
- password_hash
- role_id
- active
- created_at

### Roles

- id
- name

### Permissions

- id
- permission_name

### RolePermissions

- role_id
- permission_id

### Clients

- id
- name
- phone
- notes
- active
- created_at

### Categories

- id
- name
- description
- active

### ItemTypes

- id
- category_id
- name
- description
- default_rental_value
- active

### InventoryItems

- id
- item_type_id
- asset_code
- serial_number
- status
- notes

### Bookings

- id
- booking_number
- client_id
- date_from
- date_to
- location
- quoted_amount
- dj_rak_amount
- status
- payment_status
- internal_notes
- customer_confirmation_token
- customer_confirmed_at
- created_by
- created_at
- updated_at

### BookingItems

- id
- booking_id
- item_type_id
- quantity
- rental_value

### Payments

- id
- booking_id
- payment_date
- amount
- payment_method
- reference
- notes
- created_by

### ExpenseTypes

- id
- name
- fixed_value
- active

### Expenses

- id
- expense_type_id
- date
- amount
- description
- payment_method
- reference
- created_by

### AuditLogs

- id
- user_id
- action
- entity_type
- entity_id
- old_value
- new_value
- created_at

---

# 36. Important Inventory Rule

The system must not simply mark an item as "available/unavailable" globally.

Availability is date-dependent.

Example:

Total JBL PRX812W = 4

Booking A:
01-Aug to 03-Aug = 3 units

Booking B:
04-Aug to 06-Aug = 4 units

This is valid because the dates do not overlap.

However:

Booking C:
02-Aug to 05-Aug = 2 units

This conflicts because:

Booking A has 3 booked units during 02-Aug and 03-Aug.

Available on those dates = 1.

Therefore the system must show:

**Requested: 2  
Available: 1  
Shortage: 1**

---

# 37. Date & Time Considerations

Although the initial requirement specifies dates, the system should be designed to support event times later.

Recommended fields:

- Event Start Date
- Event End Date
- Event Start Time
- Event End Time

For the first version, time can remain optional.

---

# 38. WhatsApp Integration Architecture

Recommended architecture:

**System → WhatsApp Business API → Customer**

The system should generate an approved message template containing:

- Customer name
- Date
- Location
- Equipment
- Quoted amount
- Confirmation URL

The system should NOT send:

- Internal notes
- DJ RAK
- Profit
- Internal inventory availability

WhatsApp message status can optionally be stored:

- Sent
- Delivered
- Read
- Failed

---

# 39. Customer Confirmation Security

Confirmation URLs must:

- Use random secure tokens.
- Not expose internal booking IDs directly where possible.
- Expire if configured.
- Prevent unauthorized modification.
- Record confirmation date/time.
- Record customer response.

Possible actions:

- Confirm
- Request Change
- Decline

If the customer requests changes, the booking should become:

**Change Requested**

and the internal user can contact the customer.

---

# 40. Notifications

Future notifications can include:

### Internal

- Upcoming event tomorrow.
- Pending payment.
- Equipment conflict.
- Booking canceled.
- Customer requested change.

### Customer

- Quotation received.
- Booking confirmed.
- Payment reminder.
- Booking update.

---

# 41. Recommended Screen Structure

## Dashboard

Top KPI cards:

- Collected
- Pending
- Expected
- Expenses
- Net

Below:

- Revenue chart
- Expense chart
- Upcoming bookings
- Pending payments
- Inventory availability

## Booking Screen

Recommended layout:

**Left / Main Area**
- Client
- Dates
- Location
- Equipment
- Notes

**Right / Summary**
- Quoted Amount
- Collected
- Pending
- DJ RAK
- Booking Status
- Payment Status
- Equipment Availability

Buttons:

- Save
- Save & Send WhatsApp
- Confirm
- Cancel
- Record Payment
- Edit
- Print
- View Client Statement

---

# 42. Booking Availability User Experience

While selecting equipment:

| Item | Required | Available | Result |
|---|---:|---:|---|
| JBL PRX812W | 2 | 4 | Available |
| JBL Subwoofer | 2 | 1 | Conflict |
| Pioneer XDJ-XZ | 1 | 1 | Available |

Use clear visual status indicators:

- Available
- Limited
- Not Available

The exact colors should be configurable by the UI theme.

---

# 43. Search

Global search should support:

- Client name
- Phone
- Booking number
- Location
- Item type
- Asset code

This will make daily operation significantly faster.

---

# 44. Export

Reports should support:

- Excel
- PDF
- CSV

The exported file should respect the selected filters.

---

# 45. Mobile-Friendly Requirement

The application must be responsive and usable from:

- Desktop
- Laptop
- Tablet
- Mobile browser

The booking screen must be optimized for mobile because bookings may be created while talking to the customer on the phone.

---

# 46. Recommended Technology Architecture

For a low-cost and maintainable system, recommended architecture:

### Frontend

- React / Next.js
- Responsive UI
- Progressive Web App capability

### Backend

- Node.js / NestJS or Next.js API
- REST API

### Database

- PostgreSQL

### Authentication

- Secure session/JWT-based authentication
- Role and permission model

### Hosting

Low-cost cloud hosting can be used initially.

Example architecture:

**Web/PWA**
↓
**API**
↓
**PostgreSQL**

External services:

**WhatsApp Business API**

**Calendar Integration**

---

# 47. Recommended MVP

The first version should focus on the business-critical functions.

## Phase 1 - MVP

- Login
- User permissions
- Categories
- Item types
- Inventory
- Clients
- Bookings
- Equipment availability
- Payment tracking
- Calendar
- Expenses
- Dashboard
- Basic reports
- WhatsApp quotation
- Customer confirmation link
- Client statement

## Phase 2

- Individual asset/serial tracking
- WhatsApp delivery/read status
- Advanced financial reporting
- Advanced inventory reports
- Calendar synchronization
- Automated reminders
- Audit dashboard
- Maintenance management

## Phase 3

- Customer portal
- Online payment
- Digital contracts
- Electronic signatures
- Automated invoice generation
- Advanced analytics
- AI assistant
- Demand forecasting

---

# 48. Important Business Rules

1. Canceled bookings do not consume inventory.
2. Overlapping bookings must be checked before saving.
3. DJ RAK is never included in customer revenue.
4. Internal notes are never shared externally.
5. Pending = Quoted - Collected.
6. Collected amount cannot normally exceed quoted amount.
7. Changing booking dates must trigger an availability check.
8. Changing equipment quantity must trigger an availability check.
9. Customer confirmation reserves inventory.
10. Customer-facing links must not expose internal information.
11. Deleted financial records should preferably be soft-deleted and audited.
12. Expense records must be included in financial reporting based on their transaction date.
13. All important financial and booking changes must be audited.
14. Canceled bookings must be excluded from revenue and inventory calculations.
15. Payment transactions should be stored separately from booking records.

---

# 49. Acceptance Criteria

The system is considered ready for production when:

- Users can log in according to their permissions.
- Admin can create categories and item types.
- Inventory quantities are correctly maintained.
- Clients can be created during booking.
- Users can create bookings.
- Equipment availability is calculated correctly for overlapping dates.
- The system prevents accidental double-booking.
- Booking dates and equipment can be edited.
- Quoted, collected, and pending amounts are accurate.
- DJ RAK is excluded from customer financial totals.
- Expenses are recorded and included in reports.
- Calendar correctly displays bookings.
- Dashboard correctly summarizes the selected date range.
- WhatsApp quotation contains only approved customer information.
- Customer can confirm through the secure link.
- Confirmed booking can be added to a phone calendar.
- Client account statement is accurate.
- Pending payment messages can be sent.
- Revenue vs expense report is accurate.
- Reports can be filtered and exported.
- Audit logs capture important changes.
- Mobile users can create and update bookings comfortably.

---

# 50. Future AI Opportunities

Once enough booking history exists, AI can be added for:

1. Equipment demand forecasting.
2. Recommended equipment packages.
3. Suggested quotation amount.
4. Identifying frequently rented combinations.
5. Predicting equipment shortages.
6. Identifying low-performing equipment.
7. Automatic customer follow-up reminders.
8. Natural-language dashboard queries.

Example:

> "Show me all equipment available on 15 September for a booking worth more than SAR 3,000."

The system could return the relevant inventory immediately.

---

# 51. Final Recommended Workflow

### Step 1
Customer calls.

### Step 2
User searches for client by phone/name.

### Step 3
If client does not exist, user creates the client.

### Step 4
User enters:

- Event dates
- Location
- Equipment required

### Step 5
System calculates availability.

### Step 6
User enters quotation.

### Step 7
User enters internal DJ RAK amount.

### Step 8
User saves the booking.

### Step 9
System reserves the required equipment according to the booking status.

### Step 10
User clicks:

**Send via WhatsApp**

### Step 11
Customer receives quotation without internal information.

### Step 12
Customer clicks confirmation link.

### Step 13
Booking becomes confirmed.

### Step 14
Customer adds the event to their phone calendar.

### Step 15
User records payments when received.

### Step 16
System automatically updates:

- Collected
- Pending
- Financial dashboard
- Client statement

### Step 17
After the event, booking is completed.

### Step 18
Management can review:

- Revenue
- Expenses
- Net collected
- Pending payments
- Equipment usage
- DJ RAK
- Client balances

---

# 52. Key Design Recommendation

The most important design decision is to keep **Booking, Equipment Reservation, Payment, and Expense** as separate transactional records.

Do not store everything in one booking table.

The recommended relationship is:

**Client**
→ many **Bookings**

**Booking**
→ many **Booking Items**

**Booking**
→ many **Payments**

**Item Type**
→ many **Booking Items**

**Expense Type**
→ many **Expenses**

This structure will make inventory availability, financial reporting, client statements, and future expansion much easier and more reliable.

---

# 53. Suggested Project Name

**DJ Rak Inventory & Rental Management System**

Possible short name:

**DJ RAK Manager**

Suggested modules:

- Dashboard
- Bookings
- Calendar
- Inventory
- Clients
- Payments
- Expenses
- Reports
- Setup
- Users
