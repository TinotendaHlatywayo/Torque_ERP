<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PayrollStatutoryRule;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollStatutoryRuleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $rules = $branch->payrollStatutoryRules()
            ->when($request->query('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $rules->map(fn (PayrollStatutoryRule $payrollStatutoryRule): array => $this->payload($payrollStatutoryRule))]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $this->validated($request, $branch);
        $data['code'] = strtoupper($data['code']);

        $payrollStatutoryRule = $branch->payrollStatutoryRules()->create($data);
        $this->audit->log('payroll_statutory_rule.created', $payrollStatutoryRule, null, $this->snapshot($payrollStatutoryRule), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($payrollStatutoryRule)], 201);
    }

    public function update(Request $request, Branch $branch, PayrollStatutoryRule $payrollStatutoryRule): JsonResponse
    {
        abort_unless($payrollStatutoryRule->branch_id === $branch->id, 404);

        $data = $this->validated($request, $branch, $payrollStatutoryRule);
        $data['code'] = strtoupper($data['code'] ?? $payrollStatutoryRule->code);

        $before = $this->snapshot($payrollStatutoryRule);
        $payrollStatutoryRule->update($data);
        $this->audit->log('payroll_statutory_rule.updated', $payrollStatutoryRule->refresh(), $before, $this->snapshot($payrollStatutoryRule), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($payrollStatutoryRule->refresh())]);
    }

    private function validated(Request $request, Branch $branch, ?PayrollStatutoryRule $payrollStatutoryRule = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('payroll_statutory_rules', 'code')->where(fn ($query) => $query->where('branch_id', $branch->id))->ignore($payrollStatutoryRule?->id),
            ],
            'applies_to' => ['required', Rule::in(PayrollStatutoryRule::BASES)],
            'employee_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'employer_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'fixed_employee_minor' => ['sometimes', 'integer', 'min:0'],
            'fixed_employer_minor' => ['sometimes', 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function payload(PayrollStatutoryRule $payrollStatutoryRule): array
    {
        return [
            'id' => $payrollStatutoryRule->id,
            'name' => $payrollStatutoryRule->name,
            'code' => $payrollStatutoryRule->code,
            'applies_to' => $payrollStatutoryRule->applies_to,
            'employee_rate' => $payrollStatutoryRule->employee_rate,
            'employer_rate' => $payrollStatutoryRule->employer_rate,
            'fixed_employee_minor' => $payrollStatutoryRule->fixed_employee_minor,
            'fixed_employer_minor' => $payrollStatutoryRule->fixed_employer_minor,
            'effective_from' => $payrollStatutoryRule->effective_from?->toDateString(),
            'effective_to' => $payrollStatutoryRule->effective_to?->toDateString(),
            'is_active' => $payrollStatutoryRule->is_active,
        ];
    }

    private function snapshot(PayrollStatutoryRule $payrollStatutoryRule): array
    {
        return $this->payload($payrollStatutoryRule);
    }
}
