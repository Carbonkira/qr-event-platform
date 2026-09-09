<?php

namespace App\Providers;

use App\Models\Organizer;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum's personal_access_tokens.tokenable_type is a polymorphic
        // column - both account types independently `use HasApiTokens`
        // (see Organizer/Participant), so it needs to be able to tell them
        // apart. Without a morph map it'd store the fully-qualified class
        // name instead, which works too but ties every existing token to
        // never renaming these classes; the map is a one-line guard against
        // that. Deliberately not enforceMorphMap() - a few pre-cutover
        // App\Models\User tokens may still be reachable during the backfill
        // window, and those aren't (and shouldn't be) in this map.
        Relation::morphMap([
            'organizer' => Organizer::class,
            'participant' => Participant::class,
        ]);
    }
}
