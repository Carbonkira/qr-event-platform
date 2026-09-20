// Boots the API for the e2e suite: a fresh, seeded scratch database, then
// PHP's built-in server on a dedicated port. Playwright starts this as its
// backend webServer and tears the process tree down afterwards.
import { spawn, spawnSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { BACKEND_DIR, E2E_DB, API_PORT, backendEnv } from './env.mjs'

fs.rmSync(E2E_DB, { force: true })
fs.writeFileSync(E2E_DB, '')

const migrate = spawnSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
  cwd: BACKEND_DIR,
  env: backendEnv,
  stdio: 'inherit',
})
if (migrate.status !== 0) process.exit(migrate.status ?? 1)

// Same invocation `php artisan serve` uses, minus its habit of dropping every
// environment variable it doesn't recognise.
const publicDir = path.join(BACKEND_DIR, 'public')
const router = path.join(BACKEND_DIR, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')
const server = spawn('php', ['-S', `127.0.0.1:${API_PORT}`, router], {
  cwd: publicDir,
  env: backendEnv,
  stdio: ['ignore', 'inherit', 'pipe'],
})

// PHP's server logs every connection (and, with LOG_CHANNEL=stderr, Laravel
// logs here too). Keep the failures and the app's own errors, drop the
// per-request chatter so a failing run stays readable.
const CHATTER = /(Accepted|Closing|\[(2|3)\d\d\]: .*)$/
let pending = ''
server.stderr.on('data', (chunk) => {
  const lines = (pending + chunk.toString()).split(/\r?\n/)
  pending = lines.pop() ?? ''
  for (const line of lines) {
    if (line.trim() && !CHATTER.test(line)) process.stderr.write(line + '\n')
  }
})

server.on('exit', (code) => process.exit(code ?? 0))
for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    server.kill()
    process.exit(0)
  })
}
