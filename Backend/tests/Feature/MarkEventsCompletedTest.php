<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkEventsCompletedTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_event_whose_end_time_has_passed_is_marked_completed(): void
    {
        $event = Event::create([
            'title' => 'Yesterday Event', 'status' => 'approved', 'slug' => 'yesterday-event',
            'date' => now()->subDay()->toDateString(), 'end_time' => '14:00',
        ]);

        $this->artisan('events:mark-completed')->assertExitCode(0);

        $this->assertSame('completed', $event->fresh()->status);
    }

    public function test_an_approved_event_still_in_progress_today_is_left_alone(): void
    {
        $event = Event::create([
            'title' => 'Still Running', 'status' => 'approved', 'slug' => 'still-running',
            'date' => now()->toDateString(), 'end_time' => now()->addHour()->format('H:i'),
        ]);

        $this->artisan('events:mark-completed');

        $this->assertSame('approved', $event->fresh()->status);
    }

    public function test_a_pending_event_is_never_auto_completed(): void
    {
        $event = Event::create([
            'title' => 'Never Approved', 'status' => 'pending', 'slug' => 'never-approved',
            'date' => now()->subWeek()->toDateString(), 'end_time' => '14:00',
        ]);

        $this->artisan('events:mark-completed');

        $this->assertSame('pending', $event->fresh()->status);
    }
}
