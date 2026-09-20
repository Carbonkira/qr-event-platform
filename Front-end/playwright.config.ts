import { defineConfig, devices } from '@playwright/test'
import { API_URL, APP_PORT, APP_URL } from './e2e/env.mjs'

export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  // PHP's built-in dev server is single-threaded and the scratch DB is SQLite
  // (single writer) — concurrent workers queue up behind each other and start
  // timing out, not a bug in the app itself.
  // Journeys with several browser contexts plus tinker calls (each boots PHP)
  // outlast the 30s default on a single-threaded dev server.
  timeout: 90_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'html',
  use: {
    baseURL: APP_URL,
    trace: 'on-first-retry',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  // Dedicated ports and never reused: a leftover server from an earlier run
  // (or the everyday dev server) would be pointed at a different database.
  webServer: [
    {
      command: 'node e2e/serve-backend.mjs',
      url: `${API_URL}/events`,
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: `npm run dev -- --port ${APP_PORT} --strictPort`,
      url: APP_URL,
      reuseExistingServer: false,
      timeout: 60_000,
      env: { VITE_API_URL: API_URL },
    },
  ],
})
