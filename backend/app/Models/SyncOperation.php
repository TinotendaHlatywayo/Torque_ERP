<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id', 'device_id', 'actor_id', 'operation_type', 'operation_id',
        'payload_version', 'idempotency_key', 'status', 'client_time', 'server_time',
        'payload', 'result', 'error_message', 'error_class', 'attempt_count',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'client_time' => 'datetime',
        'server_time' => 'datetime',
        'payload_version' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
