import { test, expect } from '@playwright/test'
import { loginAsOrganizer } from './helpers'

const EVENT_SLUG = 'e2e-waitlist-event'
const EVENT_TITLE = 'E2E Waitlist Event'

async function registerParticipant(page: import('@playwright/test').Page, name: string, email: string) {
  await page.goto(`/events/${EVENT_SLUG}`)
  await page.getByRole('button', { name: 'Register', exact: true }).click()
  await page.getByPlaceholder('Juan Dela Cruz').fill(name)
  await page.getByPlaceholder('juan@email.com').fill(email)
  await page.getByPlaceholder('••••••••').fill('password123')
  await page.getByRole('button', { name: 'Create Account & Continue' }).click()
  await page.getByRole('button', { name: 'Complete Registration' }).click()
}

test('registering over capacity auto-waitlists, and the organizer can promote', async ({ browser }) => {
  const suffix = Date.now()
  const firstContext = await browser.newContext()
  const secondContext = await browser.newContext()
  const orgContext = await browser.newContext()
  const firstPage = await firstContext.newPage()
  const secondPage = await secondContext.newPage()
  const orgPage = await orgContext.newPage()

  // Names/emails deliberately avoid the word "waitlist" so it only ever
  // appears as the status badge, not also as a substring match in the
  // guest's own name/email — that ambiguity is why the first pass here
  // failed with a strict-mode violation.
  const firstName = `Capacity Guest First ${suffix}`
  const secondName = `Capacity Guest Second ${suffix}`

  // Capacity is 1 — the first registrant gets the spot...
  await registerParticipant(firstPage, firstName, `capacity-first-${suffix}@example.com`)
  await expect(firstPage.getByText("You're in!")).toBeVisible()
  await expect(firstPage.getByText('Your spot is confirmed.')).toBeVisible()

  // ...the second is over capacity and lands on the waitlist.
  await registerParticipant(secondPage, secondName, `capacity-second-${suffix}@example.com`)
  await expect(secondPage.getByText("You're on the waitlist")).toBeVisible()
  await secondPage.screenshot({ path: 'e2e/screenshots/30-participant-waitlisted.png', fullPage: true })

  // Organizer sees the waitlisted guest flagged in the guest list.
  await loginAsOrganizer(orgPage)
  await orgPage.goto('/organizer/events')
  await orgPage.locator('tr', { hasText: EVENT_TITLE }).getByRole('button', { name: 'Manage' }).click()
  await orgPage.getByRole('button', { name: /^guests/i }).click()
  await expect(orgPage.getByText('1 waitlisted')).toBeVisible()

  const waitlistedRow = orgPage.locator('div.rounded-xl', { hasText: secondName }).first()
  await expect(waitlistedRow.getByText('Waitlist', { exact: true })).toBeVisible()
  await orgPage.screenshot({ path: 'e2e/screenshots/31-organizer-guests-waitlisted.png', fullPage: true })

  // Promote them off the waitlist.
  await waitlistedRow.getByTitle('Promote off waitlist').click()
  await expect(orgPage.getByText(`${secondName} moved off the waitlist`)).toBeVisible()
  await expect(orgPage.locator('div.rounded-xl', { hasText: secondName }).first().getByText('Waitlist', { exact: true })).not.toBeVisible()
  await orgPage.screenshot({ path: 'e2e/screenshots/32-organizer-guests-promoted.png', fullPage: true })

  await firstContext.close()
  await secondContext.close()
  await orgContext.close()
})
