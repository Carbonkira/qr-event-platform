import { test, expect } from '@playwright/test'
import { loginAsAdmin, futureDateInput } from './helpers'

test('organizer can draft, submit, get approved, and duplicate an event', async ({ page }) => {
  const title = `Playwright Draft Event ${Date.now()}`

  await loginAsAdmin(page)

  await page.getByRole('button', { name: 'Create Event' }).first().click()
  await expect(page.getByRole('heading', { name: 'Create Event' })).toBeVisible()

  // Step 1: Details (the first dropdown is the organization - an admin can
  // leave it as "No organization")
  await page.getByPlaceholder('Founder Networking Night').fill(title)
  await page.locator('select').nth(1).selectOption({ label: 'Workshop' }) // Type
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

  // My Events - the new event shows up as a draft
  await page.goto('/my-events')
  const card = page.locator('.p-4', { hasText: title })
  await expect(card).toBeVisible()
  await expect(card.getByText('draft', { exact: true })).toBeVisible()
  await page.screenshot({ path: 'e2e/screenshots/02-my-events-draft.png', fullPage: true })

  // Open it -> submit for approval
  await card.click()
  await expect(page.getByRole('heading', { name: title })).toBeVisible()
  await page.getByRole('button', { name: 'Submit for approval' }).first().click()
  await expect(page.getByText('pending', { exact: true }).first()).toBeVisible()
  await page.screenshot({ path: 'e2e/screenshots/03-event-detail-pending.png', fullPage: true })
  const eventUrl = page.url()

  // Approvals: the pending event's card, approved without opening it
  await page.goto('/organizer/approvals')
  const approvalCard = page.locator('div.overflow-hidden', { hasText: title }).last()
  await expect(approvalCard).toBeVisible()
  await approvalCard.getByRole('button', { name: 'Approve', exact: true }).click()
  await expect(page.getByText('Event approved')).toBeVisible()
  await expect(page.locator('div.overflow-hidden', { hasText: title })).toHaveCount(0)

  // It moves to the Approved history, where the card opens to show who
  // decided and who created it (real name + email, not a placeholder)
  await page.getByRole('button', { name: /^Approved/ }).click()
  const approvedCard = page.locator('div.overflow-hidden', { hasText: title }).last()
  await approvedCard.getByRole('button', { name: new RegExp(title) }).click()
  await expect(approvedCard.getByText(/Approved by TechHub Admin/)).toBeVisible()
  await expect(approvedCard.getByText('Created by')).toBeVisible()
  await expect(approvedCard.getByText('admin@techhub.ph')).toBeVisible()
  await page.screenshot({ path: 'e2e/screenshots/04-approvals-history.png', fullPage: true })

  // Back on the event: now approved
  await page.goto(eventUrl)
  await expect(page.getByText('approved', { exact: true }).first()).toBeVisible()

  // Duplicate -> lands on a new draft copy
  await page.getByRole('button', { name: 'Duplicate' }).first().click()
  await expect(page).toHaveURL(/\/organizer\/events\/\d+\/edit$/)
  await page.goto('/my-events')
  await expect(page.locator('.p-4', { hasText: `${title} (Copy)` })).toBeVisible()
})
