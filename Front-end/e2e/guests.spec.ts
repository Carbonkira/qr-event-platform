import { test, expect } from '@playwright/test'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { loginAsOrganizer } from './helpers'

// Runs against the "E2E Fixture Event" created by global-setup.ts.
async function gotoFixtureEventGuests(page: import('@playwright/test').Page) {
  await page.goto('/organizer/events')
  await page.locator('tr', { hasText: 'E2E Fixture Event' }).getByRole('button', { name: 'Manage' }).click()
  await page.getByRole('button', { name: /^guests/i }).click()
}

test('organizer can add, edit, CSV-import, and remove guests', async ({ page }) => {
  page.on('dialog', d => d.accept())

  const suffix = Date.now()
  const guestEmail = `guest-${suffix}@example.com`
  const guestName = `Playwright Guest ${suffix}`

  await loginAsOrganizer(page)
  await gotoFixtureEventGuests(page)

  // Add guest
  await page.getByRole('button', { name: 'Add guest' }).click()
  await page.getByRole('heading', { name: 'Add Guest' }).waitFor()
  await page.locator('label:text-is("Full Name") + div input').fill(guestName)
  await page.locator('label:text-is("Email") + div input').fill(guestEmail)
  await page.getByRole('button', { name: 'Add Guest', exact: true }).click()
  await expect(page.getByText(guestName)).toBeVisible()

  // Edit guest — rename
  const renamedName = `${guestName} (edited)`
  const guestRow = page.locator('div.rounded-xl', { hasText: guestName }).first()
  await guestRow.locator('button').nth(0).click() // pencil icon
  await page.getByRole('heading', { name: 'Edit Guest' }).waitFor()
  await page.locator('label:text-is("Full Name") + div input').fill(renamedName)
  await page.getByRole('button', { name: 'Save Changes' }).click()
  await expect(page.getByText(renamedName)).toBeVisible()

  await page.screenshot({ path: 'e2e/screenshots/10-guests-added-edited.png', fullPage: true })

  // CSV import: two fresh guests
  const csvPath = path.join(os.tmpdir(), `e2e-guests-${suffix}.csv`)
  const csvEmails = [`csv1-${suffix}@example.com`, `csv2-${suffix}@example.com`]
  const csvNames = [`CSV Guest One ${suffix}`, `CSV Guest Two ${suffix}`]
  fs.writeFileSync(csvPath, `name,email\n${csvNames[0]},${csvEmails[0]}\n${csvNames[1]},${csvEmails[1]}\n`)
  await page.locator('input[type="file"][accept*="csv"]').setInputFiles(csvPath)
  await expect(page.getByText(/Imported 2 guest/)).toBeVisible()
  await expect(page.getByText(csvNames[0])).toBeVisible()
  await expect(page.getByText(csvNames[1])).toBeVisible()
  fs.unlinkSync(csvPath)

  await page.screenshot({ path: 'e2e/screenshots/11-guests-csv-imported.png', fullPage: true })

  // Remove the manually-added guest
  const editedRow = page.locator('div.rounded-xl', { hasText: renamedName }).first()
  await editedRow.locator('button').last().click() // trash icon
  await expect(page.getByText(renamedName)).not.toBeVisible()
})
