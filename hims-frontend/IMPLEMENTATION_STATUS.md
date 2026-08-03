# Frontend Implementation Status

Status values: `Not Started`, `In Progress`, `Blocked`, `Ready for QA`, `Complete`.

A feature may be marked `Complete` only when it uses the real backend API, supports permissions, includes validation and error/loading/empty states, works responsively, has tests for critical flows and contains no placeholder controls.

| Area | Status | Tests | Notes |
|---|---|---|---|
| React/Vite bootstrap | Complete | Passing | React 19, TypeScript, Vite, Vitest and Playwright packages installed; build and smoke test pass |
| Material UI theme | Complete | Passing | Light India-first Material UI theme added |
| Application shell | Complete | Passing | Responsive authenticated shell, permission-filtered sidebar navigation (`≤7` items per role), global patient search (Enter navigates to `/patients?q=`), notifications popover wired to `/notifications`, logout and hospital/branch context header |
| Authentication screens | Ready for QA | Passing | Email/password, OTP, forgot-password and reset-password screens wired to real auth APIs; MFA pending |
| Route/permission guards | Complete | Passing | Authenticated route guard plus `RequirePermission` (redirects to `/dashboard` when the signed-in user's permission array does not include the required permission(s)); global 401 handling clears cached session state and redirects to `/login` |
| Hospital/branch context | Ready for QA | Passing | Shell loads `/api/v1/context`, lists available assignments, and persists selected hospital/branch headers (`X-Hospital-Id` / `X-Branch-Id`) via Zustand |
| Role-based navigation | Complete | Passing | Sidebar items (Dashboard, Patients, Appointments, OPD Queue, Consultations, Billing, Admin) are filtered by the signed-in user's permissions; Hospital/Users/Roles/Branches/Departments are consolidated under a single Admin landing page to stay within the navigation budget |
| Dashboard framework | In Progress | Passing | Dashboard uses real backend health API; no fake metrics added |
| Hospital settings UI | Ready for QA | Passing | Current hospital summary and edit drawer (name, legal name, registration number, GSTIN, phone, status) wired to real `/admin/hospitals` API |
| User management UI | Ready for QA | Passing | User list, filters, and create/edit drawer (name, email, mobile, password, status, role, branch, assignment type) wired to real `/admin/users` and `/admin/roles` APIs |
| Role management UI | Ready for QA | Passing | Role list with permission chips and create/edit drawer with permission checkboxes wired to real `/admin/roles` and `/admin/permissions` APIs; system roles are shown read-only for the name field |
| Branch setup UI | Ready for QA | Passing | Branch list, create and edit drawer use real backend APIs |
| Department setup UI | Ready for QA | Passing | Department list, create and edit drawer use real backend APIs |
| Patient registration/UHID | Ready for QA | Passing | Patient list, filters, hospital-grade registration/edit drawer and duplicate warning use real backend APIs; after registration the user is routed to the new patient profile |
| ABDM M1 (ABHA / Scan & Share) | Ready for QA | Passing | `/abdm` workspace: verify, create, Scan & Share register; uses sandbox-ready gateway status |
| Patient profile/timeline | Ready for QA | Passing | Profile header (UHID, name, age/sex, mobile, ABHA, category/status chips), metadata-only documents list/add dialog and a "Next actions" panel (book appointment, OPD walk-in, billing) wired to real backend APIs |
| Appointments/schedules | Ready for QA | Passing | Booking drawer uses available slots from `/appointments/slots` when a doctor is selected; fee auto-fills from doctor fee master; leave state shown in picker |
| Doctor schedule/leave/pricing UI | Ready for QA | Passing | Admin page `/admin/doctor-ops` for weekly schedule, leaves and consultation fees |
| OPD queue/token | Ready for QA | Passing | Live queue board with vitals pending/recorded chips; call/start/complete/skip/requeue for reception/doctor; nurse/compounder can open vitals dialog |
| Nurse/compounder vitals station | Ready for QA | Passing | Queue vitals dialog wired to `PUT /opd/queue/{id}/vitals`; nav/route includes `opd.vitals` |
| OPD consultation | Ready for QA | Passing | Consultation workspace includes prior-visit history panel; vitals handoff from queue; Rx draft/complete |
| Patient clinical history panel | Ready for QA | Passing | Accordion prior visits beside doctor workspace via `/patients/{id}/clinical-history` |
| Billing/payments | Ready for QA | Passing | Invoice list, create drawer, finalize, payment posting to `/billing/invoices/{id}/payments`, and printable receipt from `/receipt` wired to real APIs with server GST totals |
| Billing category UX (owner spine) | Ready for QA | Passing | Service catalog category filter; invoice create supports owner billing types; path/rad/procedure/IPD order modules still R2/R3 |
| Print/PDF integration | In Progress | Passing | Billing receipt uses browser print of the API receipt payload; formal PDF generation pending |
| Notifications UI | Ready for QA | Passing | Header notifications popover lists recent in-app notification logs from `/api/v1/notifications` |
| Laboratory | Ready for QA | Passing | Shared Diagnostics board for pathology/radiology/procedure; collect + result actions for lab; bill-from-order for billing |
| Microbiology | Not Started | Not Started | |
| Radiology | Ready for QA | Passing | Same Diagnostics board / category filter |
| Pharmacy | Not Started | Not Started | |
| Inventory/purchase | Not Started | Not Started | |
| IPD/admission (indoor registration) | Ready for QA | Passing | `/ipd` admit drawer + active admissions list; uses `ipd.manage` |
| Bed board | Ready for QA | Passing | Ward-filtered bed board with transfer; Care panel for nursing/charges/clearances |
| Nursing charts | Ready for QA | Passing | Care dialog nursing notes + vitals on active admissions |
| Discharge (LAMA/DOPR/death + reports) | Ready for QA | Passing | Exit requires clearance chips; package + draft invoice from daily charges |
| Emergency | Not Started | Not Started | |
| OT | Not Started | Not Started | |
| Insurance/TPA | Not Started | Not Started | |
| MRD | Not Started | Not Started | |
| Blood bank | Not Started | Not Started | |
| Infection control | Not Started | Not Started | |
| HR | Not Started | Not Started | |
| Biomedical | Not Started | Not Started | |
| Housekeeping | Not Started | Not Started | |
| Reports/analytics | Not Started | Not Started | |
| Patient portal | Not Started | Not Started | |
| Responsive/mobile web | Not Started | Not Started | |
| Accessibility review | Not Started | Not Started | |
| Error monitoring | Not Started | Not Started | |
| Production build/deployment | Ready for QA | Passing | Frontend README documents SPA deploy/rollback and env wiring; production build passes in CI workflow |
