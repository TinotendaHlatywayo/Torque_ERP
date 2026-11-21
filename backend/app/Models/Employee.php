<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasUlids;

    public const STATUS_HIRED = 'hired';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_ARCHIVED = 'archived';

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'casual', 'internship'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'employee_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'department',
        'employment_type',
        'hire_date',
        'termination_date',
        'status',
        'national_id',
        'date_of_birth',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'archived_at',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'date_of_birth' => 'date',
        'archived_at' => 'datetime',
    ];

    protected $hidden = [
        'national_id',
        'date_of_birth',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryAssignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryAssignment::class);
    }

    public function payComponents(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_HIRED,
            self::STATUS_ACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_TERMINATED,
            self::STATUS_ARCHIVED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function transitionsFor(string $status): array
    {
        return match ($status) {
            self::STATUS_HIRED => [self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_TERMINATED],
            self::STATUS_ACTIVE => [self::STATUS_SUSPENDED, self::STATUS_TERMINATED],
            self::STATUS_SUSPENDED => [self::STATUS_ACTIVE, self::STATUS_TERMINATED],
            self::STATUS_TERMINATED => [self::STATUS_ARCHIVED],
            default => [],
        };
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::transitionsFor($this->status), true);
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
