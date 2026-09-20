import { test, expect } from '@playwright/test'
import { STRONG_PASSWORD, createOrganizer, loginAs, verifyEmail } from './helpers'

test('an organizer applicant sees where their application stands, and cannot reach the tools or the Approvals page', async ({ page }) => {
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

  // ...and even then a new account is an organizer awaiting approval: My
  // Events is their application status, not a dashboard they can't use.
  await expect(page.getByRole('heading', { name: 'Your organizer application is under review' })).toBeVisible()
  await expect(page.getByText('Pending review')).toBeVisible()
  await expect(page.getByText('Application submitted')).toBeVisible()
  await expect(page.getByText('Admin review')).toBeVisible()
  await expect(page.getByText(email).first()).toBeVisible()
  // Who to call is the admin's published number (ADMIN_CONTACT_NUMBER in the e2e env)
  await expect(page.getByRole('link', { name: '0917 000 0000' })).toBeVisible()
  await page.evaluate(() => window.scrollTo(0, 0)) // a full-page capture otherwise draws the sticky header mid-page
  await page.screenshot({ path: 'e2e/screenshots/50-application-status-pending.png', fullPage: true })

  // Nothing to host with yet, so the tools aren't offered...
  await expect(page.getByRole('button', { name: 'Create Event' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /Manage/ })).toHaveCount(0)

  // ...and every other page carries a reminder with a way back to the status.
  await page.goto('/organizations')
  await expect(page.getByText("Your organizer application is being reviewed")).toBeVisible()
  await page.getByRole('link', { name: 'View status' }).click()
  await expect(page).toHaveURL(/\/my-events$/)

  // Typing an organizer URL directly explains itself instead of failing; the
  // Approvals page is also admin-only, which never even comes into it.
  await page.goto('/organizer/approvals')
  await expect(page.getByText('Waiting for admin approval')).toBeVisible()
  await expect(page.getByText('Admins only')).toHaveCount(0)
})

test('an approved organizer who is not an admin still cannot see or reach the Approvals page', async ({ page }) => {
  const email = `e2e-plain-organizer-${Date.now()}@example.com`
  createOrganizer(email, 'E2E Plain Organizer', 'approved')

  await loginAs(page, 'organizer', email, STRONG_PASSWORD)

  // Approved, so the tools are theirs - but the Approvals nav item is admin-only,
  // and hitting the URL directly is blocked too (a UX nicety on top of the
  // API's own 403, not the real gate).
  await expect(page.getByRole('button', { name: 'Create Event' })).toBeVisible()
  await expect(page.getByText('Your organizer application')).toHaveCount(0)
  await page.getByRole('button', { name: /Manage/ }).first().click()
  await expect(page.getByRole('link', { name: 'Approvals' })).toHaveCount(0)

  await page.goto('/organizer/approvals')
  await expect(page.getByText('Admins only')).toBeVisible()
  await expect(page.getByText("You don't have permission to review approvals.")).toBeVisible()
})
