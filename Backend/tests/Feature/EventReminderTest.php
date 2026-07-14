<?php

namespace Tests\Feature;

use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $startTime): Event
    {
        return Event::create([
            'title' => 'Reminder Test Event',
            'status' => 'approved',
            'slug' => 'reminder-test-'.uniqid(),
            'date' => now()->toDateString(),
            'start_time' => $startTime,
            'end_time' => '23:59',
        ]);
    }

    public function test_a_registrant_is_emailed_once_when_the_event_starts_in_about_an_hour(): void
    {
        Mail::fake();
        $event = $this->makeEvent(now()->addHour()->format('H:i'));
        $registration = Registration::create(['event_id' => $event->id, 'name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertQueued(EventReminderMail::class, fn ($mail) => $mail->hasTo('a@example.com'));
        $this->assertNotNull($registration->fresh()->reminder_sent_at);
    }

    public function test_running_the_command_twice_does_not_send_a_second_reminder(): void
    {
        Mail::fake();
        $event = $this->makeEvent(now()->addHour()->format('H:i'));
        Registration::create(['event_id' => $event->id, 'name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);

        $this->artisan('events:send-reminders');
        $this->artisan('events:send-reminders');

        Mail::assertQueued(EventReminderMail::class, 1);
    }

    public function test_an_event_far_outside_the_one_hour_window_is_not_reminded_yet(): void
    {
        Mail::fake();
        $event = $this->makeEvent(now()->addHours(5)->format('H:i'));
        Registration::create(['event_id' => $event->id, 'name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);

        $this->artisan('events:send-reminders');

        Mail::assertNothingQueued();
    }

    public function test_an_event_that_already_started_is_not_reminded(): void
    {
        Mail::fake();
        $event = $this->makeEvent(now()->subHour()->format('H:i'));
        Registration::create(['event_id' => $event->id, 'name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);

        $this->artisan('events:send-reminders');

        Mail::assertNothingQueued();
    }

    public function test_a_pending_events_registrants_are_not_reminded(): void
    {
        Mail::fake();
        $event = $this->makeEvent(now()->addHour()->format('H:i'));
        $event->update(['status' => 'pending']);
        Registration::create(['event_id' => $event->id, 'name' => 'A', 'email' => 'a@example.com', 'qr_code' => 'QR-A']);

        $this->artisan('events:send-reminders');

        Mail::assertNothingQueued();
    }
}
