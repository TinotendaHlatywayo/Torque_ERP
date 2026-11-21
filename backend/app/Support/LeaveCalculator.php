<?php

namespace App\Support;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

final class LeaveCalculator
{
    public function days(CarbonImmutable $start, CarbonImmutable $end, bool $countsWeekends): float
    {
        if ($end->lt($start)) {
            return 0.0;
        }

        if ($countsWeekends) {
            return (float) ($start->diffInDays($end) + 1);
        }

        $days = 0;
        $cursor = $start;

        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend()) {
                $days++;
            }
            $cursor = $cursor->addDay();
        }

        return (float) $days;
    }

    public function approvedDaysInYear(string $employeeId, string $leaveTypeId, int $year, ?string $excludeRequestId = null): float
    {
        return (float) LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->when($excludeRequestId, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->sum('days_requested');
    }

    public function exceedsAnnualLimit(LeaveType $type, float $approvedDays, float $requestedDays): bool
    {
        if ($type->max_days_per_year === null) {
            return false;
        }

        return ($approvedDays + $requestedDays) > (float) $type->max_days_per_year;
    }

    public function overlaps(string $employeeId, string $start, string $end, ?string $excludeRequestId = null): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', LeaveRequest::ACTIVE_STATUSES)
            ->when($excludeRequestId, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
    }
}
