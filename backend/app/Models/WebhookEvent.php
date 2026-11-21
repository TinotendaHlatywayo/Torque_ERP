<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_key', 'reference', 'received_at'];

    protected $casts = [
        'received_at' => 'datetime',
    ];
}
