<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryRecord extends Model
{
    use HasUlids;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_APPEALED = 'appealed';

    public const STATUS_CLOSED = 'closed';

    public const CATEGORIES = ['conduct', 'performance', 'attendance', 'safety', 'policy', 'other'];

    public const SEVERITIES = ['verbal', 'written', 'final_warning', 'suspension', 'other'];

    protected $fillable = [
        'branch_id',
        'employee_id',
        'incident_date',
        'category',
        'severity',
        'summary',
        'details',
        'status',
        'action_taken',
        'issued_by',
        'issued_at',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'issued_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_APPEALED, self::STATUS_CLOSED];
    }

    /**
     * @return array<int, string>
     */
    public static function transitionsFor(string $status): array
    {
        return match ($status) {
            self::STATUS_OPEN => [self::STATUS_RESOLVED, self::STATUS_CLOSED],
            self::STATUS_RESOLVED => [self::STATUS_APPEALED, self::STATUS_CLOSED],
            self::STATUS_APPEALED => [self::STATUS_RESOLVED, self::STATUS_CLOSED],
            default => [],
        };
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::transitionsFor($this->status), true);
    }
}
