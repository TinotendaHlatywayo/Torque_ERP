<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DisciplinaryRecord;
use App\Models\HrAttachment;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisciplinaryRecordController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $status = (string) $request->query('status', '');
        $employeeId = (string) $request->query('employee_id', '');

        $records = $branch->disciplinaryRecords()
            ->with('employee')
            ->when($status !== '' && in_array($status, DisciplinaryRecord::statuses(), true), fn ($query) => $query->where('status', $status))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $records->getCollection()->map(fn (DisciplinaryRecord $record): array => $this->payload($record)),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
            'categories' => DisciplinaryRecord::CATEGORIES,
            'severities' => DisciplinaryRecord::SEVERITIES,
            'statuses' => DisciplinaryRecord::statuses(),
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
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(DisciplinaryRecord::CATEGORIES)],
            'severity' => ['required', Rule::in(DisciplinaryRecord::SEVERITIES)],
            'summary' => ['required', 'string', 'max:240'],
            'details' => ['nullable', 'string', 'max:20000'],
            'action_taken' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = $branch->disciplinaryRecords()->create([
            'employee_id' => $data['employee_id'],
            'incident_date' => $data['incident_date'],
            'category' => $data['category'],
            'severity' => $data['severity'],
            'summary' => $data['summary'],
            'details' => $data['details'] ?? null,
            'status' => DisciplinaryRecord::STATUS_OPEN,
            'action_taken' => $data['action_taken'] ?? null,
            'issued_by' => $request->user()->id,
            'issued_at' => now(),
        ]);

        $this->audit->log('disciplinary_record.created', $record, null, $this->snapshot($record), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($record->load('employee'))], 201);
    }

    public function show(Branch $branch, DisciplinaryRecord $disciplinaryRecord): JsonResponse
    {
        return response()->json([
            'data' => $this->payload($disciplinaryRecord->load('employee')),
            'allowed_transitions' => DisciplinaryRecord::transitionsFor($disciplinaryRecord->status),
        ]);
    }

    public function update(Request $request, Branch $branch, DisciplinaryRecord $disciplinaryRecord): JsonResponse
    {
        if ($disciplinaryRecord->status === DisciplinaryRecord::STATUS_CLOSED) {
            throw ValidationException::withMessages(['record' => 'Closed disciplinary records cannot be edited.']);
        }

        $data = $request->validate([
            'incident_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'category' => ['sometimes', Rule::in(DisciplinaryRecord::CATEGORIES)],
            'severity' => ['sometimes', Rule::in(DisciplinaryRecord::SEVERITIES)],
            'summary' => ['sometimes', 'string', 'max:240'],
            'details' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'action_taken' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'resolution_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $before = $this->snapshot($disciplinaryRecord);
        $disciplinaryRecord->update($data);

        $this->audit->log('disciplinary_record.updated', $disciplinaryRecord->refresh(), $before, $this->snapshot($disciplinaryRecord), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($disciplinaryRecord->refresh()->load('employee'))]);
    }

    public function transition(Request $request, Branch $branch, DisciplinaryRecord $disciplinaryRecord): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(DisciplinaryRecord::statuses())],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! $disciplinaryRecord->canTransitionTo($data['status'])) {
            throw ValidationException::withMessages([
                'status' => "Record cannot move from {$disciplinaryRecord->status} to {$data['status']}.",
            ]);
        }

        $before = $this->snapshot($disciplinaryRecord);
        $attributes = ['status' => $data['status']];

        if (isset($data['resolution_note'])) {
            $attributes['resolution_note'] = $data['resolution_note'];
        }

        if (isset($data['action_taken'])) {
            $attributes['action_taken'] = $data['action_taken'];
        }

        if (in_array($data['status'], [DisciplinaryRecord::STATUS_RESOLVED, DisciplinaryRecord::STATUS_CLOSED], true)) {
            $attributes['resolved_at'] = now();
        }

        $disciplinaryRecord->update($attributes);

        $this->audit->log('disciplinary_record.status_changed', $disciplinaryRecord->refresh(), $before, $this->snapshot($disciplinaryRecord), $branch->id, $request->user());

        return response()->json([
            'data' => $this->payload($disciplinaryRecord->refresh()->load('employee')),
            'allowed_transitions' => DisciplinaryRecord::transitionsFor($disciplinaryRecord->status),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DisciplinaryRecord $record): array
    {
        $attachments = HrAttachment::where('attachable_type', HrAttachment::TYPE_DISCIPLINARY_RECORD)
            ->where('attachable_id', $record->id)
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
            'id' => $record->id,
            'employee' => $record->employee ? [
                'id' => $record->employee->id,
                'employee_number' => $record->employee->employee_number,
                'full_name' => trim($record->employee->first_name.' '.$record->employee->last_name),
            ] : null,
            'incident_date' => $record->incident_date?->toDateString(),
            'category' => $record->category,
            'severity' => $record->severity,
            'summary' => $record->summary,
            'details' => $record->details,
            'status' => $record->status,
            'action_taken' => $record->action_taken,
            'issued_at' => $record->issued_at?->toIso8601String(),
            'resolved_at' => $record->resolved_at?->toIso8601String(),
            'resolution_note' => $record->resolution_note,
            'attachments' => $attachments,
            'allowed_transitions' => DisciplinaryRecord::transitionsFor($record->status),
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DisciplinaryRecord $record): array
    {
        return [
            'employee_id' => $record->employee_id,
            'incident_date' => $record->incident_date?->toDateString(),
            'category' => $record->category,
            'severity' => $record->severity,
            'summary' => $record->summary,
            'details' => $record->details,
            'status' => $record->status,
            'action_taken' => $record->action_taken,
            'resolution_note' => $record->resolution_note,
        ];
    }
}
