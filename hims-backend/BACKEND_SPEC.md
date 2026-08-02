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
- Login throttling, account lock, session/device history and logout-all
- Optional admin MFA extension point

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

- Email
- SMS
- WhatsApp
- In-app
- Future push notifications

Store notification templates, attempts, status, provider response and retry history.

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
6. Patient registration and UHID
7. Appointment schedules, booking and token queue
8. OPD consultation
9. Billing and payment
10. Notifications
11. Laboratory and pharmacy
12. IPD and remaining modules from `HIMS_SPEC.md`

All backend work must update `IMPLEMENTATION_STATUS.md`.
