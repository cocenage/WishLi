<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'type',
        'dedupe_key',
        'related_type',
        'related_id',
        'text',
        'payload',
        'sent_at',
        'failed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}