# HIMS Laravel + React

Production-grade, India-first Hospital Information Management System.

This repository contains two independent applications:

- `hims-backend/` — Laravel REST API, MySQL, tenancy, business logic, queues, notifications, reports, audit, integrations and background processing.
- `hims-frontend/` — React + TypeScript web application using Material UI.

## Product scope

This is not a POC. The target is a live-ready application for clinics, diagnostic centres and small, medium or large multi-branch hospitals in India.

### Owner mandate (must ship)

1. Outdoor (OPD) patient registration
2. Indoor (IPD) patient registration
3. Billing — OPD / IPD / pathology / radiology / procedure / consultant fee
4. Discharge / LAMA / DOPR / death summary with all attached reports

Plus production OPD usability (doctor slots/leaves/pricing, nurse/compounder vitals, visit history, email/SMS/WhatsApp wiring) and ABDM M1/M2/M3 certification readiness.

### Broader modules

Patient registration, OPD, IPD, billing, laboratory, microbiology, radiology, pharmacy, inventory, purchase, OT, emergency, TPA, MRD, blood bank, infection control, HR, biomedical maintenance, housekeeping, notifications, reports, patient portal and external APIs.

## Delivery order

- **R1** — Foundation OPD (largely built)
- **R1.5** — OPD production complete (next)
- **R2** — Diagnostics + pathology/radiology/procedure billing
- **R3** — IPD admit, beds, discharge LAMA/DOPR/death + reports
- **R4/R5** — Extended and enterprise modules
- **ABDM** — M1 → M2 → M3 gated track

## Documentation

- `HIMS_SPEC.md` — product and system specification
- `SPEC_PLAN.md` — release plan and R1.5 outcomes
- `hims-backend/BACKEND_SPEC.md` — Laravel backend specification
- `hims-frontend/FRONTEND_SPEC.md` — React frontend specification
- `hims-frontend/UI_SPEC.md` — India-first UI system
- `hims-backend/IMPLEMENTATION_STATUS.md`
- `hims-frontend/IMPLEMENTATION_STATUS.md`
- `hims-backend/README.md` — local Docker, deploy and rollback notes
- `hims-frontend/README.md` — SPA setup, deploy and rollback notes

## Local development

```bash
# infrastructure
cd hims-backend && docker compose up -d

# API
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve

# SPA
cd ../hims-frontend
npm install
npm run dev
```

Use Mailpit (Docker) or Mailtrap SMTP for email in development. Configure SMS/WhatsApp credentials only when a provider is available — adapters must no-op to log/`pending` otherwise.
