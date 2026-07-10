import { test, expect } from '@playwright/test'

// Uses the "E2E Fixture Event" (free, public, approved, always 30 days out)
// created by e2e/global-setup.ts — the seeded demo events carry fixed 2025
// dates and eventually fall into the past.
const EVENT_SLUG = 'e2e-fixture-event'
const EVENT_TITLE = 'E2E Fixture Event'

test('participant can register for a free public event', async ({ page }) => {
  const uniqueEmail = `e2e-${Date.now()}@example.com`

  await page.goto(`/events/${EVENT_SLUG}`)
  await expect(page.getByRole('heading', { name: EVENT_TITLE })).toBeVisible()

  await page.getByRole('button', { name: 'Register', exact: true }).click()
  await expect(page).toHaveURL(new RegExp(`/events/${EVENT_SLUG}/register$`))

  // Step 1: create account
  await page.getByPlaceholder('Juan Dela Cruz').fill('E2E Test User')
  await page.getByPlaceholder('juan@email.com').fill(uniqueEmail)
  await page.getByPlaceholder('••••••••').fill('password123')
  await page.getByRole('button', { name: 'Create Account & Continue' }).click()

  // Step 2: registration form (name/email prefilled from the new account)
  await expect(page.getByRole('button', { name: 'Complete Registration' })).toBeVisible()
  await page.getByRole('button', { name: 'Complete Registration' }).click()

  // Step 3: confirmation
  await expect(page).toHaveURL(new RegExp(`/events/${EVENT_SLUG}/confirm/`))
  await expect(page.getByText("You're in!")).toBeVisible()
  await expect(page.getByText(EVENT_TITLE)).toBeVisible()
})
