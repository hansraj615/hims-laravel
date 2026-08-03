# HIMS Backend

Laravel 12 REST API for the India-first HIMS platform.

## Local setup

1. Start infrastructure:

```bash
docker compose up -d
```

This starts MySQL (`localhost:3306`, db/user `hims`, password `secret`), Redis (`6379`), and Mailpit (`8025` UI / `1025` SMTP).

2. Configure the app:

```bash
cp .env.example .env
# point DB_* at docker mysql, MAIL_MAILER=smtp, MAIL_HOST=127.0.0.1, MAIL_PORT=1025
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API base URL: `http://localhost:8000/api/v1`

Demo users from the seeder use password `password`:

- `platform@example.com`
- `admin@example.com`
- `reception@example.com`
- `doctor@example.com`
- `billing@example.com`
- `nurse@example.com`
- `compounder@example.com`

## Email / SMS / WhatsApp

- **Email:** set SMTP in `.env` (Mailpit locally: `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`; or Mailtrap sandbox host/user/pass).
- **OTP:** with `DEMO_OTP_ENABLED=true`, use codes `1234`/`123`. With `DEMO_OTP_ENABLED=false`, OTP is emailed to the staff user's email.
- **SMS / WhatsApp:** leave `SMS_*` / `WHATSAPP_*` empty to keep logs `pending`; set `*_DRIVER`, `*_API_KEY`, and `*_ENDPOINT` to deliver.
- Completing a consult emails the prescription when the patient has `email` + `consent_email`.

## Tests

```bash
php artisan test
```

## Deployment notes

1. Build and deploy the API app separately from the React SPA.
2. Set `APP_ENV=production`, generate a unique `APP_KEY`, and set `DEMO_OTP_ENABLED=false`.
3. Configure MySQL, Redis, mail, Sanctum stateful domains, and `FRONTEND_URL` for password-reset links.
4. Run `php artisan migrate --force` during release.
5. Restart PHP-FPM/queue workers after deploy (`php artisan queue:restart`).
6. Keep sessions/cookies on a trusted domain shared with the SPA CSRF origin.

## Rollback notes

1. Redeploy the previous API artifact/image.
2. Roll back only migrations that are explicitly reverse-safe for that release.
3. Restart workers after rollback.
4. Verify `/api/v1/health` and a sample authenticated `/api/v1/context` call.
5. If password/auth mailers changed, confirm `FRONTEND_URL` still points at the active SPA.

## Status

See `IMPLEMENTATION_STATUS.md` and root `SPEC_PLAN.md`.
