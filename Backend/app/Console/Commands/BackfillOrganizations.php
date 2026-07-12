<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-time (but safely re-runnable) migration of existing data into the new
 * multi-organization model: every distinct user who owns at least one event
 * gets their own Organization (auto-created, they become its owner), and
 * their events get backfilled to point at it.
 *
 * One special case: the account matching --demo-email gets the *existing*
 * singleton organization (id=1, "QRMeets Community" - real branding already
 * filled in by SeedDemoData) reassigned to them, instead of a fresh
 * duplicate. Everyone else gets a brand-new org named after them.
 *
 * Idempotent: a user who already belongs to an organization is skipped, and
 * events that already have an organization_id are left untouched - safe to
 * run again after new events/users show up.
 */
class BackfillOrganizations extends Command
{
    protected $signature = 'organizations:backfill {--demo-email=demo@qrmeets.net}';

    protected $description = 'Give every existing event-owning user their own organization, and backfill their events';

    public function handle(): int
    {
        $demoEmail = $this->option('demo-email');
        $userIds = Event::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No events with an owner found - nothing to backfill.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $eventsBackfilled = 0;

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $org = $user->organizations()->first();

            if ($org) {
                $skipped++;
            } else {
                $org = $this->organizationFor($user, $demoEmail);
                $user->organizations()->attach($org->id, ['role' => 'owner']);
                $created++;
            }

            $eventsBackfilled += Event::where('user_id', $user->id)
                ->whereNull('organization_id')
                ->update(['organization_id' => $org->id]);
        }

        $this->info("Organizations created: {$created}. Users already had one: {$skipped}. Events backfilled: {$eventsBackfilled}.");

        return self::SUCCESS;
    }

    private function organizationFor(User $user, string $demoEmail): Organization
    {
        if ($user->email === $demoEmail) {
            $singleton = Organization::find(1);
            if ($singleton && ! $singleton->members()->exists()) {
                if (! $singleton->slug) {
                    $singleton->update(['slug' => $this->uniqueSlug($singleton->name)]);
                }

                return $singleton;
            }
        }

        return Organization::create([
            'name' => "{$user->name}'s Organization",
            'slug' => $this->uniqueSlug("{$user->name}'s Organization"),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
