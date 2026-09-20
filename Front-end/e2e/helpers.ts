import { execFileSync } from 'node:child_process'
import type { Page } from '@playwright/test'
import { API_URL, BACKEND_DIR, backendEnv } from './env.mjs'

// The seeded admin (DatabaseSeeder): verified, approved, role "admin".
export const ADMIN_EMAIL = 'admin@techhub.ph'
export const ADMIN_PASSWORD = 'password'

// Strong enough for the password rules on every sign-up form.
export const STRONG_PASSWORD = 'E2e-Passw0rd!2026'

export type AccountType = 'organizer' | 'participant'

/** Runs a snippet through `php artisan tinker` against the scratch database. */
export function tinker(script: string): string {
  return execFileSync('php', ['artisan', 'tinker', '--execute', script], {
    cwd: BACKEND_DIR,
    env: backendEnv,
    encoding: 'utf8',
  })
}

const MODELS: Record<AccountType, string> = {
  organizer: 'App\\Models\\Organizer',
  participant: 'App\\Models\\Participant',
}

/**
 * Stands in for clicking the link in the verification email (the app blocks
 * everything until an account is verified, and mail never leaves the
 * scratch environment).
 */
export function verifyEmail(type: AccountType, email: string) {
  tinker(`${MODELS[type]}::where('email', '${email}')->update(['email_verified_at' => now()]);`)
}

/**
 * Creates a verified organizer directly - skips the sign-up UI. "approved"
 * ones can host; "pending" ones are still waiting on the admin, with the
 * kind of details an application carries.
 */
export function createOrganizer(email: string, name: string, status: 'approved' | 'pending', password = STRONG_PASSWORD) {
  const approval = status === 'approved' ? ", 'approved_at' => now()" : ''
  tinker(`
    $o = App\\Models\\Organizer::create([
      'name' => '${name}', 'email' => '${email}', 'contact_number' => '0917 555 0199',
      'password' => Illuminate\\Support\\Facades\\Hash::make('${password}'),
    ]);
    $o->forceFill(['email_verified_at' => now(), 'approval_status' => '${status}'${approval}])->save();
  `)
}

/** A submitted-for-review event owned by an existing organizer, for the admin to decide on. */
export function createPendingEvent(slug: string, title: string, organizerEmail: string) {
  tinker(`
    $o = App\\Models\\Organizer::where('email', '${organizerEmail}')->firstOrFail();
    App\\Models\\Event::create([
      'slug' => '${slug}', 'title' => '${title}', 'type' => 'Workshop', 'is_private' => false,
      'description' => 'Waiting on the admin.', 'venue' => 'Pending Venue', 'location' => 'Test City',
      'date' => now()->addDays(20)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
      'organized_by' => $o->name, 'industry' => 'Technology', 'capacity' => 30, 'status' => 'pending',
      'feedback_enabled' => true, 'pricing' => 'free', 'price' => 0, 'allow_walk_ins' => false,
      'socials' => [], 'custom_fields' => [], 'tags' => [], 'organizer_id' => $o->id,
    ]);
  `)
}

export async function eventIdBySlug(slug: string): Promise<number> {
  const res = await fetch(`${API_URL}/events/${slug}`)
  if (!res.ok) throw new Error(`No event "${slug}" (HTTP ${res.status})`)
  return (await res.json()).id
}

export async function loginAs(page: Page, type: AccountType, email: string, password: string) {
  await page.goto(`/login?type=${type}`)
  await page.getByPlaceholder('you@organization.com').fill(email)
  await page.getByPlaceholder('••••••••').fill(password)
  await page.locator('form').getByRole('button', { name: 'Log In' }).click()
  await page.waitForURL('**/my-events')
}

export const loginAsAdmin = (page: Page) => loginAs(page, 'organizer', ADMIN_EMAIL, ADMIN_PASSWORD)

/**
 * Registers a fresh participant account for an event the way a visitor does:
 * create the account inline, "verify" it, and land on the registration form.
 * Stops there - the caller clicks "Complete Registration" (or not).
 */
export async function signUpForEvent(page: Page, slug: string, name: string, email: string, { direct = false } = {}) {
  // `direct` skips the event page's Register button, which isn't offered once
  // an event is full (see the waitlist spec).
  if (direct) {
    await page.goto(`/events/${slug}/register`)
  } else {
    await page.goto(`/events/${slug}`)
    await page.getByRole('button', { name: 'Register', exact: true }).click()
  }
  await page.getByPlaceholder('Juan Dela Cruz').fill(name)
  await page.getByPlaceholder('juan@email.com').nth(0).fill(email)
  await page.getByPlaceholder('juan@email.com').nth(1).fill(email)
  await page.getByPlaceholder('••••••••').nth(0).fill(STRONG_PASSWORD)
  await page.getByPlaceholder('••••••••').nth(1).fill(STRONG_PASSWORD)
  await page.getByRole('button', { name: 'Create Account & Continue' }).click()

  await page.getByRole('heading', { name: 'Check your inbox' }).waitFor()
  verifyEmail('participant', email)
  await page.getByRole('button', { name: "I've verified — Continue" }).click()
  await page.getByRole('button', { name: 'Complete Registration' }).waitFor()
}

export function futureDateInput(daysFromNow: number): string {
  const d = new Date()
  d.setDate(d.getDate() + daysFromNow)
  return d.toISOString().slice(0, 10) // yyyy-mm-dd, matches <input type="date">
}
