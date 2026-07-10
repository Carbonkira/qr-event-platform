import { test, expect } from '@playwright/test'

test('a non-admin organizer cannot see or reach the Approvals page', async ({ page }) => {
  const suffix = Date.now()

  await page.goto('/organizer/register')
  await page.getByPlaceholder('e.g. Acme Student Council').fill(`E2E Org ${suffix}`)
  await page.getByPlaceholder('Full name').fill(`E2E Organizer ${suffix}`)
  await page.getByPlaceholder('you@organization.com').fill(`e2e-organizer-${suffix}@example.com`)
  await page.getByPlaceholder('••••••••').fill('password123')
  await page.getByRole('button', { name: 'Create Account' }).click()
  await expect(page).toHaveURL(/\/organizer\/verify-email/)

  // New accounts default to role "organizer", not "admin" - the nav item
  // shouldn't render, and hitting the URL directly should be blocked too
  // (this is a UX nicety on top of the API's own 403, not the real gate).
  await page.getByRole('button', { name: 'Go to Dashboard' }).click()
  await expect(page).toHaveURL(/\/organizer$/)
  await expect(page.getByRole('link', { name: 'Approvals' })).toHaveCount(0)

  await page.goto('/organizer/approvals')
  await expect(page.getByText('Admins only')).toBeVisible()
  await expect(page.getByText("You don't have permission to review event approvals.")).toBeVisible()
})
