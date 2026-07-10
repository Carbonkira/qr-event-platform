<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row table (id=1) mirroring the mock's global getOrg()/updateOrg() -
 * there is no multi-tenancy, every organizer shares the same org profile.
 */
class Organization extends Model
{
    protected $fillable = [
        'name',
        'description',
        'organized_by',
        'email',
        'industry',
        'instagram',
        'linkedin',
        'facebook',
        'website',
        'twitter',
        'privacy_policy_url',
    ];
}
