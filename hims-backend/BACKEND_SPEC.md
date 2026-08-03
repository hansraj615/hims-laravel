# HIMS Backend Specification

## Stack

- Laravel 12
- PHP 8.3+
- MySQL 8
- Redis
- Laravel Sanctum
- Spatie Laravel Permission
- Laravel Queue, Scheduler and Notifications
- OpenAPI/Swagger
- Pest
- S3-compatible storage

## Purpose

Provide a production-grade REST API and business layer for the HIMS. Do not add Blade application pages. The React frontend is a separate application.

## Architecture

Use a modular monolith with thin controllers and domain-oriented code.

```text
app/
  Domain/
    Hospitals/
    Users/
    Patients/
    Appointments/
    OPD/
    IPD/
    Billing/
    Laboratory/
    Radiology/
    Pharmacy/
    Inventory/
    OperationTheatre/
    Insurance/
    MRD/
    BloodBank/
    InfectionControl/
    HR/
    Biomedical/
    Housekeeping/
    Notifications/
    Reports/
```

Use:

- Form Requests for validation
- Policies and permissions for authorization
- Actions/services for use cases
- Eloquent models and query objects
- API Resources for responses
- Events/listeners for cross-module reactions
- Jobs for async work
- Database transactions for critical workflows
- Domain exceptions mapped to correct HTTP responses

## Enterprise Phase 1 foundation

Before claiming production OPD readiness, the backend must keep stable enterprise schemas and close R1.5 operational gaps (schedules, shared vitals, notification providers).

Phase 1 domains:

- `Patients`: UHID, demographics, outdoor registration, ABHA/ABDM verification fields and consent identity foundation.
- `Audit`: append-only audit log records for CRUD, clinical access and financial modifications.
- `Appointments`: appointment lifecycle, doctor schedule/slot/leave linkage and fee snapshots.
- `OPD`: token and queue state linked to appointments, patients, departments and doctors; shared pre-consult vitals.
- `EMR`: encounters, vitals/complaints/diagnosis structures, prescriptions, longitudinal history queries and FHIR R4 payload columns.
- `Billing`: services with owner categories (`opd`, `ipd`, `pathology`, `radiology`, `procedure`, `consultant_fee`), GST/tax snapshot invoice lines, payments and cashier daybooks.
- `Notifications`: templates, logs, email/SMS/WhatsApp/in-app provider abstractions.
- `IPD` (R3): admission/indoor registration, beds, transfer, discharge outcomes including LAMA/DOPR/death.

Phase 1 schema rules:

- Every tenant-owned table must include `hospital_id`; branch-owned operational records must include `branch_id`.
- Workflow tables must carry lifecycle `status` fields and date/status indexes.
- Financial rows must snapshot prices, discounts, tax rates and tax amounts instead of depending on mutable service master values.
- Clinical and financial records must be designed for immutable audit trails.
- FHIR payload columns must store backend-generated export payloads only; relational schema remains the operational source of truth.
- Numbered operational records must be ready for transaction-safe sequence generation.
- Service masters must carry a constrained `category` for the owner billing spine.

## API standards

- Prefix APIs with `/api/v1`
- Use JSON only
- Use consistent pagination, filtering and sorting
- Return validation errors in a predictable shape
- Use proper HTTP status codes
- Add request IDs
- Support idempotency for appointment, payment and external-write APIs
- Publish OpenAPI documentation
- Maintain backward-compatible versioning

Pagination and filtering contracts:

- List endpoints must support `page`, `per_page`, `sort`, `direction` and documented filters.
- Responses must include pagination metadata in the existing API envelope `meta`.
- Unbounded collection endpoints are acceptable only for small master data and must be documented.

Response shape:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {},
  "meta": {},
  "errors": null,
  "request_id": "uuid"
}
```

## Tenancy

- Scope tenant-owned tables using `hospital_id`
- Scope branch-owned records using `branch_id`
- Resolve tenant context from the authenticated user and approved assignments
- Add global scopes only where safe and explicit service-layer checks for critical operations
- Test cross-tenant access denial
- Audit platform-level access to clinical data

## Authentication

- Sanctum secure cookie authentication for the first-party React app
- Token authentication for external APIs
- Mobile OTP and email/password login
- Demo OTP only in non-production environments
- Real OTP delivery through configured email (SMTP/Mailtrap) and later SMS adapters when demo OTP is disabled
- Login throttling, account lock, session/device history and logout-all
- Optional admin MFA extension point

## Roles and permissions (starter set)

Seed and document at least:

| Role | Representative permissions |
|---|---|
| `platform-admin` | All |
| `hospital-admin` | Admin + patients + appointments + billing |
| `reception` | `patients.manage`, `appointments.manage` |
| `nurse` | `opd.vitals`, queue view |
| `compounder` | `opd.vitals`, queue view |
| `doctor` | `opd.consult` (may also hold `opd.vitals`) |
| `billing` | `billing.manage` |
| `bed-in-charge` | `ipd.beds` (R3) |
| `lab` | Lab permissions (R2) |

Custom roles remain supported via Spatie.
## Data integrity

Use transactions and locking where needed for:

- Invoice numbering and payment posting
- Refunds and credit notes
- Pharmacy dispensing and returns
- Inventory receipt, issue and transfer
- Bed assignment and transfer
- Admission and discharge
- Lab report approval and amendment
- TPA settlement

Prevent duplicate numbering through database constraints and safe sequence generation.

## Security

- Never trust hospital or branch IDs from the frontend
- Validate and authorize every protected endpoint
- Mask PHI and credentials in logs
- Encrypt sensitive integration settings
- Validate uploads by MIME type, extension and size
- Store audit logs for clinical record access, edits, approvals, billing changes, refunds, stock changes and administrative actions
- Do not hard-delete completed clinical or financial records
- Add retention and archival support
- Mask identity values such as Aadhaar, ABHA number and government IDs in normal resources.
- Expose full identity values only through explicit permissions and audited access paths.

## Performance

- Require indexed tenant, branch, status and date columns
- Prevent N+1 queries
- Paginate large datasets
- Queue exports, reports and notifications
- Cache stable masters with tenant-aware keys
- Add rate limits to authentication and public/external APIs
- Provide health, readiness and queue-health endpoints

## Notifications

Create provider abstractions for:

- Email (SMTP — Mailtrap or Mailpit in development; production SMTP/provider)
- SMS (config-driven; empty credentials → persist `pending`/log only)
- WhatsApp (config-driven; same rule)
- In-app
- Future push notifications

Store notification templates, attempts, status, provider response and retry history.

Wire clinical events:

- Patient registered
- Appointment booked/cancelled/reminder
- Payment received
- Prescription ready / emailed
- OTP challenges when real OTP mode is enabled

Never put provider secrets in the frontend. Configure via `.env` → `config/services.php` / `config/hims.php`.

## ABDM gateway (certification track)

- Provide a gated ABDM client abstraction (disabled unless configured).
- M1: ABHA verify/create and Scan & Share intake APIs.
- M2: HIP consent + FHIR record link/notify using backend-built FHIR payloads.
- M3: HIU fetch/display under consent.
- Persist transaction references and audit every ABDM call.
- Patient ABHA fields alone do not satisfy certification.
## Files and reports

- Use Laravel filesystem abstraction
- Local driver for development
- S3-compatible production driver
- Signed/authorized download endpoints
- Generate PDFs asynchronously when heavy
- Support CSV/Excel exports through queued jobs

## Testing

Required test layers:

- Unit tests for domain rules
- Feature/API tests for endpoints
- Policy and tenant-isolation tests
- Transaction and concurrency tests for billing, stock and beds
- Integration tests for queues, files and notifications
- Seeded end-to-end API journey tests

## Production operations

Provide:

- `.env.example`
- Docker Compose for local development
- Queue worker and scheduler configuration
- Migration and rollback instructions
- Backup and restore documentation
- Deployment checklist
- Health endpoints
- Structured logging
- Error-monitoring integration point
- CI for linting, tests and migration safety

## Initial implementation order

1. Laravel bootstrap and environment setup
2. Tenancy model
3. Authentication and demo OTP guard
4. Roles and permissions
5. Hospital/branch/user administration
6. Outdoor patient registration and UHID
7. Appointment schedules, booking and token queue
8. OPD consultation
9. Billing and payment
10. Notifications foundation
11. **R1.5** — doctor schedule/leave/pricing, shared vitals, history API, email/SMS/WhatsApp providers, billing categories
12. Laboratory, radiology, procedure billing (R2)
13. Indoor registration, beds, discharge LAMA/DOPR/death + attached reports (R3)
14. Remaining modules and ABDM M1→M2→M3 from `HIMS_SPEC.md`

All backend work must update `IMPLEMENTATION_STATUS.md`.
