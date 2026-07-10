import { test, expect } from '@playwright/test'
import { loginAsOrganizer } from './helpers'

const EVENT_SLUG = 'e2e-fixture-event'
const EVENT_TITLE = 'E2E Fixture Event'

test('scanned check-in unlocks feedback, including the custom question', async ({ browser }) => {
  const orgContext = await browser.newContext()
  const partContext = await browser.newContext()
  const orgPage = await orgContext.newPage()
  const partPage = await partContext.newPage()

  const uniqueEmail = `e2e-checkin-${Date.now()}@example.com`
  const participantName = 'E2E Checkin Participant'

  // --- Participant: register for the fixture event ---
  await partPage.goto(`/events/${EVENT_SLUG}`)
  await partPage.getByRole('button', { name: 'Register', exact: true }).click()
  await partPage.getByPlaceholder('Juan Dela Cruz').fill(participantName)
  await partPage.getByPlaceholder('juan@email.com').fill(uniqueEmail)
  await partPage.getByPlaceholder('••••••••').fill('password123')
  await partPage.getByRole('button', { name: 'Create Account & Continue' }).click()
  await expect(partPage.getByRole('button', { name: 'Complete Registration' })).toBeVisible()
  await partPage.getByRole('button', { name: 'Complete Registration' }).click()
  await expect(partPage.getByText("You're in!")).toBeVisible()

  const qrCode = await partPage
    .locator('div.flex.justify-between', { hasText: 'Pass' })
    .locator('span')
    .nth(1)
    .textContent()
  expect(qrCode).toMatch(/^QR-/)

  // --- Organizer: check the participant in via the scanner's manual entry ---
  await loginAsOrganizer(orgPage)
  await orgPage.goto('/organizer/events')
  await orgPage.locator('tr', { hasText: EVENT_TITLE }).getByRole('button', { name: 'Manage' }).click()
  await orgPage.getByRole('button', { name: /^scanner/i }).click()
  await orgPage.locator('input[placeholder*="QR-"]').fill(qrCode!.trim())
  await orgPage.getByRole('button', { name: 'Check In' }).click()
  await expect(orgPage.getByText(new RegExp(`Attendance confirmed.*${participantName}`))).toBeVisible()
  await orgPage.screenshot({ path: 'e2e/screenshots/20-scanner-checked-in.png', fullPage: true })

  // Re-scanning the same code should report a duplicate, not a second check-in
  await orgPage.locator('input[placeholder*="QR-"]').fill(qrCode!.trim())
  await orgPage.getByRole('button', { name: 'Check In' }).click()
  await expect(orgPage.getByText(new RegExp(`Already checked in.*${participantName}`))).toBeVisible()

  // --- Participant: My Tickets now shows them checked in ---
  await partPage.goto('/my-tickets')
  const ticketCard = partPage.locator('.p-4', { hasText: EVENT_TITLE }).filter({ hasText: 'Checked in' })
  await expect(ticketCard.first()).toBeVisible()
  await partPage.screenshot({ path: 'e2e/screenshots/21-my-tickets-checked-in.png', fullPage: true })

  await ticketCard.first().click()
  await expect(partPage.getByRole('button', { name: 'Leave Feedback' })).toBeVisible()
  await partPage.getByRole('button', { name: 'Leave Feedback' }).click()

  // --- Feedback form: 5 core star ratings + the organizer's custom question ---
  await expect(partPage.getByText('How was it?')).toBeVisible()
  const coreLabels = ['Check-in experience', 'Event organization', 'Content quality', 'Venue & facilities', 'Overall satisfaction']
  for (const label of coreLabels) {
    const row = partPage.locator('div.rounded-xl.bg-slate-50.border.border-slate-200', { hasText: label })
    await row.locator('button').nth(4).click() // 5th star
  }

  const customQuestionLabel = 'What is one thing we could improve?'
  await expect(partPage.getByText(customQuestionLabel)).toBeVisible()
  await partPage
    .locator('div.rounded-xl.bg-slate-50.border.border-slate-200', { hasText: customQuestionLabel })
    .locator('input')
    .fill('More seating near the front.')

  await partPage.getByPlaceholder('What stood out? What could be better?').fill('Great event overall, smooth check-in.')
  await partPage.screenshot({ path: 'e2e/screenshots/22-feedback-form-filled.png', fullPage: true })

  await partPage.getByRole('button', { name: 'Submit Feedback' }).click()
  await expect(partPage).toHaveURL(/\/feedback\/\d+\/done$/)
  await partPage.screenshot({ path: 'e2e/screenshots/23-feedback-done.png', fullPage: true })

  // --- Organizer: the feedback (with the custom answer) shows up on the event ---
  // Reload — feedback is fetched once on mount, not live-polled, so a
  // stale tab from before the participant submitted wouldn't show it.
  await orgPage.reload()
  await orgPage.getByRole('button', { name: /^feedback/i }).click()
  await expect(orgPage.getByText('Great event overall, smooth check-in.').first()).toBeVisible()
  await expect(orgPage.getByText(/More seating near the front\./).first()).toBeVisible()
  await orgPage.screenshot({ path: 'e2e/screenshots/24-organizer-feedback-tab.png', fullPage: true })

  await orgContext.close()
  await partContext.close()
})
