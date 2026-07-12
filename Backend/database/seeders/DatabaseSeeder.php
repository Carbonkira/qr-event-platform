<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Ports the exact sample data from Front-end/src/App.jsx's createStore()
 * (App.jsx:20-77) so the app looks identical to the mock on first run.
 * Mock string ids (evt-001, reg-004, ...) are not preserved (Postgres
 * auto-increment ids are used instead) - relationships below are wired up
 * by insertion order, which matches the mock's array order exactly.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Seeded organizer login ────────────────────────────────────
        $admin = User::create([
            'name' => 'TechHub Admin',
            'email' => 'admin@techhub.ph',
            'password' => Hash::make('password'),
            'institution' => null,
            'email_verified_at' => now(),
        ]);
        // 'role' isn't mass-assignable (see User::$fillable) so registration
        // can never self-escalate to admin - the seeded demo account is set
        // directly instead.
        $admin->forceFill(['role' => 'admin'])->save();

        // ─── Organization (first insert into an empty table gets id=1 -
        // BackfillOrganizations reassigns this specific row to the demo
        // account rather than creating a duplicate) ──────────────────────
        Organization::create([
            'name' => 'TechHub Manila',
            'description' => 'A community of builders, designers, and founders hosting events across Metro Manila.',
            'organized_by' => 'Rimuel Salibio',
            'email' => 'hello@techhub.ph',
            'industry' => 'Technology',
            'instagram' => 'techhubmnl',
            'linkedin' => 'techhub-manila',
            'facebook' => 'techhubmnl',
            'website' => 'techhub.ph',
            'twitter' => 'techhubmnl',
            'privacy_policy_url' => 'https://techhub.ph/privacy',
        ]);

        // ─── Events (App.jsx:26-60) ─────────────────────────────────────
        $event1 = $this->makeEvent([
            'title' => 'AI in Education Summit 2025',
            'type' => 'Conference',
            'is_private' => false,
            'slug' => 'ai-education-summit-2025',
            'private_link' => null,
            'description' => 'Exploring how AI and machine learning are reshaping academic institutions. Join keynote speakers, live demos, and hands-on workshops with leaders in EdTech.',
            'venue' => 'Main Auditorium, BGC',
            'location' => 'Taguig City, Metro Manila',
            'date' => '2025-08-15',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'organized_by' => 'Dr. Maria Santos',
            'industry' => 'Education',
            'capacity' => 150,
            'status' => 'approved',
            'feedback_enabled' => true,
            'image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&q=80',
            'requires_certificate' => true,
            'pricing' => 'free',
            'price' => 0,
            'allow_walk_ins' => true,
            'socials' => ['instagram' => 'aisummit', 'linkedin' => 'ai-education-summit', 'website' => 'aisummit.ph'],
            'privacy_policy_url' => 'https://techhub.ph/privacy',
            'custom_fields' => [['id' => 'cf1', 'label' => 'Dietary restrictions', 'type' => 'text', 'required' => false]],
            'tags' => ['AI', 'Education'],
            'user_id' => $admin->id,
        ], '2025-06-01T08:00:00', [
            ['label' => 'Send invitations', 'done' => true],
            ['label' => 'Confirm AV equipment', 'done' => true],
            ['label' => 'Prepare name tags', 'done' => false],
            ['label' => 'Print certificates', 'done' => false],
        ]);

        $event2 = $this->makeEvent([
            'title' => 'Founder Networking Night',
            'type' => 'Networking',
            'is_private' => false,
            'slug' => 'founder-networking-night',
            'private_link' => null,
            'description' => 'An intimate evening for startup founders to connect, share war stories, and find collaborators. Drinks and light bites included.',
            'venue' => 'The Rooftop, Makati',
            'location' => 'Makati City, Metro Manila',
            'date' => '2025-09-20',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'organized_by' => 'TechHub Manila',
            'industry' => 'Technology',
            'capacity' => 60,
            'status' => 'approved',
            'feedback_enabled' => true,
            'image' => 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=1200&q=80',
            'requires_certificate' => false,
            'pricing' => 'paid',
            'price' => 500,
            'allow_walk_ins' => false,
            'socials' => ['instagram' => 'techhubmnl', 'linkedin' => 'techhub-manila'],
            'privacy_policy_url' => 'https://techhub.ph/privacy',
            'custom_fields' => [
                ['id' => 'cf1', 'label' => 'Company / Startup name', 'type' => 'text', 'required' => true],
                ['id' => 'cf2', 'label' => 'What are you building?', 'type' => 'text', 'required' => false],
            ],
            'tags' => ['Startup', 'Networking'],
            'user_id' => $admin->id,
        ], '2025-07-01T10:00:00', [
            ['label' => 'Book venue', 'done' => true],
            ['label' => 'Arrange catering', 'done' => false],
            ['label' => 'Confirm RSVP list', 'done' => false],
        ]);

        $event3 = $this->makeEvent([
            'title' => 'Cybersecurity Awareness Workshop',
            'type' => 'Workshop',
            'is_private' => true,
            'slug' => 'cybersecurity-workshop-2025',
            'private_link' => 'sec-access-7f3d',
            'description' => 'Internal cybersecurity training for staff. Live threat demonstrations and best practices for keeping data safe.',
            'venue' => 'ICT Training Center',
            'location' => 'Pasig City, Metro Manila',
            'date' => '2025-07-10',
            'start_time' => '13:00',
            'end_time' => '17:00',
            'organized_by' => 'IT Department',
            'industry' => 'Technology',
            'capacity' => 100,
            'status' => 'completed',
            'feedback_enabled' => true,
            'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1200&q=80',
            'requires_certificate' => true,
            'pricing' => 'walk-in',
            'price' => 0,
            'allow_walk_ins' => true,
            'socials' => [],
            'privacy_policy_url' => 'https://techhub.ph/privacy',
            'custom_fields' => [],
            'tags' => ['Security'],
            'user_id' => $admin->id,
        ], '2025-06-15T14:00:00', [
            ['label' => 'Prepare slides', 'done' => true],
            ['label' => 'Test demo environment', 'done' => true],
            ['label' => 'Issue certificates', 'done' => true],
        ]);

        $event4 = $this->makeEvent([
            'title' => 'Design Systems Meetup',
            'type' => 'Meetup',
            'is_private' => false,
            'slug' => 'design-systems-meetup',
            'private_link' => null,
            'description' => 'Monthly gathering for product designers. This month: scaling design tokens across teams.',
            'venue' => 'Co-Lab Space, Ortigas',
            'location' => 'Pasig City, Metro Manila',
            'date' => '2025-10-05',
            'start_time' => '14:00',
            'end_time' => '17:00',
            'organized_by' => 'Design Guild PH',
            'industry' => 'Design',
            'capacity' => 40,
            'status' => 'pending',
            'feedback_enabled' => true,
            'image' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=1200&q=80',
            'requires_certificate' => false,
            'pricing' => 'free',
            'price' => 0,
            'allow_walk_ins' => true,
            'socials' => ['instagram' => 'designguildph'],
            'privacy_policy_url' => '',
            'custom_fields' => [],
            'tags' => ['Design'],
            'user_id' => $admin->id,
        ], '2025-07-20T09:00:00', []);

        // Demo participant account - same account model as the organizer
        // above (User::events()/registrations()), just showing the other
        // side: someone who registered for an event and could, just as
        // easily, go create one of their own.
        $participant = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@edu.ph',
            'password' => Hash::make('password'),
            'institution' => 'Ateneo de Manila University',
            'email_verified_at' => now(),
        ]);

        // ─── Registrations (App.jsx:62-68) ─────────────────────────────
        $reg1 = $this->makeRegistration($event1, [
            'user_id' => $participant->id,
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@edu.ph',
            'custom_data' => ['cf1' => 'Vegetarian'],
            'qr_code' => 'QR-E001-P001',
            'attended' => false,
            'check_in_time' => null,
            'feedback_submitted' => false,
            'is_walk_in' => false,
            'needs_certificate' => true,
        ], '2025-07-01T08:30:00');

        $this->makeRegistration($event1, [
            'name' => 'Maria Garcia',
            'email' => 'maria@edu.ph',
            'custom_data' => [],
            'qr_code' => 'QR-E001-P002',
            'attended' => false,
            'check_in_time' => null,
            'feedback_submitted' => false,
            'is_walk_in' => false,
            'needs_certificate' => false,
        ], '2025-07-02T10:15:00');

        $this->makeRegistration($event2, [
            'name' => 'Angela Torres',
            'email' => 'angela@startup.ph',
            'custom_data' => ['cf1' => 'Acme AI', 'cf2' => 'Developer tools'],
            'qr_code' => 'QR-E002-P001',
            'attended' => false,
            'check_in_time' => null,
            'feedback_submitted' => false,
            'is_walk_in' => false,
            'needs_certificate' => false,
        ], '2025-08-05T09:00:00');

        $reg4 = $this->makeRegistration($event3, [
            'name' => 'Roberto Santos',
            'email' => 'roberto@edu.ph',
            'custom_data' => [],
            'qr_code' => 'QR-E003-P001',
            'attended' => true,
            'check_in_time' => '2025-07-10T13:05:00',
            'feedback_submitted' => true,
            'is_walk_in' => false,
            'needs_certificate' => true,
        ], '2025-06-20T11:30:00');

        $reg5 = $this->makeRegistration($event3, [
            'name' => 'Mark Lim',
            'email' => 'mark@edu.ph',
            'custom_data' => [],
            'qr_code' => 'QR-E003-P002',
            'attended' => true,
            'check_in_time' => '2025-07-10T13:12:00',
            'feedback_submitted' => true,
            'is_walk_in' => false,
            'needs_certificate' => true,
        ], '2025-06-22T13:00:00');

        $this->makeRegistration($event3, [
            'name' => 'Walk-in Guest',
            'email' => 'walkin-001@tmp',
            'custom_data' => [],
            'qr_code' => 'QR-E003-WI001',
            'attended' => true,
            'check_in_time' => '2025-07-10T13:00:00',
            'feedback_submitted' => false,
            'is_walk_in' => true,
            'needs_certificate' => false,
        ], '2025-07-10T13:00:00');

        // ─── Feedback (App.jsx:70-72) ───────────────────────────────────
        // Seed values are literal (as in the mock's hardcoded FEEDBACK
        // array), not run through the badge-computing logic that only
        // applies to new submissions via FeedbackController.
        $this->makeFeedback($reg4, $event3, [
            'q1' => 5, 'q2' => 5, 'q3' => 4, 'q4' => 5, 'q5' => 5,
            'comment' => 'Excellent workshop! The hands-on demos were eye-opening. Would love a follow-up session.',
            'is_highlighted' => true,
            'badge' => '⭐ Top Reviewer',
        ], '2025-07-10T16:45:00');

        $this->makeFeedback($reg5, $event3, [
            'q1' => 4, 'q2' => 5, 'q3' => 5, 'q4' => 4, 'q5' => 4,
            'comment' => 'Very informative. The QR check-in was super smooth.',
            'is_highlighted' => false,
            'badge' => null,
        ], '2025-07-10T16:30:00');

        // ─── Task templates (App.jsx:73-77) ─────────────────────────────
        TaskTemplate::create([
            'name' => 'Conference Standard',
            'tasks' => ['Send invitations', 'Confirm AV equipment', 'Prepare name tags', 'Print certificates', 'Set up registration booth'],
        ]);
        TaskTemplate::create([
            'name' => 'Workshop Checklist',
            'tasks' => ['Book venue', 'Prepare materials', 'Send calendar invites', 'Arrange refreshments'],
        ]);
        TaskTemplate::create([
            'name' => 'Meetup Basic',
            'tasks' => ['Confirm venue', 'Post on socials', 'Prepare sign-in sheet'],
        ]);
    }

    private function makeEvent(array $attributes, string $createdAt, array $tasks): Event
    {
        $event = Event::create($attributes);
        $event->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        foreach ($tasks as $task) {
            $event->tasks()->create($task);
        }

        return $event;
    }

    private function makeRegistration(Event $event, array $attributes, string $registeredAt): Registration
    {
        $registration = $event->registrations()->create($attributes);
        $registration->forceFill(['created_at' => $registeredAt, 'updated_at' => $registeredAt])->save();

        return $registration;
    }

    private function makeFeedback(Registration $registration, Event $event, array $attributes, string $submittedAt): Feedback
    {
        $feedback = Feedback::create(array_merge($attributes, [
            'registration_id' => $registration->id,
            'event_id' => $event->id,
        ]));
        $feedback->forceFill(['created_at' => $submittedAt, 'updated_at' => $submittedAt])->save();

        return $feedback;
    }
}
