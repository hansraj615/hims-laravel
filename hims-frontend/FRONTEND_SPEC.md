# HIMS Frontend Specification

## Stack

- React 19
- TypeScript
- Vite
- Material UI
- React Router
- TanStack Query
- React Hook Form
- Zod
- Axios
- Zustand for UI and context state only
- Recharts
- React Testing Library
- Playwright

## Purpose

Provide a production-grade web application for the Laravel HIMS API. Keep frontend and backend independently runnable and deployable.

## Structure

```text
src/
  api/
  app/
  assets/
  components/
  config/
  features/
    auth/
    dashboard/
    hospitals/
    patients/
    appointments/
    opd/
    ipd/
    billing/
    laboratory/
    radiology/
    pharmacy/
    inventory/
    insurance/
    operation-theatre/
    reports/
  hooks/
  layouts/
  routes/
  services/
  store/
  theme/
  types/
  utils/
```

Each feature should own its pages, components, API hooks, schemas, types and tests.

## Design principles

- India-first hospital workflows
- Maximum 8 primary menu items per role
- Clean light theme
- White and soft-grey surfaces
- One healthcare accent palette
- Compact but readable spacing
- Fast searchable tables
- Drawers for short forms
- Full pages for consultation, billing, admission and discharge
- Sticky actions for long workflows
- Keyboard-friendly forms
- A4 and thermal print layouts
- No fake charts, dead controls or placeholder routes

## Role-based navigation

Generate navigation from backend permissions.

The frontend must not hardcode production route or action visibility. It must consume backend permission context from `/api/v1/context` or `/api/v1/context/permissions` and hide or disable navigation items and row actions when permission is absent. Backend authorization remains mandatory even when the UI hides an action.

Reception example:

1. Dashboard
2. Patients
3. Appointments
4. OPD Queue
5. Billing
6. Admissions (when IPD enabled)
7. Reports

Nurse / compounder example:

1. Today’s Queue
2. Vitals Station
3. Patients (read)
4. Notifications

Doctor example:

1. Today’s Queue
2. Appointments
3. Patients / History
4. Consultations
5. IPD Patients (when enabled)
6. Orders and Results
7. Schedule

Billing / cashier example:

1. Billing
2. Payments / Daybook
3. Patients
4. Reports

Bed in-charge example (R3):

1. Bed Board
2. Admissions
3. Transfers
4. Discharge Clearance

Lab example:

1. Dashboard
2. Sample Collection
3. Worklist
4. Results
5. Approval
6. Reports

## API integration

Use one Axios client with:

- Base URL from environment
- Sanctum CSRF handling
- Credentials enabled
- Request IDs
- Global unauthorized/session-expired handling
- Validation error mapping
- Cancellation support
- Safe retry rules

Use TanStack Query for server state. Do not duplicate API data in Zustand.

Use Zustand only for:

- Current hospital/branch context
- Sidebar and layout state
- Temporary workflow context that is not server-owned

## Core UX workflows

### Outdoor (OPD) patient registration

- Search before create
- Quick registration under one minute
- Inline duplicate warnings
- Automatic UHID returned from API
- ABHA/ABDM verification state visible on patient identity views
- Scan & Share registration entry point when ABDM integration is enabled
- Mask Aadhaar, ABHA and government identity values in lists unless permission allows full identity viewing
- Clear next actions: appointment, walk-in OPD, admission or billing

### Indoor (IPD) patient registration (R3)

- Distinct admit form (not a reuse of OPD quick-reg alone)
- Admitting doctor, ward/bed, provisional diagnosis, attendant, deposit
- Immediate bed board and nursing handoff

### Appointment

Single-screen flow:

Patient → Department → Doctor → Date → **Available slot** → Visit Type → Fee → Confirm

Slots and fees come from doctor schedule/pricing masters. Leave days must not offer slots.

### Nurse / compounder vitals (R1.5)

- Queue-first vitals station for patients in waiting/called states
- Capture BP, pulse, temp, SpO2, weight/height and free notes as required by hospital
- Doctor consult opens with these vitals already populated
- Doctor may also add or edit vitals during consultation

### OPD consultation

One complete doctor workspace:

- Patient summary
- Longitudinal visit history (prior encounters, vitals, diagnoses, Rx)
- Current vitals (shared)
- Complaints/examination/diagnosis
- Prescription with print and email actions
- Lab/radiology/procedure orders
- Advice/follow-up
- Draft and complete actions
- ICD-10/SNOMED coding fields where diagnosis is captured
- FHIR export state visible for completed clinical documents

### Billing

Fast keyboard-friendly item entry, totals always visible, payment drawer, discount approval and print actions. Billing screens must display GST/HSN/SAC snapshots for taxable items and payer context for self-pay, government scheme, corporate and private TPA billing.

Support owner categories as catalogues and invoice sources: OPD, IPD, pathology, radiology, procedure, consultant fee (enable as clinical modules land).

### IPD

Bed board, patient list, clinical timeline, orders, nursing charts, charges, discharge clearance and exit outcomes: discharge, LAMA, DOPR, death — each with summary + attached reports.

## Accessibility and resilience

- Keyboard navigation
- Visible focus states
- Semantic labels
- Accessible contrast
- Skeleton loading
- Empty states
- Retryable errors
- Error boundaries
- Unsaved-change warnings
- Offline/network failure messaging
- Responsive desktop/tablet/mobile-web layouts

## Testing

- Component tests for shared controls
- Form validation tests
- Route/permission guard tests
- API integration tests with mocked network responses
- Playwright tests for critical end-to-end journeys
- Visual checks for key pages and print layouts

## Deployment

- Separate production build
- Environment-specific API URL
- Nginx/CDN compatible
- HTTPS only in production
- No secrets in frontend environment variables
- Cache-busting and rollback documentation
- Error-monitoring integration point

## Initial implementation order

1. Vite/React bootstrap
2. Material UI theme and layout
3. Authentication and route guards
4. Hospital/branch context
5. Role-based navigation
6. Outdoor patient registration
7. Appointment and queue
8. OPD consultation
9. Billing/payment
10. **R1.5** — doctor schedule/leave/pricing UI, vitals station, visit history, Rx email indicators
11. Lab/radiology/procedure and remaining modules
12. IPD admit, bed board, discharge LAMA/DOPR/death UX

Update `IMPLEMENTATION_STATUS.md` after each completed workflow.
