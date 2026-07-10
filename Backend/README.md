# QR Event Platform — Backend

Laravel 11 + PostgreSQL + Sanctum API for the QR event/attendance platform
(event creation, participant registration with QR codes, check-in scanning,
feedback collection, and an AI-generated feedback summary via the Anthropic
API). Hand-written to match the plan at
`../` project root (see the approved plan doc for the full spec).

## Setup

```bash
cd Backend
composer install
cp .env.example .env
php artisan key:generate
```

Create a PostgreSQL database matching `.env`'s `DB_DATABASE` (default
`qr_event_platform`), then set `DB_USERNAME`/`DB_PASSWORD`/`DB_HOST`/`DB_PORT`
in `.env` to match your local Postgres instance.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

The API is now available at `http://localhost:8000/api`. `storage:link` creates
`public/storage` → `storage/app/public`, which is required for uploaded
payment-proof screenshots to be servable as URLs — skip it and those images
will 404.

To enable the AI feedback summary endpoint, set `ANTHROPIC_API_KEY` in
`.env` (get one from the Anthropic Console). `ANTHROPIC_MODEL` defaults to
`claude-opus-4-8` and can be overridden. The key is read server-side only
(`config('services.anthropic.key')`) and is never included in any API
response.

## Seeded login

The seeder creates one organizer account so you can log in immediately:

- **Email:** `admin@techhub.ph`
- **Password:** `password`

It also seeds the same sample events/registrations/feedback/org profile the
frontend's old mock store (`App.jsx`'s `createStore()`) used to show, so the
app looks identical on first run.

## Notes

- Email verification is stubbed — accounts are auto-verified on
  `POST /auth/register` (no real email delivery is implemented).
- There is a single global `organizations` row (id 1) — no multi-tenancy.
- Any authenticated organizer can access every protected endpoint, including
  approvals — there is no separate admin role, matching the original mock's
  flat access model.
- `status`/`pricing`/`payment_status` are plain validated strings, not
  native PostgreSQL enum columns, so the allowed value sets can change
  without a schema migration.
