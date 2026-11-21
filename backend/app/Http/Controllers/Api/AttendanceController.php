<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 31), 1), 100);
        $employeeId = (string) $request->query('employee_id', '');
        $from = $request->query('from');
        $to = $request->query('to');
        $status = (string) $request->query('status', '');

        $records = $branch->attendanceRecords()
            ->with('employee')
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->when($from, fn ($query) => $query->whereDate('work_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('work_date', '<=', $to))
            ->when($status !== '' && in_array($status, AttendanceRecord::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('work_date')
            ->orderBy('employee_id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $records->getCollection()->map(fn (AttendanceRecord $record): array => $this->payload($record)),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
            'statuses' => AttendanceRecord::STATUSES,
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => [
                'required',
                'string',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id)),
            ],
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'scheduled_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'worked_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'overtime_minutes' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = $branch->attendanceRecords()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('work_date', $data['work_date'])
            ->first();

        $before = $record ? $this->snapshot($record) : null;

        if ($record) {
            $record->update([
                'status' => $data['status'],
                'scheduled_minutes' => $data['scheduled_minutes'] ?? $record->scheduled_minutes,
                'worked_minutes' => $data['worked_minutes'] ?? $record->worked_minutes,
                'overtime_minutes' => $data['overtime_minutes'] ?? $record->overtime_minutes,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
        } else {
            $record = $branch->attendanceRecords()->create([
                'employee_id' => $data['employee_id'],
                'work_date' => $data['work_date'],
                'status' => $data['status'],
                'scheduled_minutes' => $data['scheduled_minutes'] ?? 0,
                'worked_minutes' => $data['worked_minutes'] ?? 0,
                'overtime_minutes' => $data['overtime_minutes'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
        }

        $this->audit->log(
            $before ? 'attendance.updated' : 'attendance.created',
            $record->refresh(),
            $before,
            $this->snapshot($record),
            $branch->id,
            $request->user(),
        );

        return response()->json([
            'data' => $this->payload($record->refresh()->load('employee')),
        ], $before ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'employee' => $record->employee ? [
                'id' => $record->employee->id,
                'employee_number' => $record->employee->employee_number,
                'full_name' => trim($record->employee->first_name.' '.$record->employee->last_name),
            ] : null,
            'work_date' => $record->work_date?->toDateString(),
            'status' => $record->status,
            'scheduled_minutes' => $record->scheduled_minutes,
            'worked_minutes' => $record->worked_minutes,
            'overtime_minutes' => $record->overtime_minutes,
            'notes' => $record->notes,
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AttendanceRecord $record): array
    {
        return [
            'employee_id' => $record->employee_id,
            'work_date' => $record->work_date?->toDateString(),
            'status' => $record->status,
            'scheduled_minutes' => $record->scheduled_minutes,
            'worked_minutes' => $record->worked_minutes,
            'overtime_minutes' => $record->overtime_minutes,
            'notes' => $record->notes,
        ];
    }
}
