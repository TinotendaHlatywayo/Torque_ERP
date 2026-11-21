<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttachment extends Model
{
    use HasUlids;

    public const TYPE_LEAVE_REQUEST = 'leave_request';

    public const TYPE_DISCIPLINARY_RECORD = 'disciplinary_record';

    public const TYPES = [self::TYPE_LEAVE_REQUEST, self::TYPE_DISCIPLINARY_RECORD];

    protected $fillable = [
        'branch_id',
        'employee_id',
        'attachable_type',
        'attachable_id',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    protected $hidden = ['path'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
