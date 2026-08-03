# HIMS UI Specification — Indian Hospital Market

## UI goal

Create a premium, calm and fast hospital interface that feels easier than traditional hospital ERP software. Staff should find daily actions quickly without seeing unrelated modules.

## Design system

Use Material UI only for the primary application UI.

### Theme

- Light theme is mandatory for the first production release
- Neutral page background
- White content surfaces
- Healthcare blue/teal primary palette
- Red reserved for critical/destructive states
- Amber for warnings
- Green for completed/available states
- Consistent spacing, border radius, shadows and typography tokens

### Typography

- Readable sans-serif font
- Compact table text but never below accessible sizing
- Strong hierarchy for patient identity, status and primary actions
- Avoid decorative fonts

## Application shell

### Sidebar

- Collapsible
- Maximum 8 top-level entries for normal roles
- Permission-driven
- Section grouping only when necessary
- Current item clearly highlighted
- No three-level navigation

### Header

- Current hospital and branch
- Global patient search
- Notifications
- Contextual quick action
- User profile and session options

## Page patterns

### List pages

- Page title
- One primary action
- Search
- Common filters visible
- Advanced filters in a drawer
- Responsive data table
- Clear row actions
- Pagination
- Saved views only where useful

### Short forms

Use right-side drawers for:

- Quick patient registration
- Small master creation
- Appointment reschedule
- Contact edits

### Complex workflows

Use full pages for:

- OPD consultation
- Billing
- Admission
- IPD charts
- Discharge
- OT notes
- Laboratory result entry

### Status language

Use familiar wording and consistent chips:

- Waiting
- Called
- In Consultation
- Completed
- Vitals Pending / Vitals Recorded
- Pending Payment
- Sample Collected
- Awaiting Approval
- Approved
- Occupied
- Available
- Cleaning
- Cashless Pending
- LAMA
- DOPR
- Discharged
- Expired (death summary context only; use clinically respectful language in UI copy)
## India-specific presentation

- Dates: DD-MM-YYYY
- Time: 12-hour with AM/PM
- Currency: Indian grouping and ₹
- Phone default: +91
- GST/HSN fields where relevant
- A4 print templates
- Thermal receipts
- Barcode and label print layouts
- Translation-ready strings

## Dashboard rules

- Maximum 6–8 useful widgets
- Show real API data only
- No decorative charts without operational value
- Each widget must link to an actionable filtered view
- Use role-specific dashboards (reception today board, nurse vitals backlog, doctor today’s queue, cashier outstanding)
## Key workflow rules

### Outdoor registration

- Minimum required fields first
- Optional sections collapsed
- Duplicate check before save
- Show next action immediately after registration (appointment, walk-in, admit, bill)

### Indoor registration (R3)

- Dedicated admit page, not a drawer-only shortcut
- Bed selection from live availability
- Deposit and attendant captured before confirm

### Appointment

- Single-screen booking
- Doctor availability from real slots only
- Fee and visit type visible before confirmation
- Clear token/check-in/cancel state

### Nurse vitals station

- Queue-centric list of waiting/called patients
- Fast vitals form with sticky save
- Visible “vitals recorded” chip on queue cards
- Doctor consult must not require re-entry of already recorded vitals

### Consultation

- Patient header remains visible
- Previous visit history available without losing current draft
- Shared vitals read/edit from nurse handoff
- Prescription and orders usable from the same workspace
- Sticky save/complete actions
- Email prescription action after complete when permitted

### Billing

- Fast item search by owner categories (OPD, IPD, pathology, radiology, procedure, consultant fee)
- Keyboard navigation
- Totals always visible
- Payment status prominent
- Print and email receipt actions after successful payment

### Bed board

Use simple status colours and filters by ward/floor. Do not overload bed cards with clinical details.

### Discharge / exit outcomes

Support distinct flows and summary titles for:

- Discharge
- LAMA
- DOPR
- Death

Each must show an attach-all-reports package before finalise (lab, radiology, procedure, clinical docs).
## Accessibility

- Keyboard-accessible controls
- Visible focus
- Correct labels
- Sufficient contrast
- Error messages next to fields
- Do not rely on colour alone
- Confirm destructive/irreversible actions

## Responsive behavior

Desktop is primary, but all pages must work on tablet and mobile web.

- Tables may switch to cards on narrow screens
- Primary actions stay accessible
- Sidebars become drawers
- Long forms use sections/steppers only when they reduce confusion
- Do not hide critical patient or payment status

## Forbidden patterns

- Generic purchased admin-template appearance
- Multiple UI frameworks
- Excessive gradient cards
- Nested tab inside tab
- Large empty dashboard charts
- Tiny icon-only actions without labels/tooltips
- Modal forms for long clinical workflows
- Placeholder buttons or fake metrics
