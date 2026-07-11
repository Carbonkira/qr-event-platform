<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkEventsCompleted extends Command
{
    protected $signature = 'events:mark-completed';

    protected $description = 'Flip approved events whose end time has passed to completed';

    public function handle(): int
    {
        $count = 0;

        foreach (Event::where('status', 'approved')->get() as $event) {
            $endsAt = Carbon::parse($event->date->toDateString().' '.($event->end_time ?? '23:59'));
            if ($endsAt->isPast()) {
                $event->update(['status' => 'completed']);
                $count++;
            }
        }

        $this->info("Marked {$count} event(s) completed.");

        return self::SUCCESS;
    }
}
