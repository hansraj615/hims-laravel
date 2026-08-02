# HIMS Laravel + React

Production-grade, India-first Hospital Information Management System.

This repository contains two independent applications:

- `hims-backend/` — Laravel REST API, MySQL, tenancy, business logic, queues, notifications, reports, audit, integrations and background processing.
- `hims-frontend/` — React + TypeScript web application using Material UI.

## Product scope

This is not a POC. The target is a live-ready application for clinics, diagnostic centres and small, medium or large multi-branch hospitals in India.

The system must provide complete workflows for patient registration, OPD, IPD, billing, laboratory, microbiology, radiology, pharmacy, inventory, purchase, OT, emergency, TPA, MRD, blood bank, infection control, HR, biomedical maintenance, housekeeping, notifications, reports, patient portal and external APIs.

## First production journey

Patient Registration → Appointment → Check-in/Token → Consultation → Prescription/Orders → Billing → Pharmacy/Laboratory.

## Documentation

- `HIMS_SPEC.md` — product and system specification
- `hims-backend/BACKEND_SPEC.md` — Laravel backend specification
- `hims-frontend/FRONTEND_SPEC.md` — React frontend specification
- `hims-frontend/UI_SPEC.md` — India-first UI system
- `hims-backend/IMPLEMENTATION_STATUS.md`
- `hims-frontend/IMPLEMENTATION_STATUS.md`

Codex must inspect these files before implementation and keep the status trackers current.
