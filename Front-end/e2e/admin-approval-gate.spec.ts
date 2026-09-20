import { test, expect } from '@playwright/test'
import { STRONG_PASSWORD, verifyEmail } from './helpers'

test('an organizer applicant cannot see or reach the Approvals page', async ({ page }) => {
  const suffix = Date.now()
  const email = `e2e-applicant-${suffix}@example.com`

  await page.goto('/organizer/register')
  await expect(page.getByRole('heading', { name: 'Apply as an Organizer' })).toBeVisible()
  await page.getByPlaceholder('Juan Dela Cruz').fill(`E2E Applicant ${suffix}`)
  await page.getByPlaceholder('you@organization.com').nth(0).fill(email)
  await page.getByPlaceholder('you@organization.com').nth(1).fill(email)
  await page.getByPlaceholder('0917 123 4567').fill('0917 123 4567')
  await page.getByPlaceholder('••••••••').nth(0).fill(STRONG_PASSWORD)
  await page.getByPlaceholder('••••••••').nth(1).fill(STRONG_PASSWORD)
  await page.getByRole('button', { name: 'Submit Application' }).click()

  // Nothing works until the email is verified...
  await expect(page).toHaveURL(/\/organizer\/verify-email/)
  verifyEmail('organizer', email)
  await page.getByRole('button', { name: "I've verified — Continue" }).click()
  await expect(page).toHaveURL(/\/my-events$/)

  // ...and even then a new account is a plain organizer awaiting approval,
  // not an admin: the nav item shouldn't render, and hitting the URL
  // directly should be blocked too (a UX nicety on top of the API's own 403,
  // not the real gate).
  await expect(page.getByRole('link', { name: 'Approvals' })).toHaveCount(0)
  await page.goto('/organizer/approvals')
  await expect(page.getByText('Admins only')).toBeVisible()
  await expect(page.getByText("You don't have permission to review approvals.")).toBeVisible()
})
