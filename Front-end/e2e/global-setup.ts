import { execFile } from 'node:child_process'
import { promisify } from 'node:util'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

const execFileAsync = promisify(execFile)

// Seeded events (Backend/database/seeders/DatabaseSeeder.php) carry fixed
// 2025 dates, so they eventually fall into the past and stop being
// registerable. This creates (or refreshes) a dedicated, always-future,
// approved event so registration tests don't rot with the calendar.
const TINKER_SCRIPT = `
$u = App\\Models\\User::first();
App\\Models\\Event::updateOrCreate(
  ['slug' => 'e2e-fixture-event'],
  [
    'title' => 'E2E Fixture Event',
    'type' => 'Meetup',
    'is_private' => false,
    'description' => 'Fixture event for Playwright e2e tests.',
    'venue' => 'Test Venue',
    'location' => 'Test City',
    'date' => now()->addDays(30)->toDateString(),
    'start_time' => '10:00',
    'end_time' => '12:00',
    'organized_by' => 'E2E Tester',
    'industry' => 'Technology',
    'capacity' => 100,
    'status' => 'approved',
    'feedback_enabled' => true,
    'requires_certificate' => false,
    'pricing' => 'free',
    'price' => 0,
    'allow_walk_ins' => true,
    'socials' => [],
    'custom_fields' => [],
    'feedback_questions' => [
      ['id' => 'q1', 'label' => 'Check-in experience', 'type' => 'rating', 'required' => true],
      ['id' => 'q2', 'label' => 'Event organization', 'type' => 'rating', 'required' => true],
      ['id' => 'q3', 'label' => 'Content quality', 'type' => 'rating', 'required' => true],
      ['id' => 'q4', 'label' => 'Venue & facilities', 'type' => 'rating', 'required' => true],
      ['id' => 'q5', 'label' => 'Overall satisfaction', 'type' => 'rating', 'required' => true],
      ['id' => 'fq1', 'label' => 'What is one thing we could improve?', 'type' => 'text', 'required' => true],
    ],
    'tags' => [],
    'user_id' => $u->id,
  ]
);

App\\Models\\Event::updateOrCreate(
  ['slug' => 'e2e-waitlist-event'],
  [
    'title' => 'E2E Waitlist Event',
    'type' => 'Meetup',
    'is_private' => false,
    'description' => 'Capacity-1 fixture for Playwright waitlist tests.',
    'venue' => 'Test Venue',
    'location' => 'Test City',
    'date' => now()->addDays(30)->toDateString(),
    'start_time' => '10:00',
    'end_time' => '12:00',
    'organized_by' => 'E2E Tester',
    'industry' => 'Technology',
    'capacity' => 1,
    'status' => 'approved',
    'feedback_enabled' => true,
    'requires_certificate' => false,
    'pricing' => 'free',
    'price' => 0,
    'allow_walk_ins' => true,
    'socials' => [],
    'custom_fields' => [],
    'tags' => [],
    'user_id' => $u->id,
  ]
);

// Capacity-1 waitlist assertions only hold with an exact registration
// count, so this fixture gets wiped clean on every run rather than
// accumulating registrations like the general-purpose fixture above.
\$waitlistEvent = App\\Models\\Event::where('slug', 'e2e-waitlist-event')->first();
if (\$waitlistEvent) {
  \$waitlistEvent->registrations()->delete();
}
`

export default async function globalSetup() {
  const backendDir = path.resolve(__dirname, '../../Backend')
  await execFileAsync('php', ['artisan', 'tinker', '--execute', TINKER_SCRIPT], { cwd: backendDir })
}
