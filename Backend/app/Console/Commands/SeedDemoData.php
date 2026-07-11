<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * One-shot demo content for a fresh deployment: a usable admin account plus
 * a handful of realistic-looking events spanning the past month (completed,
 * with registrations/attendance/feedback) through the next few weeks
 * (approved and open, or still pending approval). Safe to re-run - events
 * are looked up by slug, so it updates in place rather than duplicating.
 *
 * Registrations here are created directly via Eloquent, not through
 * RegistrationController - that's deliberate: the controller's own
 * createRegistration() queues a real confirmation email per registration,
 * which would either bounce (fake addresses) or spam a real one if this
 * is ever re-run against real data.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--email=demo@qrmeets.net} {--password=Demo12345!}';

    protected $description = 'Create a demo admin account and pseudo/mock events for a fresh deployment';

    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Demo Organizer',
                'password' => Hash::make($password),
            ]
        );
        // Neither is mass-assignable (see User::$fillable) - both are
        // deliberately excluded from ordinary create()/update() calls, so
        // they need a separate forceFill, same as the seeder does.
        $user->forceFill(['role' => 'admin', 'email_verified_at' => now()])->save();

        Organization::query()->first()?->update([
            'name' => 'QRMeets Community',
            'description' => 'Bringing together builders, designers, and curious minds for hands-on sessions, talks, and meetups.',
            'organized_by' => 'Demo Organizer',
            'email' => 'hello@qrmeets.net',
            'industry' => 'Technology',
        ]);

        $completed = [
            [
                'title' => 'Intro to Modern Web Development',
                'type' => 'Workshop', 'industry' => 'Technology', 'tags' => ['Web', 'Beginner-friendly'],
                'venue' => 'Innovation Hub', 'location' => 'Downtown Campus',
                'daysAgo' => 24, 'capacity' => 40, 'attendeeCount' => 31, 'attendedCount' => 27,
                'description' => 'A hands-on walkthrough of building and shipping a real web app from scratch - HTML/CSS/JS fundamentals through deploying your first project.',
                'image' => 'https://loremflickr.com/800/450/coding,laptop,programming?lock=101',
            ],
            [
                'title' => 'Founders Coffee Chat',
                'type' => 'Networking', 'industry' => 'Technology', 'tags' => ['Startups', 'Networking'],
                'venue' => 'The Roastery', 'location' => 'Arts District',
                'daysAgo' => 15, 'capacity' => 20, 'attendeeCount' => 18, 'attendedCount' => 16,
                'description' => 'An informal morning meetup for early-stage founders to swap notes, find collaborators, and commiserate over coffee.',
                'image' => 'https://loremflickr.com/800/450/coffee,meeting,networking?lock=102',
            ],
            [
                'title' => 'AI Tools for Everyday Productivity',
                'type' => 'Seminar', 'industry' => 'Technology', 'tags' => ['AI', 'Productivity'],
                'venue' => 'Main Auditorium', 'location' => 'Downtown Campus',
                'daysAgo' => 6, 'capacity' => 60, 'attendeeCount' => 52, 'attendedCount' => 45,
                'description' => 'A practical look at the AI tools actually worth adding to your workflow this year, with live demos and Q&A.',
                'image' => 'https://loremflickr.com/800/450/artificial-intelligence,technology,office?lock=103',
            ],
        ];

        $upcoming = [
            [
                'title' => 'Weekend Hackathon Kickoff',
                'type' => 'Conference', 'industry' => 'Technology', 'tags' => ['Hackathon'],
                'venue' => 'Innovation Hub', 'location' => 'Downtown Campus',
                'daysFromNow' => 3, 'capacity' => 100, 'status' => 'approved',
                'description' => 'Kickoff night for our 48-hour weekend hackathon - team formation, theme reveal, and a keynote to get everyone fired up.',
                'image' => 'https://loremflickr.com/800/450/hackathon,coding,team?lock=104',
            ],
            [
                'title' => 'Design Systems Deep Dive',
                'type' => 'Workshop', 'industry' => 'Design', 'tags' => ['Design', 'Design Systems'],
                'venue' => 'Studio B', 'location' => 'Arts District',
                'daysFromNow' => 10, 'capacity' => 35, 'status' => 'approved',
                'description' => 'Scaling design tokens and component libraries across teams, taught by people who\'ve actually done it at scale.',
                'image' => 'https://loremflickr.com/800/450/design,ui,workspace?lock=105',
            ],
            [
                'title' => 'Startup Pitch Night',
                'type' => 'Networking', 'industry' => 'Technology', 'tags' => ['Startups', 'Pitching'],
                'venue' => 'Main Auditorium', 'location' => 'Downtown Campus',
                'daysFromNow' => 18, 'capacity' => 80, 'status' => 'pending',
                'description' => 'Five local founders pitch to a panel of investors and the room - open floor for questions after each round.',
                'image' => 'https://loremflickr.com/800/450/startup,pitch,presentation?lock=106',
            ],
        ];

        $names = [
            'Ana Cruz', 'Ben Torres', 'Carla Reyes', 'Diego Santos', 'Ella Ramos', 'Felix Garcia',
            'Grace Lim', 'Hugo Mendoza', 'Isla Rivera', 'Jaya Bautista', 'Kai Fernandez', 'Lena Ortiz',
            'Miko Aquino', 'Nadia Flores', 'Omar Dela Cruz', 'Priya Santos', 'Quinn Alvarez', 'Rosa Diaz',
            'Sam Villanueva', 'Talia Herrera', 'Uri Gonzales', 'Vera Castro', 'Wren Morales', 'Xander Ilagan',
            'Yara Pascual', 'Zane Domingo', 'Abby Navarro', 'Cruz Manalo', 'Dara Espino', 'Eli Cortez',
        ];

        $comments = [
            'Really well organized, learned a lot!',
            'Great speakers, would attend again.',
            'Loved the hands-on portions the most.',
            'Venue was great, food could be better.',
            'Exceeded my expectations, thank you!',
            'Solid event, a bit long but worth it.',
            'The Q&A at the end was the highlight for me.',
            null, null, null, // some attendees leave no comment - realistic, not everyone does
        ];

        foreach ($completed as $i => $spec) {
            $event = Event::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($spec['title'])],
                [
                    'title' => $spec['title'], 'type' => $spec['type'], 'is_private' => false,
                    'description' => $spec['description'], 'venue' => $spec['venue'], 'location' => $spec['location'],
                    'date' => now()->subDays($spec['daysAgo'])->toDateString(),
                    'start_time' => '10:00', 'end_time' => '14:00',
                    'organized_by' => 'Demo Organizer', 'industry' => $spec['industry'],
                    'capacity' => $spec['capacity'], 'status' => 'completed', 'feedback_enabled' => true,
                    'requires_certificate' => false, 'pricing' => 'free', 'price' => 0, 'allow_walk_ins' => true,
                    'socials' => [], 'custom_fields' => [], 'tags' => $spec['tags'], 'user_id' => $user->id,
                    'image' => $spec['image'],
                ]
            );
            $event->registrations()->delete();

            $eventNum = str_pad((string) $event->id, 3, '0', STR_PAD_LEFT);
            for ($n = 1; $n <= $spec['attendeeCount']; $n++) {
                $attended = $n <= $spec['attendedCount'];
                $registration = $event->registrations()->create([
                    'name' => $names[($i * 10 + $n) % count($names)].($n > count($names) ? " {$n}" : ''),
                    'email' => 'attendee'.$n.'.demo'.$event->id.'@example.com',
                    'qr_code' => sprintf('QR-E%s-P%s', $eventNum, str_pad((string) $n, 3, '0', STR_PAD_LEFT)),
                    'attended' => $attended,
                    'check_in_time' => $attended ? now()->subDays($spec['daysAgo'])->setTime(10, random_int(0, 30)) : null,
                    'feedback_submitted' => false,
                ]);

                // Most (not all) attendees who checked in also leave feedback - realistic completion rate.
                if ($attended && random_int(1, 100) <= 70) {
                    $ratings = array_map(fn () => random_int(3, 5), range(1, 5));
                    Feedback::create([
                        'registration_id' => $registration->id,
                        'event_id' => $event->id,
                        'q1' => $ratings[0], 'q2' => $ratings[1], 'q3' => $ratings[2], 'q4' => $ratings[3], 'q5' => $ratings[4],
                        'comment' => $comments[array_rand($comments)],
                        'is_highlighted' => array_sum($ratings) / 5 >= 4.5,
                    ]);
                    $registration->update(['feedback_submitted' => true]);
                }
            }
        }

        foreach ($upcoming as $spec) {
            Event::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($spec['title'])],
                [
                    'title' => $spec['title'], 'type' => $spec['type'], 'is_private' => false,
                    'description' => $spec['description'], 'venue' => $spec['venue'], 'location' => $spec['location'],
                    'date' => now()->addDays($spec['daysFromNow'])->toDateString(),
                    'start_time' => '10:00', 'end_time' => '14:00',
                    'organized_by' => 'Demo Organizer', 'industry' => $spec['industry'],
                    'capacity' => $spec['capacity'], 'status' => $spec['status'], 'feedback_enabled' => true,
                    'requires_certificate' => false, 'pricing' => 'free', 'price' => 0, 'allow_walk_ins' => true,
                    'socials' => [], 'custom_fields' => [], 'tags' => $spec['tags'], 'user_id' => $user->id,
                    'image' => $spec['image'],
                ]
            );
        }

        $this->info("Demo account ready: {$email} / {$password} (role: admin)");
        $this->info('Seeded '.count($completed).' completed events with registrations/feedback and '.count($upcoming).' upcoming events.');

        return self::SUCCESS;
    }
}
