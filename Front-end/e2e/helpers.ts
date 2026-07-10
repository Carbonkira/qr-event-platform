import type { Page } from '@playwright/test'

export const ORGANIZER_EMAIL = 'admin@techhub.ph'
export const ORGANIZER_PASSWORD = 'password'

export async function loginAsOrganizer(page: Page) {
  await page.goto('/login')
  await page.getByPlaceholder('you@organization.com').fill(ORGANIZER_EMAIL)
  await page.getByPlaceholder('••••••••').fill(ORGANIZER_PASSWORD)
  await page.getByRole('button', { name: 'Log In' }).click()
  await page.waitForURL('**/organizer')
}

export function futureDateInput(daysFromNow: number): string {
  const d = new Date()
  d.setDate(d.getDate() + daysFromNow)
  return d.toISOString().slice(0, 10) // yyyy-mm-dd, matches <input type="date">
}
