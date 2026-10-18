# QRMeets

QR-based event registration, check-in, and feedback platform. Laravel 11 API backend, React 18 + Vite frontend.

## Running it with Docker

Requires only [Docker Desktop](https://www.docker.com/products/docker-desktop/) — no PHP, Node, or Postgres install needed, everything runs in containers.

```
docker compose up --build -d
docker compose exec backend php artisan demo:seed
```

Then open:

- **App**: http://localhost:5173
- **API**: http://localhost:8000/api

Log in with the seeded demo account: **demo@qrmeets.net** / **Demo12345!** — a pre-verified, pre-approved admin account with 3 completed events (registrations and feedback already on them) and 3 upcoming ones, so there's real data to look at immediately.

To stop it: `docker compose down` (add `-v` to also wipe the database and start fresh next time).

## What's in the box

- `db` — Postgres
- `migrate` — runs the database migrations once, then exits
- `backend` — the Laravel API
- `worker` — processes queued emails and runs the scheduler (event reminders, auto-completing past events); nothing else does this
- `frontend` — the React app, built and served as static files

All configuration needed to run locally is already set in `docker-compose.yml` with safe defaults (a demo-only app key, a local-only database password, email logged to a file instead of actually sent). See `.env.docker.example` for the handful of optional overrides — a real email provider or Gemini API key — if you want those live instead of stubbed out.

## Running it without Docker

See `Backend/README.md` and `Front-end/README.md` for native setup (PHP/Composer and Node directly).
