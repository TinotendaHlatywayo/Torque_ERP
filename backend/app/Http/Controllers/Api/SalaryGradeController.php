<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SalaryGrade;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalaryGradeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = (string) $request->query('status', 'all');

        $grades = $branch->salaryGrades()
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $grades->map(fn (SalaryGrade $grade): array => $this->payload($grade))]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $this->validated($request, $branch);
        $data['branch_id'] = $branch->id;
        $data['currency_code'] = $data['currency_code'] ?? $branch->currency_code;
        $data['code'] = strtoupper($data['code']);

        $grade = $branch->salaryGrades()->create($data);
        $this->audit->log('salary_grade.created', $grade, null, $this->snapshot($grade), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($grade)], 201);
    }

    public function update(Request $request, Branch $branch, SalaryGrade $salaryGrade): JsonResponse
    {
        abort_unless($salaryGrade->branch_id === $branch->id, 404);

        $data = $this->validated($request, $branch, $salaryGrade);
        $data['code'] = strtoupper($data['code'] ?? $salaryGrade->code);

        $before = $this->snapshot($salaryGrade);
        $salaryGrade->update($data);
        $this->audit->log('salary_grade.updated', $salaryGrade->refresh(), $before, $this->snapshot($salaryGrade), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($salaryGrade->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Branch $branch, ?SalaryGrade $grade = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('salary_grades', 'code')->where(fn ($query) => $query->where('branch_id', $branch->id))->ignore($grade?->id),
            ],
            'pay_basis' => ['required', Rule::in(SalaryGrade::BASES)],
            'base_rate_minor' => ['required', 'integer', 'min:1'],
            'overtime_multiplier' => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'overtime_hourly_rate_minor' => ['nullable', 'integer', 'min:1'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function payload(SalaryGrade $grade): array
    {
        return [
            'id' => $grade->id,
            'name' => $grade->name,
            'code' => $grade->code,
            'pay_basis' => $grade->pay_basis,
            'base_rate_minor' => $grade->base_rate_minor,
            'overtime_multiplier' => $grade->overtime_multiplier,
            'overtime_hourly_rate_minor' => $grade->overtime_hourly_rate_minor,
            'currency_code' => $grade->currency_code,
            'is_active' => $grade->is_active,
        ];
    }

    private function snapshot(SalaryGrade $grade): array
    {
        return $this->payload($grade);
    }
}
