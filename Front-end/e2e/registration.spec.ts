import { test, expect } from '@playwright/test'
import { signUpForEvent } from './helpers'

// Uses the "E2E Fixture Event" (free, public, approved, always 30 days out)
// created by e2e/global-setup.ts — the seeded demo events carry fixed 2025
// dates and eventually fall into the past.
const EVENT_SLUG = 'e2e-fixture-event'
const EVENT_TITLE = 'E2E Fixture Event'

test('participant can create an account, verify it, and register for a free public event', async ({ page }) => {
  const name = 'E2E Test User'
  const email = `e2e-${Date.now()}@example.com`

  await page.goto(`/events/${EVENT_SLUG}`)
  await expect(page.getByRole('heading', { name: EVENT_TITLE })).toBeVisible()

  // Account -> verify -> form (a fresh account can't register until verified).
  await signUpForEvent(page, EVENT_SLUG, name, email)

  // The form no longer re-asks for name/email - it says who it's registering.
  await expect(page.getByText(/Registering as/)).toContainText(name)
  await expect(page.getByText(/Registering as/)).toContainText(email)

  await page.getByRole('button', { name: 'Complete Registration' }).click()

  await expect(page).toHaveURL(new RegExp(`/events/${EVENT_SLUG}/confirm/`))
  await expect(page.getByText("You're in!")).toBeVisible()
  await expect(page.getByText(EVENT_TITLE)).toBeVisible()
  // The pass code is random per registration (QR-E<event>-P<seq>-<suffix>).
  await expect(page.getByText(/^QR-E\d+-P\d+-[A-Z0-9]{8}$/)).toBeVisible()
})

test('registering blocks a mismatched confirmation email, and login mode offers password recovery', async ({ page }) => {
  await page.goto(`/events/${EVENT_SLUG}/register`)

  await page.getByPlaceholder('Juan Dela Cruz').fill('Mismatch Tester')
  await page.getByPlaceholder('juan@email.com').nth(0).fill('one@example.com')
  await page.getByPlaceholder('juan@email.com').nth(1).fill('two@example.com')
  await page.getByPlaceholder('••••••••').nth(0).fill('E2e-Passw0rd!2026')
  await page.getByPlaceholder('••••••••').nth(1).fill('E2e-Passw0rd!2026')
  await page.getByRole('button', { name: 'Create Account & Continue' }).click()
  await expect(page.getByText('Emails do not match')).toBeVisible()

  // Existing account? The login tab carries a forgot-password link that
  // returns to this same registration once the password is reset.
  await page.locator('form').getByRole('button', { name: 'Log in', exact: true }).click()
  const forgot = page.getByRole('link', { name: 'Forgot your password?' })
  await expect(forgot).toBeVisible()
  await forgot.click()
  await expect(page).toHaveURL(/\/forgot-password\?type=participant&next=/)
})
