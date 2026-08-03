# HIMS — Production Product Specification

## 1. Product vision

Build a live-ready, India-first Hospital Information Management System for clinics, diagnostic centres and small, medium or large multi-branch hospitals.

This is not a proof of concept. The software must be architected and implemented for real hospital use, real patient data, real billing, real operations, production security, auditability, backups, monitoring and deployment.

The product must remain easy for hospital staff. It should feel familiar to Indian users and avoid the visual and operational clutter commonly found in legacy hospital ERP products.

## 2. Application split

Maintain two independent applications in this repository:

```text
hims-backend/   Laravel REST API
hims-frontend/  React web application
```

They must have separate dependencies, environment files, tests, build pipelines and deployment processes.

## 3. Core technology

### Backend

- Laravel 12
- PHP 8.3+
- MySQL 8
- Laravel Sanctum
- Spatie Laravel Permission
- Redis
- Laravel queues and scheduler
- Laravel Notifications
- OpenAPI/Swagger
- Pest
- S3-compatible storage abstraction

### Frontend

- React 19
- TypeScript
- Vite
- Material UI
- React Router
- TanStack Query
- React Hook Form
- Zod
- Axios
- Zustand only for UI/application context state
- Recharts
- Playwright

Do not mix Material UI, Bootstrap, Tailwind and Ant Design in the main frontend. Material UI is the application design system.

## 4. Product principles

1. Production-ready, not demo-only.
2. Simple daily workflows that match how Indian hospitals actually operate.
3. Role-based menus and dashboards (reception, nurse/compounder, doctor, billing, bed in-charge, admin).
4. India-first terminology and formats.
5. Multi-hospital and multi-branch tenant isolation.
6. API-first architecture.
7. No dead buttons, fake counts or disconnected pages.
8. Permission and audit checks for sensitive actions.
9. Data integrity through backend transactions.
10. Automated testing for critical patient, billing and stock workflows.
11. Safe migrations and backward-compatible API versioning.
12. Monitoring, backups and recovery documentation are part of delivery.
13. End-to-end journeys only — every released screen must complete a real staff workflow against persistence, not demo stubs.

## 4.1 Owner mandate (non-negotiable spine)

These four capabilities are mandatory product acceptance criteria for a live hospital deployment. They may be delivered across releases, but none may be deferred indefinitely or left as module labels only.

1. **Outdoor patient registration (OPD)** — UHID, demographics, ABHA readiness, visit creation, appointment/walk-in.
2. **Indoor patient registration (IPD)** — distinct admit workflow (ward/bed, admitting doctor, provisional diagnosis, attendant, deposits).
3. **Billing** — first-class billable categories: OPD, IPD, pathology, radiology, procedure, consultant fee.
4. **Patient exit documentation** — discharge, **LAMA**, **DOPR** (Discharge on Patient Request), and death summary, each able to attach all relevant reports for the stay/visit.

## 5. India-first interface

Use terminology such as:

- UHID
- OPD
- IPD
- TPA
- MRD
- OT
- Token
- Ward
- Bed
- Cashless
- Discharge Summary
- Lab Sample
- Pharmacy Bill

Formats:

- Date: `DD-MM-YYYY`
- Time: 12-hour with AM/PM
- Currency: `₹1,25,000.00`
- Phone default: `+91`
- GST and HSN fields where applicable
- A4 and thermal print layouts
- Translation-ready UI for Hindi and regional languages

## 6. Multi-hospital model

Support:

- Platform
- Hospital group
- Hospital
- Branch/facility
- Department
- Unit
- Ward
- Room
- Bed

All tenant-owned records must be scoped by `hospital_id`; branch-owned records must also use `branch_id`.

The backend must resolve tenant context from the authenticated user/session and approved assignments. Never trust tenant identifiers sent by the browser without server-side validation.

## 7. Authentication and permissions

Support:

- Mobile OTP login
- Email/password login
- Forgot password
- Secure session handling
- Logout current device
- Logout all devices
- Account lock and throttling
- Session/device history
- Optional admin MFA

Demo mode may accept OTP `1234` and `123` through environment configuration. Static OTP must never work in production. When demo OTP is disabled, OTP must be delivered through configured email (SMTP/Mailtrap) and/or SMS adapters.

Use permission-based access control. Administrators must be able to create custom roles. Starter clinical/ops roles must include reception, nurse, compounder, doctor, billing, bed in-charge (R3), lab (R2), hospital admin and platform admin.
## 8. Production modules

### Platform and hospital administration

- Hospital onboarding
- Hospital groups and branches
- Departments and service units
- Users, roles and permissions
- Feature settings
- Numbering formats
- Service masters
- Pricing and packages
- Audit logs
- Integration credentials

### Patient and front desk

- Outdoor (OPD) patient registration
- Indoor (IPD) patient registration / admit entry
- UHID generation
- Duplicate detection
- Patient documents and consent
- Appointment scheduling against real doctor slots
- Doctor schedules, consultation pricing and leave
- Token and queue management
- Check-in / cancel / reschedule / start
- Pre-consult vitals by nurse or compounder while patient is in queue
- Referral tracking

### OPD and EMR

- Shared vitals (nurse/compounder and doctor may create/update; doctor sees existing vitals when consultation starts)
- Complaints and history
- Longitudinal patient visit history for the doctor (prior encounters, vitals, diagnoses, prescriptions)
- Examination
- Diagnosis
- Prescription with print and email delivery
- Lab/radiology/procedure orders
- Follow-up
- Certificates and advice
- Doctor templates and favourites

### Billing and finance

- First-class service categories: `opd`, `ipd`, `pathology`, `radiology`, `procedure`, `consultant_fee`
- OPD and consultant-fee billing
- Pathology, radiology and procedure billing linked to orders/results
- IPD billing (room/bed, daily charges, procedures, final bill)
- Service, package and room charges
- Deposits and advances
- Discounts and approvals
- Refunds
- Credit billing
- Cash, UPI, card, bank, cheque, insurance and mixed payments
- Cashier shifts and closing
- GST fields where applicable
- Receipts, credit notes and debit notes
- Outstanding and settlement reports

### IPD and nursing

- Indoor registration / admission
- Ward/room/bed allocation
- Bed board, bed in-charge workflows, transfer and release
- Nursing assessment
- Vitals and charts
- Medication administration
- Doctor progress notes
- Orders and procedures
- Diet
- Daily charges
- Discharge clearance workflow
- Discharge summary with all attached reports
- Exit outcomes: normal discharge, **LAMA**, **DOPR**, death summary

### Emergency

- Quick/unknown-patient registration
- Triage
- MLC flag
- Initial assessment
- Emergency orders and treatment
- Observation
- Conversion to OPD/IPD
- Referral and discharge

### Laboratory and microbiology

- Test masters and profiles
- Sample collection and barcodes
- Worklists
- Result entry
- Reference ranges
- Abnormal and critical values
- Verification and approval
- Amendments
- Outsourced tests
- Turnaround tracking
- Microbiology microscopy, culture, organism and AST workflows

### Radiology

- Procedure masters
- Scheduling and worklist
- Reporting templates
- Approval
- PDF reports
- Critical findings alerts
- PACS/RIS integration interface

### Pharmacy

- Medicine and generic masters
- Batch, expiry, GST, HSN, MRP and purchase price
- Prescription dispensing
- Walk-in sales
- Returns
- Purchase and supplier return
- Stock transfer and adjustment
- FEFO
- Near-expiry and low-stock alerts

### Inventory and purchase

- Item and vendor masters
- Purchase requests and approvals
- Purchase orders
- GRN
- Quality check
- Department issues and returns
- Stock transfers
- Reorder levels
- Batch/serial/asset tracking
- Consumption and valuation

### OT and procedures

- OT scheduling
- Team allocation
- Pre-operative and safety checklists
- Anaesthesia assessment
- Implant and consumable usage
- Operative notes
- Recovery
- Billing

### Insurance and TPA

- Payer and policy masters
- Pre-authorisation
- Estimates
- Document checklists
- Queries and enhancement requests
- Final claim
- Settlement, deduction and rejection
- Patient payable
- Ageing reports

### MRD and documents

- Medical record index
- Consent and patient documents
- Discharge records
- Birth/death records
- Record requests and movement logs
- Retention policies
- Access audit

### Blood bank

- Donor registration and screening
- Collection and testing
- Component preparation
- Inventory
- Crossmatch
- Issue/return/discard
- Traceability and adverse reactions

### Infection control

- HAI surveillance
- SSI and device-associated infections
- Hand-hygiene audits
- Isolation and MDR alerts
- Antibiogram
- Needle-stick and exposure tracking
- Outbreak and CAPA registers
- Environmental and OT surveillance

### HR and workforce

- Employee master
- Departments/designations
- Attendance, shifts, roster and leave
- Credentials and licence expiry
- Training and vaccination records
- Payroll integration interface

### Biomedical and facility operations

- Equipment and asset masters
- AMC/CMC and warranty
- Preventive maintenance and calibration
- Breakdown complaints and downtime
- Housekeeping tasks
- Bed cleaning
- Linen
- Waste logs
- Facility complaints

### Patient portal

- OTP login
- Profile and family members
- Appointment booking
- Prescriptions
- Reports
- Bills and receipts
- Discharge summaries
- Notifications and feedback

### Notifications

- In-app
- Email via SMTP (development: Mailtrap or Mailpit; production: configured SMTP/provider)
- SMS provider adapter behind config (no send when credentials empty; logs/`pending` only)
- WhatsApp provider adapter behind config (same rule)
- Future push adapter
- Templates, logs, retries and delivery status
- Email OTP delivery for login when real OTP is enabled
- Prescription and receipt email on clinical/billing completion where consented

### Reports and analytics

- OPD and appointments
- New/repeat patients
- Doctor/department statistics
- Revenue, collection, refunds and outstanding
- Admission, discharge, occupancy and length of stay
- Lab volume, pending work and TAT
- Pharmacy sales, margin, stock and expiry
- Purchase and consumption
- TPA ageing
- OT utilisation
- Infection-control indicators
- User and audit activity

## 9. External APIs

Use versioned APIs under `/api/v1`.

Prepare for:

- Doctor/department discovery
- Availability and slot publishing
- Appointment booking/reschedule/cancellation
- Patient registration under consent rules
- Reports and payment status
- Webhooks

Implement rate limiting, idempotency, API audit logs and OpenAPI documentation.

## 10. Security and compliance baseline

- Tenant isolation
- Backend authorization on every protected action
- CSRF/CORS/cookie security
- Strong password hashing
- Secret encryption
- Sensitive-log masking
- Safe file uploads
- Session timeout
- Audit and access logs
- Change history for sensitive records
- No deletion of completed clinical/financial records
- Database transactions for critical workflows
- Backup, restore and disaster-recovery documentation
- Consent and privacy tracking
- Production HTTPS only

## 10.1 Indian enterprise compliance baseline

This HIMS is a full-fledged Indian enterprise hospital platform. Clinical, financial and operational modules must support NABH-style auditability, ABDM interoperability and certification readiness, Indian billing compliance and strict privacy controls.

### NHA, ABDM and ABHA

- Store ABHA number, ABHA address, verification state, verification timestamp and ABDM transaction references on patient records.
- Support ABHA OTP verification workflows and ABDM Scan & Share OPD registration.
- Store raw ABDM payloads only in controlled JSON fields with access control and audit logging.
- Track consent references before exchanging records through HIP/HIU workflows.
- Mask Aadhaar, ABHA and government identity values in normal API/UI responses unless explicit audited permission allows full access.
- Storing ABHA columns or emitting a minimal FHIR Bundle is **not** ABDM certification. Certification requires gated M1/M2/M3 milestones below.

### ABDM certification milestones (M1 / M2 / M3)

Feature-flag ABDM gateway integration. Each milestone needs sandbox evidence, audit trails and production-safe credentials — not stub UI alone.

| Milestone | Scope | Depends on |
|---|---|---|
| **M1** | ABHA creation/verification, demographics linked to ABHA, Scan & Share OPD registration entry | Patient registration, consent, OTP/notification wiring |
| **M2** | HIP — patient consent artefact, health-record link/notify for encounter, prescription, diagnostics and discharge summaries as FHIR R4 | Clinical documents from OPD/IPD/diagnostics |
| **M3** | HIU — consent-based fetch and display of external linked health records | Stable M2 + consent UX |

Implement M1 after OPD production hardening; M2 when exportable clinical artefacts exist; M3 only after M2 is reliable.

### FHIR R4 interoperability

- EMR encounters, prescriptions, lab reports, radiology reports and discharge summaries must maintain exportable FHIR R4 JSON payloads.
- The backend is the source of truth for FHIR construction. Frontend forms collect clinical data but do not assemble authoritative FHIR payloads.
- FHIR payloads must remain traceable to internal records, patient UHID/ABHA identity and audit events.

### Government schemes, TPA and GST

- Billing must support self-pay, corporate, government scheme and private TPA payer types.
- Government scheme workflows must account for PM-JAY/Ayushman Bharat, CGHS, ECHS and state scheme package flows.
- TPA workflows must support pre-authorisation, enhancement, claim submission, query, deduction, settlement and patient-payable split.
- Billable masters and invoice lines must support HSN/SAC, CGST, SGST and IGST snapshots.
- Print output must support A4 invoices and 2-inch/3-inch thermal receipts.

### NABH auditability

- All administrative CRUD, clinical record access/modification and financial changes must be auditable.
- Audit records must capture actor, tenant, branch, request ID, IP address, user agent, timestamp, target record and before/after values where relevant.
- Audit logs must be append-only at application level and designed for future hash-chain verification.

## 11. Availability and operations

Production delivery must include:

- Health endpoints
- Structured logs
- Error monitoring integration points
- Queue monitoring
- Database backup schedule
- Restore drill documentation
- Storage lifecycle policy
- Deployment and rollback documentation
- Environment separation
- CI checks
- Zero hard-coded secrets

## 12. Implementation order

Release R1 foundation (auth, tenancy, patient/UHID, appointment, queue, consultation, OPD billing, in-app notifications) already exists in code. Subsequent releases close owner-mandate and production gaps.

### Release 1 — Foundation and OPD (base — largely built)

- Authentication
- Tenancy
- Roles and permissions
- Hospital setup
- Outdoor patient registration
- Appointments and queue/token
- OPD consultation foundation
- Billing and payment foundation
- In-app notifications and audit foundation

### Release 1.5 — OPD production complete (next build track)

Closes outdoor journey usability for real Indian OPD counters:

- Doctor weekly schedules → bookable slots
- Doctor leaves / blocked days
- Consultation pricing / consultant-fee masters
- Appointment booking only against available slots
- Roles: nurse, compounder (`opd.vitals`); queue vitals station
- Doctor sees and may edit shared vitals; longitudinal visit history
- Queue skip/requeue
- Service-category masters for all owner billing types (`opd`, `ipd`, `pathology`, `radiology`, `procedure`, `consultant_fee`)
- Live OPD + consultant-fee billing paths
- Email via SMTP/Mailtrap (or Mailpit locally); email OTP when enabled; prescription email
- SMS and WhatsApp adapters: config-wired; pending/log when credentials absent
- ABDM M1 foundation work may start here (feature-flagged)

### Release 2 — Diagnostics and pharmacy

Completes owner billing for pathology, radiology and procedure:

- Laboratory and microbiology
- Radiology
- Procedure orders/results/reports
- Category billing + report attachments into patient record
- Pharmacy
- Core inventory

### Release 3 — IPD (owner indoor registration + discharge package)

- Indoor patient registration / admission
- Bed management, bed in-charge, transfer, release
- Nursing and doctor IPD workflows
- Daily charges and IPD billing
- Discharge clearance
- Exit outcomes: discharge, LAMA, DOPR, death
- Discharge/death/LAMA/DOPR summary with **all attached reports**
- Final IPD invoice
- ABDM M2 artefacts for discharge and IPD documents

### Release 4 — Extended hospital operations

- Emergency
- OT
- Insurance/TPA depth
- MRD
- Purchase/inventory depth
- Reports
- Patient portal
- ABDM M3 HIU

### Release 5 — Enterprise modules

- Blood bank
- Infection control
- HR
- Biomedical
- Housekeeping
- External integrations
- Advanced analytics

Each release must be deployable and support complete staff workflows against real APIs. Owner mandate items #1–#4 are complete only when the corresponding release acceptance above is met end to end.

## 13. Definition of done

A feature is complete only when:

- Backend API and database persistence work
- React frontend uses the real API
- Tenant isolation and permissions are enforced
- Validation and error handling exist
- Loading and empty states exist
- Audit logs exist where required
- Printing/PDF works where required
- Seed data and migration paths exist
- Automated tests cover critical flows
- Responsive UI works
- Documentation is updated
- No fake data, dead action or placeholder route remains
- Production configuration and operational impact are documented
