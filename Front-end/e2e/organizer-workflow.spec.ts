import { test, expect } from '@playwright/test'
import { loginAsOrganizer, futureDateInput } from './helpers'

test('organizer can draft, edit, submit, approve, and duplicate an event', async ({ page }) => {
  const title = `Playwright Draft Event ${Date.now()}`

  await loginAsOrganizer(page)
  await page.goto('/organizer/events')

  await page.getByRole('button', { name: 'Create Event' }).first().click()
  await expect(page.getByRole('heading', { name: 'Create Event' })).toBeVisible()

  // Step 1: Details
  await page.getByPlaceholder('Founder Networking Night').fill(title)
  await page.locator('select').nth(0).selectOption({ label: 'Workshop' }) // Type
  await page.getByPlaceholder('e.g. Main Auditorium').fill('Playwright Test Venue')
  await page.getByPlaceholder('e.g. Taguig City, Metro Manila').fill('Test City')
  await page.locator('input[type="date"]').fill(futureDateInput(45))
  await page.locator('input[type="time"]').nth(0).fill('09:00')
  await page.locator('input[type="time"]').nth(1).fill('11:00')
  await page.locator('input[type="number"]').fill('20')

  await page.screenshot({ path: 'e2e/screenshots/01-create-event-step1.png', fullPage: true })

  // Save as draft from step 1 (should not require step 2/3 fields)
  await page.getByRole('button', { name: 'Save Draft' }).click()
  await expect(page).toHaveURL(/\/organizer\/events\/\d+\/edit$/)

  // Back to the events list — new event shows up as a draft
  await page.goto('/organizer/events')
  const row = page.locator('tr', { hasText: title })
  await expect(row).toBeVisible()
  await expect(row.getByText('draft', { exact: true })).toBeVisible()
  await page.screenshot({ path: 'e2e/screenshots/02-events-list-draft.png', fullPage: true })

  // Manage -> submit for approval
  await row.getByRole('button', { name: 'Manage' }).click()
  await expect(page.getByRole('heading', { name: title })).toBeVisible()
  await expect(page.getByText('draft', { exact: true }).first()).toBeVisible()

  await page.getByRole('button', { name: 'Submit for approval' }).click()
  await expect(page.getByText('pending', { exact: true }).first()).toBeVisible()
  await page.screenshot({ path: 'e2e/screenshots/03-event-detail-pending.png', fullPage: true })

  // Approvals page: approve it
  await page.goto('/organizer/approvals')
  const approvalCard = page.locator('.p-4', { hasText: title })
  await expect(approvalCard).toBeVisible()
  await approvalCard.getByRole('button', { name: 'Approve' }).click()
  await expect(page.locator('.p-4', { hasText: title })).not.toBeVisible()

  // Back to event detail: now approved
  await page.goto('/organizer/events')
  await page.locator('tr', { hasText: title }).getByRole('button', { name: 'Manage' }).click()
  await expect(page.getByText('approved', { exact: true }).first()).toBeVisible()
  await page.screenshot({ path: 'e2e/screenshots/04-event-detail-approved.png', fullPage: true })

  // Duplicate -> lands on a new draft copy
  await page.getByRole('button', { name: 'Duplicate' }).click()
  await expect(page).toHaveURL(/\/organizer\/events\/\d+\/edit$/)
  await page.goto('/organizer/events')
  await expect(page.locator('tr', { hasText: `${title} (Copy)` })).toBeVisible()
})
