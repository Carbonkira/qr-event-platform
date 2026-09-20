import { tinker } from './helpers'

// The seeded demo events (Backend/database/seeders/DatabaseSeeder.php) carry
// fixed 2025 dates, so they sit in the past and can't be registered for. This
// creates dedicated, always-future, approved events so the registration specs
// don't rot with the calendar. The scratch database is rebuilt from zero on
// every run (see serve-backend.mjs), so plain creates are enough.
const TINKER_SCRIPT = `
$admin = App\\Models\\Organizer::where('email', 'admin@techhub.ph')->firstOrFail();

$base = [
  'type' => 'Meetup',
  'is_private' => false,
  'venue' => 'Test Venue',
  'location' => 'Test City',
  'date' => now()->addDays(30)->toDateString(),
  'start_time' => '10:00',
  'end_time' => '12:00',
  'organized_by' => 'E2E Tester',
  'industry' => 'Technology',
  'status' => 'approved',
  'feedback_enabled' => true,
  'pricing' => 'free',
  'price' => 0,
  'allow_walk_ins' => true,
  'socials' => [],
  'custom_fields' => [],
  'tags' => [],
  'organizer_id' => $admin->id,
];

$fixture = App\\Models\\Event::create($base + [
  'slug' => 'e2e-fixture-event',
  'title' => 'E2E Fixture Event',
  'description' => 'Fixture event for Playwright e2e tests.',
  'capacity' => 100,
  'feedback_questions' => [
    ['id' => 'q1', 'label' => 'Check-in experience', 'type' => 'rating', 'required' => true],
    ['id' => 'q2', 'label' => 'Event organization', 'type' => 'rating', 'required' => true],
    ['id' => 'q3', 'label' => 'Content quality', 'type' => 'rating', 'required' => true],
    ['id' => 'q4', 'label' => 'Venue & facilities', 'type' => 'rating', 'required' => true],
    ['id' => 'q5', 'label' => 'Overall satisfaction', 'type' => 'rating', 'required' => true],
    ['id' => 'fq1', 'label' => 'What is one thing we could improve?', 'type' => 'text', 'required' => true],
  ],
]);

// Own event for the check-in -> completed -> feedback journey: completing it
// closes registration, so it can't share the general-purpose fixture above.
App\\Models\\Event::create($base + [
  'slug' => 'e2e-feedback-event',
  'title' => 'E2E Feedback Event',
  'description' => 'Fixture event for the check-in and feedback e2e journey.',
  'capacity' => 100,
  'feedback_questions' => $fixture->feedback_questions,
]);

App\\Models\\Event::create($base + [
  'slug' => 'e2e-waitlist-event',
  'title' => 'E2E Waitlist Event',
  'description' => 'Capacity-1 fixture for Playwright waitlist tests.',
  'capacity' => 1,
]);
`

export default async function globalSetup() {
  tinker(TINKER_SCRIPT)
}
