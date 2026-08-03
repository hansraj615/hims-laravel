# HIMS Spec Plan — Releases and Owner Spine

## Purpose

This plan converts `HIMS_SPEC.md`, `hims-backend/BACKEND_SPEC.md`, `hims-frontend/FRONTEND_SPEC.md` and `hims-frontend/UI_SPEC.md` into executable implementation tracks.

The repository already contains working Release 1 OPD foundation code in `hims-backend/` and `hims-frontend/`. This document tracks remaining work against production Indian hospital usage and the owner mandate.

## Owner mandate (must ship)

| # | Requirement | Status | Target release |
|---|---|---|---|
| 1 | Outdoor (OPD) patient registration | Largely built — harden | R1 / R1.5 |
| 2 | Indoor (IPD) patient registration / admit | Not started | R3 |
| 3 | Billing: OPD / IPD / pathology / radiology / procedure / consultant fee | OPD cash only | R1.5 (OPD + consultant fee + category masters); R2 (path/rad/procedure); R3 (IPD) |
| 4 | Discharge / LAMA / DOPR / death summary with all attached reports | Not started (DOPR was missing from older specs) | R3 |

## Current production OPD journey (R1 base)

```text
Hospital setup -> Login -> Outdoor registration/ABHA fields -> Appointment -> Check-in/token
-> OPD consultation/FHIR-ready prescription -> Billing/payment/GST -> In-app notification/receipt
```

## Target production OPD journey (R1.5)

```text
Doctor schedule/leave/pricing -> Slot booking -> Check-in/token -> Queue
-> Nurse/compounder vitals -> Doctor consult (sees vitals + visit history) -> Rx email
-> OPD/consultant-fee bill -> Email/SMS/WhatsApp (config-wired)
```

## Non-Negotiable Constraints

- Backend is a Laravel REST API only. No Blade application UI.
- Frontend is an independent React application using Material UI.
- APIs use `/api/v1`.
- Tenant context is resolved server-side from authenticated user assignments.
- Browser-supplied `hospital_id` and `branch_id` are never trusted directly.
- Critical workflows use database transactions.
- No fake dashboard metrics, placeholder buttons or disconnected frontend routes.
- Implementation status files must be updated as milestones finish.
- SMS/WhatsApp must work when credentials are set in config; otherwise log/`pending` only.
- Dev email uses Mailtrap (or local Mailpit SMTP). Static demo OTP must never work in production.

## Release 1 Outcomes (base — largely delivered)

1. Platform admin / hospital admin can manage hospital, branch, departments, roles and users.
2. Staff can log in using email/password and configured demo OTP in non-production.
3. User access is scoped to assigned hospital and branch.
4. Reception can search, register and update patients with UHID generation.
5. Reception can book, reschedule, cancel and check in appointments (slot masters still pending R1.5).
6. OPD queue/token state updates correctly.
7. Doctor can open a patient consultation, record clinical details and prescribe medicines.
8. Billing can create an invoice from consultation fee, collect payment and print a receipt.
9. In-app notifications are recorded; other channels pending R1.5 adapters.
10. Audit logs capture sensitive administrative, clinical and financial actions.
11. Patient identity supports ABHA/ABDM verification fields and future Scan & Share workflows.
12. Billing records snapshot Indian GST fields and support self-pay, scheme and TPA payer foundations.

## Release 1.5 Outcomes (next build — required before calling OPD production-ready)

1. Hospital admin can define doctor weekly schedules, leaves and consultation/consultant-fee pricing.
2. Reception books appointments only from available slots; leave days block booking.
3. Seeded roles include `nurse` and `compounder` with `opd.vitals`.
4. While a patient is waiting/called in queue, nurse/compounder can add or update vitals.
5. Doctor starting consultation sees those vitals and may edit them; prior visit history is visible.
6. Queue supports skip/requeue.
7. Service masters use categories: `opd`, `ipd`, `pathology`, `radiology`, `procedure`, `consultant_fee`.
8. Completing a consult can email the prescription via SMTP/Mailtrap when patient email/consent allows.
9. Email OTP can be delivered through SMTP when real OTP mode is enabled (demo OTP remains non-prod only).
10. SMS and WhatsApp adapters exist behind `config/services.php` / `config/hims.php`; empty credentials → pending/log; set credentials → provider call.
11. Status docs and seeders reflect new permissions and categories.
12. No dead controls on R1.5 screens.

## Release 2 Outcomes (owner billing path/rad/procedure)

1. Lab/radiology/procedure orders from OPD (and later IPD) persist and progress through collection/result/report.
2. Results generate attachable reports on the patient record.
3. Invoices can include pathology, radiology and procedure lines from those orders.
4. Pharmacy and core inventory foundation as specified in `HIMS_SPEC.md`.

## Release 3 Outcomes (owner indoor + discharge package)

1. Indoor registration / admission is distinct from OPD registration.
2. Bed board, bed in-charge, transfer and release work with tenant/branch scope.
3. Daily IPD charges and final IPD billing work end to end.
4. Exit outcomes supported: discharge, LAMA, DOPR, death.
5. Each exit produces a summary package attaching all relevant reports for the admission.
6. Clearance gates final bill appropriately.

## ABDM track (parallel, gated)

| Milestone | Outcome |
|---|---|
| M1 | ABHA verify/create, Scan & Share OPD entry, audited ABDM refs |
| M2 | HIP consent + FHIR link/notify for encounter, Rx, diagnostics, discharge |
| M3 | HIU consent-based fetch/display of external records |

ABHA columns alone do not satisfy M1–M3.

## R1 Backend milestones (historical reference)

Milestones B0–B8 in prior revisions covered Laravel bootstrap through notifications/audit and are largely implemented. See `hims-backend/IMPLEMENTATION_STATUS.md` for live status.

## R1.5 Backend milestones

### B9. Doctor schedule, leave and pricing

- `doctor_schedules`, `doctor_leaves`, `doctor_fee_masters` (or equivalent)
- Slot generation for a date range
- Appointment booking validates slot availability and leave
- Admin APIs + tests

### B10. Shared vitals and clinical roles

- Permissions: `opd.vitals`, retain `opd.consult`
- Seed `nurse`, `compounder`
- Vitals writable on queue entry and on encounter
- Skip/requeue queue actions
- Audit vitals changes

### B11. Patient longitudinal history

- API returning prior encounters/vitals/diagnoses/prescriptions for a patient UHID in tenant scope
- Doctor consult authorization only

### B12. Notification providers and email clinical artefacts

- Email driver via SMTP (Mailtrap/Mailpit documented)
- SMS and WhatsApp interfaces with env/config credentials
- Prescription email on consult complete
- Optional email OTP path when not using demo OTP

### B13. Billing category masters

- Enforce service categories for owner spine
- Ensure OPD + consultant fee invoice paths; reserve path/rad/procedure/IPD for later order linkage

## R1.5 Frontend milestones

### F8. Doctor ops masters

- Schedule, leave and pricing screens for hospital-admin
- Appointment slot picker (no free-typed fake availability)

### F9. Nurse/compounder vitals station

- Queue-facing vitals entry for `opd.vitals`
- Doctor workspace shows shared vitals + editable fields
- Prior visit history panel

### F10. Notification-aware clinical/billing UX

- Indicate Rx email/send status
- Mailtrap-dev verification documented in README

## Data model additions for R1.5

- doctor_schedules
- doctor_leaves
- doctor_fee_masters (or fee columns on schedule by visit type)
- generated slots view/table if persisted
- service_masters.category constrained to owner categories
- vitals may remain JSON on encounter **plus** queue-linked vitals snapshot, or first-class vitals rows keyed by patient/appointment/encounter — prefer auditable shared store

## API groups to add in R1.5

- `/api/v1/admin/doctors/{id}/schedules`
- `/api/v1/admin/doctors/{id}/leaves`
- `/api/v1/admin/doctors/{id}/fees`
- `/api/v1/appointments/slots` (availability)
- `/api/v1/opd/queue/{id}/vitals`
- `/api/v1/opd/queue/{id}/skip` and `/requeue`
- `/api/v1/patients/{id}/clinical-history`
- Notification dispatch remains server-driven; no browser-secret provider keys

## Testing plan additions for R1.5

- Slot booking rejects leave days and taken slots.
- Nurse can write vitals; reception without `opd.vitals` cannot.
- Doctor consult returns existing vitals.
- Clinical history excludes cross-tenant patients.
- With SMS credentials empty, log stays pending; with test double configured, dispatch succeeds.
- Prescription email creates notification log with email channel.

## Implementation sequence (from now)

1. Keep specs and status docs aligned (this revision).
2. Implement B9/F8 doctor schedule/leave/pricing + slot booking.
3. Implement B10/F9 nurse vitals + skip/requeue.
4. Implement B11 history panel.
5. Implement B12/B13 notifications and billing categories.
6. Proceed to R2 diagnostics, then R3 IPD/discharge, with ABDM milestones gated.

## Definition of Ready for Each Milestone

Before starting a milestone:

- Required data ownership is clear.
- API request and response shape is defined.
- Permission names are listed.
- Validation rules are known.
- Audit requirements are identified.
- Frontend page pattern is selected.
- Tests are identified.

## Definition of Done for Each Milestone

A milestone is done only when:

- Migrations and seeders are present where needed.
- APIs persist and return real data.
- Authorization and tenant isolation are tested.
- Frontend uses the real API.
- Loading, empty, validation and error states exist.
- No fake counts, placeholder routes or dead actions remain.
- Relevant implementation status file is updated.
- Tests pass locally.
