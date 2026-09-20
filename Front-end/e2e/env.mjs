import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Shared by playwright.config.ts, serve-backend.mjs and the helpers. Plain
// .mjs so the Node script that boots the API can import it without a TS step.

const here = path.dirname(fileURLToPath(import.meta.url))

export const BACKEND_DIR = path.resolve(here, '../../Backend')

// The whole suite runs against its own throwaway SQLite file, on its own
// ports, so it can never touch (or accidentally reuse a server pointed at)
// the developer's real local database. serve-backend.mjs recreates the file
// from scratch on every run.
export const E2E_DB = path.join(BACKEND_DIR, 'database', 'e2e.sqlite')
export const API_PORT = 8010
export const APP_PORT = 5183
export const API_URL = `http://127.0.0.1:${API_PORT}/api`
export const APP_URL = `http://localhost:${APP_PORT}`

// Real environment variables beat Backend/.env (Laravel's env repository is
// immutable), which is what points every php process below at the scratch DB.
// `php artisan serve` strips most of the environment it is given, which is why
// serve-backend.mjs starts PHP's built-in server directly instead.
export const backendEnv = {
  ...process.env,
  APP_ENV: 'local',
  APP_DEBUG: 'true',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: E2E_DB,
  DB_URL: '',
  FRONTEND_URL: APP_URL,
  // Mail is captured in memory and never leaves the machine; cache/session in
  // memory means login throttling can't trip up back-to-back tests.
  MAIL_MAILER: 'array',
  QUEUE_CONNECTION: 'sync',
  CACHE_STORE: 'array',
  SESSION_DRIVER: 'array',
  LOG_CHANNEL: 'stderr',
  ADMIN_CONTACT_NUMBER: '0917 000 0000',
  // Blank on purpose: Backend/.env may hold a real key, and the organizer's
  // feedback tab would otherwise send the test feedback to Google. Without a
  // key the AI summary answers a clean 503, which the page already handles.
  GEMINI_API_KEY: '',
}
