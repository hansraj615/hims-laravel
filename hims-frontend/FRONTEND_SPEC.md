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

Reception example:

1. Dashboard
2. Patients
3. Appointments
4. OPD Queue
5. Billing
6. Admissions
7. Reports

Doctor example:

1. Today’s Queue
2. Appointments
3. Patients
4. IPD Patients
5. Orders and Results
6. Prescriptions
7. Schedule

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

### Patient registration

- Search before create
- Quick registration under one minute
- Inline duplicate warnings
- Automatic UHID returned from API
- Clear next actions: appointment, walk-in OPD, admission or billing

### Appointment

Single-screen flow:

Patient → Department → Doctor → Date → Slot → Visit Type → Fee → Confirm

### OPD consultation

One complete doctor workspace:

- Patient summary
- History and vitals
- Complaints/examination/diagnosis
- Prescription
- Lab/radiology/procedure orders
- Advice/follow-up
- Draft and complete actions

### Billing

Fast keyboard-friendly item entry, totals always visible, payment drawer, discount approval and print actions.

### IPD

Bed board, patient list, clinical timeline, orders, nursing charts, charges, discharge and clearance states.

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
6. Patient registration
7. Appointment and queue
8. OPD consultation
9. Billing/payment
10. Lab/pharmacy and remaining modules

Update `IMPLEMENTATION_STATUS.md` after each completed workflow.
