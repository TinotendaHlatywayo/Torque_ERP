<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasUlids;

    public const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave', 'holiday'];

    protected $fillable = [
        'branch_id',
        'employee_id',
        'work_date',
        'status',
        'scheduled_minutes',
        'worked_minutes',
        'overtime_minutes',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'scheduled_minutes' => 'integer',
        'worked_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
