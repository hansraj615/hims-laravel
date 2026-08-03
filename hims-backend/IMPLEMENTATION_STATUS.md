# Backend Implementation Status

Status values: `Not Started`, `In Progress`, `Blocked`, `Ready for QA`, `Complete`.

A module may be marked `Complete` only when migrations, API, authorization, tenant isolation, validation, audit requirements, tests and documentation are complete.

| Area | Status | Tests | Notes |
|---|---|---|---|
| Laravel bootstrap | Complete | Passing | Laravel 12 scaffold, API routes, Sanctum, Spatie Permission, Pest, request IDs and `/api/v1/health` are in place |
| Environment and Docker | Ready for QA | Passing | `.env.example` configured for MySQL, Redis, Sanctum SPA origins, frontend URL, login lockout and demo OTP flags; `docker-compose.yml` provides MySQL, Redis and Mailpit |
| CI pipeline | Ready for QA | Passing | GitHub Actions workflows added for backend (`php artisan test`) and frontend (`npm test` + build) |
| Tenant model | Complete | Passing | Hospital group, hospital, branch, department and user assignment tables/models added with context resolver and isolation tests |
| Authentication | Ready for QA | Passing | Email/password login, forgot/reset password mail flow, current user, logout current/all devices, session history and failed-attempt account lockout are implemented; MFA pending |
| Demo OTP guard | Complete | Passing | Environment-gated demo OTP supports configured codes and throws if enabled in production |
| Roles and permissions | Complete | Passing | Spatie installed; starter permissions and roles seeded; role list, permission list, and role create/update APIs added with system-role protection and permission-scoping rules for non platform-admins |
| Hospital/branch setup | Complete | Passing | Core tables, demo seed, branch create/update APIs, tenant-safe read APIs and hospital update API (name/legal name/registration/GSTIN/phone/status, audited) added |
| Users and assignments | Complete | Passing | User assignment model, demo assignments, tenant-scoped user list API, and user create/update APIs (role sync, hospital/branch/department assignment upsert, platform-admin role guard, cross-tenant update guard, audited) added |
| Patient registration/UHID | Complete | Passing | Hospital-grade patient demographics, category/source, identity, ABHA/ABDM verification fields, guardian/emergency contacts, consent fields, tenant-scoped APIs, duplicate search, transaction-generated UHID sequence, metadata-only patient documents API and create/update audit events added |
| Appointments/schedules | Ready for QA | Passing | Booking validates available schedule slots when the doctor has schedules; auto fee from doctor fee masters; leave blocks booking; slot availability API at `/appointments/slots` |
| Doctor schedule/leave/pricing | Ready for QA | Passing | Tables/models for schedules, leaves and fee masters; admin APIs under `/admin/doctors/{id}/schedules|leaves|fees`; demo doctor seeded Mon–Sun windows + visit-type fees |
| OPD queue/token | Ready for QA | Passing | Queue listing and call/start/complete/skip/requeue lifecycle APIs; skip from waiting/called → `skipped`; requeue from skipped/called → `waiting`; nurse/compounder with `opd.vitals` can view queue and write pre-consult vitals; vitals copied into encounter on consultation start |
| Shared OPD vitals (nurse/compounder) | Ready for QA | Passing | `opd_queues.vitals` JSON + `PUT/GET /opd/queue/{id}/vitals`; roles `nurse`/`compounder` seeded (`nurse@example.com`, `compounder@example.com` / password) |
| Patient clinical history API | Ready for QA | Passing | `GET /patients/{id}/clinical-history` for `opd.consult` or `patients.manage`; returns prior completed visits with vitals/diagnoses/Rx summary; supports `exclude_encounter_id` |
| OPD consultation | Ready for QA | Passing | Consultation start-from-queue/appointment, draft update (vitals/complaints/history/examination/diagnoses/care plan/follow-up), prescription upsert (delete+recreate items), completion with minimal FHIR R4 Bundle (Encounter + MedicationRequest) generation, queue/prescription state sync, and R1 draft-invoice creation from appointment fee or the `OPDCONSULT` catalog service are implemented behind `opd.consult`, with tenant-safe hospital-generated encounter/prescription number sequences and audit logging; edits are blocked once completed |
| Billing/payments | Ready for QA | Passing | Service catalog CRUD, draft invoice create/update with server-authoritative GST calculation (CGST+SGST intra-state default, IGST override, tax-exempt zeroing), finalize/void (void only permitted while unpaid) and atomic payment posting (invoice balance/status transition to partially/fully paid, overpay rejection with 0.01 tolerance, per user/branch/day cashier daybook auto-open and cash roll-up, receipt payload) are implemented behind `billing.manage`, with hospital-generated invoice/receipt number sequences and audit logging on finalize and payment |
| Billing service categories (owner spine) | Ready for QA | Passing | `services.category` enum (`opd`/`ipd`/`pathology`/`radiology`/`procedure`/`consultant_fee`); required on create/update; catalog filter `?category=`; demo seed includes OPDREG, IPDDAY, CBC, XRAYCHEST, DRESSING + OPDCONSULT as consultant_fee |
| Notifications | Ready for QA | Passing | Dispatcher delivers email via SMTP Mailable; SMS/WhatsApp adapters leave `pending` when credentials empty and call HTTP endpoint when configured; templates include `auth.login_otp` and `prescription.ready` |
| Email/SMS/WhatsApp providers | Ready for QA | Passing | Email (Mailtrap/Mailpit SMTP); SMS/WhatsApp via `config/services.php` env keys; OTP emails staff user when `DEMO_OTP_ENABLED=false`; Rx email on consult complete when patient has email + consent |
| ABDM M1 gateway | Ready for QA | Passing | Feature-flagged M1: verify/create OTP flows + Scan & Share register; HTTP gateway when credentials set, simulated provider otherwise; `abdm_transactions` + audit |
| ABDM M2 HIP / M3 HIU | Not Started | Not Started | After clinical artefacts exist |
| Laboratory | Ready for QA | Passing | Shared diagnostics order model covers pathology (and radiology/procedure); worklist collect/result; patient document attach on result; bill creates draft invoice linked via billable morph |
| Microbiology | Not Started | Not Started | |
| Radiology | Ready for QA | Passing | Same thin diagnostics order workflow as pathology (`category=radiology`) |
| Pharmacy | Not Started | Not Started | |
| Inventory/purchase | Not Started | Not Started | |
| IPD/admission (indoor registration) | Ready for QA | Passing | Distinct admit API (`/ipd/admissions`) with ward/bed allocation, attendant + provisional diagnosis; hospital-generated IPD numbers |
| Bed management | Ready for QA | Passing | Seeded wards/beds; bed board; transfer; release on exit; auto bed-day charge lines from admit |
| Nursing | Ready for QA | Passing | Thin IPD nursing chart notes + vitals on active admissions (`/ipd/admissions/{id}/nursing-notes`) |
| Discharge (incl. LAMA/DOPR/death + reports) | Ready for QA | Passing | Exit outcomes + report package; clearance gates (nursing/diagnostics/billing/ward) required; invoice from open daily charge lines |
| Emergency | Not Started | Not Started | |
| OT | Not Started | Not Started | |
| Insurance/TPA | Not Started | Not Started | |
| MRD | Not Started | Not Started | |
| Blood bank | Not Started | Not Started | |
| Infection control | Not Started | Not Started | |
| HR | Not Started | Not Started | |
| Biomedical | Not Started | Not Started | |
| Housekeeping | Not Started | Not Started | |
| Reports | Not Started | Not Started | |
| Patient portal APIs | Not Started | Not Started | |
| External APIs/webhooks | Not Started | Not Started | |
| Audit/access logs | In Progress | Passing | Append-only audit log schema/model with tenant, actor, request, IP, target record, before/after and hash-chain fields; `AuditLogger` service computes per-hospital (or global) hash chains and is wired into hospital, user, role, patient (create/update/document), OPD consultation (start/update/complete) and billing (invoice finalize/void, payment posted) mutations |
| Backups/restore docs | In Progress | Not Started | High-level restore guidance still pending; deployment/rollback notes added to backend README |
| Deployment/rollback docs | Ready for QA | Not Started | Backend README documents production deploy and rollback steps including migrations, workers and DEMO OTP guards |
