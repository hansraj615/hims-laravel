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
2. Simple daily workflows.
3. Role-based menus and dashboards.
4. India-first terminology and formats.
5. Multi-hospital and multi-branch tenant isolation.
6. API-first architecture.
7. No dead buttons, fake counts or disconnected pages.
8. Permission and audit checks for sensitive actions.
9. Data integrity through backend transactions.
10. Automated testing for critical patient, billing and stock workflows.
11. Safe migrations and backward-compatible API versioning.
12. Monitoring, backups and recovery documentation are part of delivery.

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

Demo mode may accept OTP `1234` and `123` through environment configuration. Static OTP must never work in production.

Use permission-based access control. Administrators must be able to create custom roles.

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

- Patient registration
- UHID generation
- Duplicate detection
- Patient documents and consent
- Appointment scheduling
- Doctor schedules and leave
- Token and queue management
- Check-in
- Referral tracking

### OPD and EMR

- Vitals
- Complaints and history
- Examination
- Diagnosis
- Prescription
- Lab/radiology/procedure orders
- Follow-up
- Certificates and advice
- Doctor templates and favourites

### Billing and finance

- OPD/IPD billing
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

- Admission
- Ward/room/bed allocation
- Bed board and transfer
- Nursing assessment
- Vitals and charts
- Medication administration
- Doctor progress notes
- Orders and procedures
- Diet
- Daily charges
- Discharge workflow
- Discharge summary
- LAMA and death summary

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
- Email
- SMS adapter
- WhatsApp adapter
- Future push adapter
- Templates, logs, retries and delivery status

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

### Release 1 — Foundation and OPD

- Authentication
- Tenancy
- Roles and permissions
- Hospital setup
- Patient registration
- Appointments and schedules
- Queue/token
- OPD consultation
- Billing and payment
- Notifications

### Release 2 — Diagnostics and pharmacy

- Laboratory
- Microbiology
- Radiology
- Pharmacy
- Core inventory

### Release 3 — IPD

- Admission
- Bed management
- Nursing and doctor workflows
- Daily charges
- Discharge
- Final billing

### Release 4 — Extended hospital operations

- Emergency
- OT
- Insurance/TPA
- MRD
- Purchase/inventory
- Reports
- Patient portal

### Release 5 — Enterprise modules

- Blood bank
- Infection control
- HR
- Biomedical
- Housekeeping
- External integrations
- Advanced analytics

Each release must be deployable and support complete workflows.

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
