<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\HrAttachment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\AuditLogger;
use App\Support\LeaveCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LeaveCalculator $calculator,
    ) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = (string) $request->query('status', 'all');
        $employeeId = (string) $request->query('employee_id', '');
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $requests = $branch->leaveRequests()
            ->with(['employee', 'leaveType', 'decider'])
            ->when(in_array($status, LeaveRequest::ACTIVE_STATUSES, true) || in_array($status, [LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED], true), fn ($query) => $query->where('status', $status))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $requests->getCollection()
                ->map(fn (LeaveRequest $leave): array => $this->payload($leave)),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
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
            'leave_type_id' => [
                'required',
                'string',
                Rule::exists('leave_types', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id)->where('is_active', true)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $employee = Employee::where('branch_id', $branch->id)->findOrFail($data['employee_id']);

        if (in_array($employee->status, [Employee::STATUS_TERMINATED, Employee::STATUS_ARCHIVED], true)) {
            throw ValidationException::withMessages(['employee_id' => 'Terminated or archived employees cannot take new leave.']);
        }

        $type = LeaveType::where('branch_id', $branch->id)->findOrFail($data['leave_type_id']);
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $days = $this->calculator->days($start, $end, $type->counts_weekends);

        if ($days <= 0) {
            throw ValidationException::withMessages(['end_date' => 'The selected range contains no working days for this leave type.']);
        }

        if ($this->calculator->overlaps($employee->id, $data['start_date'], $data['end_date'])) {
            throw ValidationException::withMessages(['start_date' => 'This leave overlaps an existing submitted or approved request.']);
        }

        $approved = $this->calculator->approvedDaysInYear($employee->id, $type->id, (int) $start->year);
        if ($this->calculator->exceedsAnnualLimit($type, $approved, $days)) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'This request exceeds the configured annual leave limit for this leave type.',
            ]);
        }

        $leave = DB::transaction(function () use ($branch, $data, $employee, $type, $days, $request): LeaveRequest {
            $autoApprove = ! $type->requires_approval;
            $leave = $branch->leaveRequests()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_requested' => round($days, 2),
                'reason' => $data['reason'] ?? null,
                'status' => $autoApprove ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_SUBMITTED,
                'decided_by' => $autoApprove ? $request->user()->id : null,
                'decided_at' => $autoApprove ? now() : null,
                'decision_note' => $autoApprove ? 'Auto-approved by leave type configuration.' : null,
                'created_by' => $request->user()->id,
            ]);

            $this->audit->log('leave_request.created', $leave, null, $this->snapshot($leave), $branch->id, $request->user());

            return $leave->load(['employee', 'leaveType', 'decider']);
        });

        return response()->json(['data' => $this->payload($leave)], 201);
    }

    public function show(Branch $branch, LeaveRequest $leaveRequest): JsonResponse
    {
        return response()->json([
            'data' => $this->payload($leaveRequest->load(['employee', 'leaveType', 'decider'])),
        ]);
    }

    public function approve(Request $request, Branch $branch, LeaveRequest $leaveRequest): JsonResponse
    {
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:5000']]);

        if ($leaveRequest->status !== LeaveRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted leave requests can be approved.']);
        }

        $leaveRequest->load(['leaveType', 'employee']);
        $type = $leaveRequest->leaveType;

        if ($this->calculator->overlaps($leaveRequest->employee_id, $leaveRequest->start_date->toDateString(), $leaveRequest->end_date->toDateString(), $leaveRequest->id)) {
            throw ValidationException::withMessages(['start_date' => 'This request now overlaps another submitted or approved request.']);
        }

        $approvedDays = $this->calculator->approvedDaysInYear(
            $leaveRequest->employee_id,
            $leaveRequest->leave_type_id,
            (int) $leaveRequest->start_date->year,
            $leaveRequest->id,
        );

        if ($this->calculator->exceedsAnnualLimit($type, $approvedDays, (float) $leaveRequest->days_requested)) {
            throw ValidationException::withMessages(['leave_type_id' => 'Approving this request would exceed the configured annual leave limit.']);
        }

        return $this->decide($request, $branch, $leaveRequest, LeaveRequest::STATUS_APPROVED, $data['decision_note'] ?? null);
    }

    public function reject(Request $request, Branch $branch, LeaveRequest $leaveRequest): JsonResponse
    {
        $data = $request->validate(['decision_note' => ['required', 'string', 'max:5000']]);

        if ($leaveRequest->status !== LeaveRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted leave requests can be rejected.']);
        }

        return $this->decide($request, $branch, $leaveRequest, LeaveRequest::STATUS_REJECTED, $data['decision_note']);
    }

    public function cancel(Request $request, Branch $branch, LeaveRequest $leaveRequest): JsonResponse
    {
        if (! in_array($leaveRequest->status, LeaveRequest::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Only submitted or approved leave requests can be cancelled.']);
        }

        $before = $this->snapshot($leaveRequest);
        $leaveRequest->update(['status' => LeaveRequest::STATUS_CANCELLED]);

        $this->audit->log('leave_request.cancelled', $leaveRequest->refresh(), $before, $this->snapshot($leaveRequest), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($leaveRequest->refresh()->load(['employee', 'leaveType', 'decider']))]);
    }

    private function decide(Request $request, Branch $branch, LeaveRequest $leaveRequest, string $status, ?string $note): JsonResponse
    {
        $before = $this->snapshot($leaveRequest);

        $leaveRequest->update([
            'status' => $status,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $this->audit->log('leave_request.'.$status, $leaveRequest->refresh(), $before, $this->snapshot($leaveRequest), $branch->id, $request->user());

        return response()->json([
            'data' => $this->payload($leaveRequest->refresh()->load(['employee', 'leaveType', 'decider'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LeaveRequest $leave): array
    {
        $attachments = HrAttachment::where('attachable_type', HrAttachment::TYPE_LEAVE_REQUEST)
            ->where('attachable_id', $leave->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (HrAttachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ]);

        return [
            'id' => $leave->id,
            'employee' => $leave->employee ? [
                'id' => $leave->employee->id,
                'employee_number' => $leave->employee->employee_number,
                'full_name' => trim($leave->employee->first_name.' '.$leave->employee->last_name),
            ] : null,
            'leave_type' => $leave->leaveType ? [
                'id' => $leave->leaveType->id,
                'name' => $leave->leaveType->name,
                'code' => $leave->leaveType->code,
                'is_paid' => $leave->leaveType->is_paid,
                'requires_approval' => $leave->leaveType->requires_approval,
            ] : null,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'days_requested' => $leave->days_requested,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'decided_by' => $leave->decider ? [
                'id' => $leave->decider->id,
                'name' => $leave->decider->name,
            ] : null,
            'decided_at' => $leave->decided_at?->toIso8601String(),
            'decision_note' => $leave->decision_note,
            'attachments' => $attachments,
            'created_at' => $leave->created_at?->toIso8601String(),
            'updated_at' => $leave->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(LeaveRequest $leave): array
    {
        return [
            'employee_id' => $leave->employee_id,
            'leave_type_id' => $leave->leave_type_id,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'days_requested' => $leave->days_requested,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'decided_by' => $leave->decided_by,
            'decision_note' => $leave->decision_note,
        ];
    }
}
