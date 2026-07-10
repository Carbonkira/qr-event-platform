<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    protected $fillable = [
        'name',
        'tasks',
    ];

    protected function casts(): array
    {
        return [
            'tasks' => 'array',
        ];
    }
}
