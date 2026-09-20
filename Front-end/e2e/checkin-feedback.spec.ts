import { test, expect, type Page } from '@playwright/test'
import { eventIdBySlug, loginAsAdmin, signUpForEvent } from './helpers'

const EVENT_SLUG = 'e2e-feedback-event'
const EVENT_TITLE = 'E2E Feedback Event'
const PASS_CODE = /^QR-E\d+-P\d+-[A-Z0-9]{8}$/

async function registerAndGetPassCode(page: Page, name: string, email: string) {
  await signUpForEvent(page, EVENT_SLUG, name, email)
  await page.getByRole('button', { name: 'Complete Registration' }).click()
  await expect(page.getByText("You're in!")).toBeVisible()
  const code = (await page.getByText(PASS_CODE).textContent())!.trim()
  expect(code).toMatch(PASS_CODE)
  return code
}

test('only the participant who was checked in can leave feedback, once the event is completed', async ({ browser }) => {
  const suffix = Date.now()
  const attendeeName = `E2E Attendee ${suffix}`
  const absenteeName = `E2E Absentee ${suffix}`

  const orgPage = await (await browser.newContext()).newPage()
  const attendeePage = await (await browser.newContext()).newPage()
  const absenteePage = await (await browser.newContext()).newPage()

  // --- Two participants register; only one will turn up ---
  const attendeeCode = await registerAndGetPassCode(attendeePage, attendeeName, `e2e-attendee-${suffix}@example.com`)
  await registerAndGetPassCode(absenteePage, absenteeName, `e2e-absentee-${suffix}@example.com`)

  // --- Organizer: check the attendee in via the scanner's manual entry ---
  await loginAsAdmin(orgPage)
  await orgPage.goto(`/organizer/events/${await eventIdBySlug(EVENT_SLUG)}`)
  await orgPage.getByRole('button', { name: /^scanner/i }).click()
  const codeInput = orgPage.locator('input[placeholder*="QR-"]')

  await codeInput.fill('QR-E0-P0-NOTAREALCODE')
  await orgPage.getByRole('button', { name: 'Check In' }).click()
  await expect(orgPage.getByText('QR code not recognized')).toBeVisible()

  await codeInput.fill(attendeeCode.toLowerCase()) // hand-typed codes are forgiven their case
  await orgPage.getByRole('button', { name: 'Check In' }).click()
  await expect(orgPage.getByText(new RegExp(`Attendance confirmed.*${attendeeName}`))).toBeVisible()
  await orgPage.screenshot({ path: 'e2e/screenshots/20-scanner-checked-in.png', fullPage: true })

  // Re-scanning the same code reports a duplicate, not a second check-in
  await codeInput.fill(attendeeCode)
  await orgPage.getByRole('button', { name: 'Check In' }).click()
  await expect(orgPage.getByText(new RegExp(`Already checked in.*${attendeeName}`))).toBeVisible()

  // --- Attendee: My Events shows them checked in, but feedback isn't open yet ---
  await attendeePage.goto('/my-events')
  const attendeeCard = attendeePage.locator('.p-4', { hasText: EVENT_TITLE }).filter({ hasText: 'Checked in' })
  await expect(attendeeCard.first()).toBeVisible()
  await attendeePage.screenshot({ path: 'e2e/screenshots/21-my-events-checked-in.png', fullPage: true })
  await attendeeCard.first().click()
  await expect(attendeePage.getByText('Feedback opens once the event wraps up.')).toBeVisible()

  // --- Organizer marks the event completed ---
  await orgPage.getByRole('button', { name: 'Mark Completed' }).first().click()
  await expect(orgPage.getByText('Event marked completed')).toBeVisible()

  // --- Attendee: opening their pass now goes straight to the feedback form ---
  await attendeePage.reload()
  await expect(attendeePage).toHaveURL(/\/feedback\/\d+$/)
  await expect(attendeePage.getByText('How was it?')).toBeVisible()

  // 5 core star ratings + the organizer's custom question
  const coreLabels = ['Check-in experience', 'Event organization', 'Content quality', 'Venue & facilities', 'Overall satisfaction']
  for (const label of coreLabels) {
    const row = attendeePage.locator('div.rounded-xl.bg-slate-50.border.border-slate-200', { hasText: label })
    await row.locator('button').nth(4).click() // 5th star
  }
  const customQuestionLabel = 'What is one thing we could improve?'
  await attendeePage
    .locator('div.rounded-xl.bg-slate-50.border.border-slate-200', { hasText: customQuestionLabel })
    .locator('input')
    .fill('More seating near the front.')
  await attendeePage.getByPlaceholder('What stood out? What could be better?').fill('Great event overall, smooth check-in.')
  await attendeePage.screenshot({ path: 'e2e/screenshots/22-feedback-form-filled.png', fullPage: true })

  await attendeePage.getByRole('button', { name: 'Submit Feedback' }).click()
  await expect(attendeePage).toHaveURL(/\/feedback\/\d+\/done$/)
  await attendeePage.screenshot({ path: 'e2e/screenshots/23-feedback-done.png', fullPage: true })

  // --- Absentee: same completed event, but never checked in - no feedback ---
  await absenteePage.goto('/my-events')
  const absenteeCard = absenteePage.locator('.p-4', { hasText: EVENT_TITLE })
  await expect(absenteeCard.first()).toBeVisible()
  await expect(absenteeCard.filter({ hasText: 'Checked in' })).toHaveCount(0)
  await absenteeCard.first().click()
  await expect(absenteePage.getByText('QR Event Pass')).toBeVisible()
  await absenteePage.waitForTimeout(1500) // long enough for an (unwanted) redirect to have fired
  await expect(absenteePage).toHaveURL(/\/pass\/\d+/)
  await expect(absenteePage.getByText('Taking you to feedback…')).toHaveCount(0)

  // --- Organizer: the attendee's feedback (with the custom answer) shows up ---
  // Reload — feedback is fetched once on mount, not live-polled.
  await orgPage.reload()
  await orgPage.getByRole('button', { name: /^feedback/i }).click()
  await expect(orgPage.getByText('Great event overall, smooth check-in.').first()).toBeVisible()
  await expect(orgPage.getByText(/More seating near the front\./).first()).toBeVisible()
  await orgPage.screenshot({ path: 'e2e/screenshots/24-organizer-feedback-tab.png', fullPage: true })
})
