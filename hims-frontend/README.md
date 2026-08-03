# HIMS Frontend

React 19 + TypeScript + Material UI SPA for the HIMS platform.

## Local setup

```bash
cp .env.example .env
npm install
npm run dev
```

Default Vite URL: `http://localhost:5173`  
Default API: `http://localhost:8000/api/v1` (overridable with `VITE_API_BASE_URL`)

## Scripts

- `npm run dev` — development server
- `npm test` — Vitest unit/smoke tests
- `npm run build` — production build
- `npm run playwright` — Playwright suite (when specs are present)

## Deployment notes

1. Build with `npm run build`.
2. Serve `dist/` behind HTTPS on the SPA domain configured in backend Sanctum/CORS.
3. Set `VITE_API_BASE_URL` to the production API `/api/v1` endpoint at build time.
4. Ensure the SPA origin is listed in backend `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS`.
5. Password-reset emails open `/reset-password?token=...&email=...` on this SPA; backend `FRONTEND_URL` must match.

## Rollback notes

1. Redeploy the previous frontend build artifact.
2. Confirm the API version still accepts the previous SPA contracts (`/auth/*`, `/context`, Release 1 workflows).
3. Clear CDN cache if one is in front of the SPA.
4. Smoke-test login, patient search, and billing receipt print.

## Status

See `IMPLEMENTATION_STATUS.md`.
