<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'scope_type',
        'scope_id',
        'report_type',
        'file_path',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
