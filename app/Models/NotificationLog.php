<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'event_type',
        'channel_type',
        'user_id',
        'reference_id',
        'data',
        'sent_at'
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 