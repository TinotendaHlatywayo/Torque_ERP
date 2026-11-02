<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUlids;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('Audit logs are immutable.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Audit logs are immutable.');
        });
    }

    public $timestamps = false;

    protected $fillable = ['branch_id', 'user_id', 'action', 'entity_type', 'entity_id', 'ip_address', 'user_agent', 'before', 'after'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
