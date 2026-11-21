<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PayrollPeriod;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PayrollPeriodController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Branch $branch): JsonResponse
    {
        $periods = $branch->payrollPeriods()
            ->with(['runs' => fn ($query) => $query->orderByDesc('version')])
            ->orderByDesc('start_date')
            ->get();

        return response()->json(['data' => $periods->map(fn (PayrollPeriod $period): array => $this->payload($period))]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pay_date' => ['required', 'date', 'after_or_equal:end_date'],
        ]);

        $overlap = $branch->payrollPeriods()
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'This payroll period overlaps an existing period.']);
        }

        $period = $branch->payrollPeriods()->create([
            ...$data,
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
        $this->audit->log('payroll_period.created', $period, null, $this->snapshot($period), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($period)], 201);
    }

    public function show(Branch $branch, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless($payrollPeriod->branch_id === $branch->id, 404);

        return response()->json([
            'data' => $this->payload($payrollPeriod->load(['runs' => fn ($query) => $query->orderByDesc('version')])),
        ]);
    }

    private function payload(PayrollPeriod $period): array
    {
        $latest = $period->relationLoaded('runs') ? $period->runs->first() : null;

        return [
            'id' => $period->id,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'pay_date' => $period->pay_date?->toDateString(),
            'status' => $period->status,
            'locked_at' => $period->locked_at?->toIso8601String(),
            'latest_run' => $latest ? [
                'id' => $latest->id,
                'status' => $latest->status,
                'version' => $latest->version,
                'gross_minor' => $latest->gross_minor,
                'deductions_minor' => $latest->deductions_minor,
                'net_minor' => $latest->net_minor,
                'warnings' => $latest->warnings ?? [],
            ] : null,
            'runs' => $period->relationLoaded('runs')
                ? $period->runs->map(fn ($run): array => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'version' => $run->version,
                    'gross_minor' => $run->gross_minor,
                    'deductions_minor' => $run->deductions_minor,
                    'net_minor' => $run->net_minor,
                ])
                : [],
        ];
    }

    private function snapshot(PayrollPeriod $period): array
    {
        return [
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'pay_date' => $period->pay_date?->toDateString(),
            'status' => $period->status,
        ];
    }
}
