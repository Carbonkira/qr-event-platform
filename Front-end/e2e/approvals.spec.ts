import { test, expect, type Page } from '@playwright/test'
import { STRONG_PASSWORD, createOrganizer, createPendingEvent, loginAs, loginAsAdmin, verifyEmail } from './helpers'

// The card for one application on the Approvals page: the innermost
// overflow-hidden block containing the text (ancestors match too, and come
// first in the DOM).
const applicationCard = (page: Page, text: string) => page.locator('div.overflow-hidden', { hasText: text }).last()

test('an applicant asks for a new organization, and the admin approves them and creates it in one click', async ({ browser }) => {
  const suffix = Date.now()
  const name = `E2E Applicant ${suffix}`
  const email = `e2e-apply-${suffix}@example.com`
  const orgName = `E2E Guild ${suffix}`
  const orgAddress = `${suffix} Security Street, Test City`

  const applicantPage = await (await browser.newContext()).newPage()
  const adminPage = await (await browser.newContext()).newPage()

  // --- Applicant: full name, email, contact number, and a new organization with its address ---
  await applicantPage.goto('/organizer/register')
  await applicantPage.getByPlaceholder('Juan Dela Cruz').fill(name)
  await applicantPage.getByPlaceholder('you@organization.com').nth(0).fill(email)
  await applicantPage.getByPlaceholder('you@organization.com').nth(1).fill(email)
  await applicantPage.getByPlaceholder('0917 123 4567').fill('0917 222 3344')
  await applicantPage.locator('select').selectOption({ label: "My organization isn't listed" })
  await applicantPage.getByPlaceholder('e.g. Acme Student Council').fill(orgName)
  await applicantPage.getByPlaceholder('Street, city, province').fill(orgAddress)
  await applicantPage.getByPlaceholder('••••••••').nth(0).fill(STRONG_PASSWORD)
  await applicantPage.getByPlaceholder('••••••••').nth(1).fill(STRONG_PASSWORD)
  await applicantPage.getByRole('button', { name: 'Submit Application' }).click()
  await expect(applicantPage).toHaveURL(/\/organizer\/verify-email/)
  verifyEmail('organizer', email)
  await applicantPage.getByRole('button', { name: "I've verified — Continue" }).click()
  await expect(applicantPage).toHaveURL(/\/my-events$/)

  // Meanwhile the applicant's own view: under review, naming the organization they asked for
  await expect(applicantPage.getByRole('heading', { name: 'Your organizer application is under review' })).toBeVisible()
  await expect(applicantPage.getByText(orgName)).toBeVisible()
  await expect(applicantPage.getByRole('button', { name: 'Create Event' })).toHaveCount(0)

  // --- Admin: the application is waiting under Approvals > Organizers ---
  await loginAsAdmin(adminPage)
  await adminPage.goto('/organizer/approvals?tab=organizers')
  const card = applicationCard(adminPage, name)
  await expect(card).toBeVisible()

  // Clicking the card reveals everything the admin needs to decide
  await card.getByRole('button', { name: new RegExp(name) }).click()
  await expect(card.getByText('0917 222 3344').first()).toBeVisible()
  await expect(card.getByText('Verified')).toBeVisible()
  await expect(card.getByText(orgAddress)).toBeVisible()
  await expect(card.getByText(/Create ".*" when approving/)).toBeVisible()
  await adminPage.screenshot({ path: 'e2e/screenshots/40-approvals-organizer-expanded.png', fullPage: true })

  await card.getByRole('button', { name: 'Approve & create org' }).click()
  await expect(adminPage.getByText(`Organizer approved - "${orgName}" created`)).toBeVisible()

  // It leaves Pending and shows up under Approved, with who approved it
  await adminPage.getByRole('button', { name: /^Approved/ }).click()
  const approved = applicationCard(adminPage, name)
  await approved.getByRole('button', { name: new RegExp(name) }).click()
  await expect(approved.getByText(/Approved by TechHub Admin/)).toBeVisible()
  await adminPage.screenshot({ path: 'e2e/screenshots/41-approvals-organizer-history.png', fullPage: true })

  // --- Applicant: checks their status - approved, so hosting unlocks without logging in again ---
  await applicantPage.getByRole('button', { name: 'Check status' }).click()
  await expect(applicantPage.getByText('Your organizer application was approved')).toBeVisible()
  await expect(applicantPage.getByRole('heading', { name: 'Your organizer application is under review' })).toHaveCount(0)
  await expect(applicantPage.getByText("Everything you're hosting")).toBeVisible()

  // ...and they own the organization they asked for
  await applicantPage.getByRole('button', { name: 'Create Event' }).first().click()
  await expect(applicantPage.locator('select').first()).toContainText(orgName)
})

test('rejecting an organizer application records the reason the applicant was given', async ({ page, browser }) => {
  const suffix = Date.now()
  const name = `E2E Rejected Applicant ${suffix}`
  const email = `e2e-rejected-${suffix}@example.com`
  const reason = `We could not verify your organization (ref ${suffix}).`
  createOrganizer(email, name, 'pending')

  await loginAsAdmin(page)
  await page.goto('/organizer/approvals?tab=organizers')
  await applicationCard(page, name).getByRole('button', { name: 'Reject', exact: true }).click()

  // The dialog asks for an optional reason before anything is decided
  await expect(page.getByText('Reject this application?')).toBeVisible()
  await page.getByPlaceholder(/Tell them why/).fill(reason)
  await page.getByRole('button', { name: 'Reject', exact: true }).last().click() // the dialog's own button
  await expect(page.getByText('Organizer rejected')).toBeVisible()

  await page.getByRole('button', { name: /^Rejected/ }).click()
  const rejected = applicationCard(page, name)
  await rejected.getByRole('button', { name: new RegExp(name) }).click()
  await expect(rejected.getByText(/Rejected by TechHub Admin/)).toBeVisible()
  await expect(rejected.getByText('Reason given to the applicant')).toBeVisible()
  await expect(rejected.getByText(reason)).toBeVisible()
  await page.screenshot({ path: 'e2e/screenshots/42-approvals-rejected-reason.png', fullPage: true })

  // --- The applicant logs in and sees the same reason, plus who to contact ---
  const applicantPage = await (await browser.newContext()).newPage()
  await loginAs(applicantPage, 'organizer', email, STRONG_PASSWORD)
  await expect(applicantPage.getByRole('heading', { name: "Your organizer application wasn't approved" })).toBeVisible()
  await expect(applicantPage.getByText('Not approved').first()).toBeVisible()
  await expect(applicantPage.getByText('Reason from the admin')).toBeVisible()
  await expect(applicantPage.getByText(reason)).toBeVisible()
  await expect(applicantPage.getByRole('link', { name: '0917 000 0000' })).toBeVisible()
  await expect(applicantPage.getByRole('button', { name: 'Create Event' })).toHaveCount(0)
  await applicantPage.screenshot({ path: 'e2e/screenshots/43-application-status-rejected.png', fullPage: true })
})

test('a rejected event shows its real organizer and the reason, and stays out of the public listing', async ({ page }) => {
  const suffix = Date.now()
  const hostName = `E2E Host ${suffix}`
  const hostEmail = `e2e-host-${suffix}@example.com`
  const slug = `e2e-pending-${suffix}`
  const title = `E2E Pending Event ${suffix}`
  const reason = `Please add a venue contact (ref ${suffix}).`
  createOrganizer(hostEmail, hostName, 'approved')
  createPendingEvent(slug, title, hostEmail)

  await loginAsAdmin(page)
  await page.goto('/organizer/approvals')

  // Open the pending card: "Created by" names the actual organizer
  const card = applicationCard(page, title)
  await card.getByRole('button', { name: new RegExp(title) }).click()
  await expect(card.getByText('Created by')).toBeVisible()
  await expect(card.getByText(hostName).first()).toBeVisible()
  await expect(card.getByText(hostEmail)).toBeVisible()

  await card.getByRole('button', { name: 'Reject', exact: true }).click()
  await expect(page.getByText('Reject this event?')).toBeVisible()
  await page.getByPlaceholder(/Tell them why/).fill(reason)
  await page.getByRole('button', { name: 'Reject', exact: true }).last().click()
  await expect(page.getByText('Event rejected')).toBeVisible()

  await page.getByRole('button', { name: /^Rejected/ }).click()
  const rejected = applicationCard(page, title)
  await rejected.getByRole('button', { name: new RegExp(title) }).click()
  await expect(rejected.getByText('Reason given to the applicant')).toBeVisible()
  await expect(rejected.getByText(reason)).toBeVisible()

  // A rejected event never reaches the public home page
  await page.goto('/')
  await expect(page.getByText(title)).toHaveCount(0)
})
