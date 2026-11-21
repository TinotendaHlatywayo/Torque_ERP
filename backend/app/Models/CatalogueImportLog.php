<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueImportLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id',
        'type',
        'source',
        'format',
        'idempotency_key',
        'rows_received',
        'rows_created',
        'rows_skipped',
        'summary',
        'created_by',
    ];

    protected $casts = [
        'rows_received' => 'integer',
        'rows_created' => 'integer',
        'rows_skipped' => 'integer',
        'summary' => 'array',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
