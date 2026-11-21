<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Task;
use App\Support\AuditLogger;
use App\Support\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $tasks = $branch->tasks()
            ->with(['assignee:id,name,email', 'creator:id,name,email'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('assigned_to')->toString(), fn ($query, string $userId) => $query->where('assigned_to', $userId))
            ->withCount(['completions', 'events', 'notifications'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $tasks->map(fn (Task $task): array => $this->payload($task))]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'event_type' => ['nullable', 'string', 'max:60'],
            'reference_type' => ['nullable', 'string', 'max:60'],
            'reference_id' => ['nullable', 'string', 'max:100'],
        ]);

        $this->assertAssignable($branch, $data['assigned_to'] ?? null);
        $task = $this->tasks->create($branch, $request->user(), [
            ...$data,
            'priority' => $data['priority'] ?? 'normal',
        ]);
        $this->audit->log('task.created', $task, null, $this->snapshot($task), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($task->load(['assignee:id,name,email', 'creator:id,name,email']))], 201);
    }

    public function show(Branch $branch, Task $task): JsonResponse
    {
        abort_unless($task->branch_id === $branch->id, 404);

        return response()->json([
            'data' => $this->payload($task->load([
                'assignee:id,name,email',
                'creator:id,name,email',
                'completions.actor:id,name',
                'events' => fn ($query) => $query->orderByDesc('created_at'),
            ])),
        ]);
    }

    public function update(Request $request, Branch $branch, Task $task): JsonResponse
    {
        abort_unless($task->branch_id === $branch->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
        ]);
        $this->assertAssignable($branch, $data['assigned_to'] ?? null);

        $before = $this->snapshot($task);
        $updated = $this->tasks->update($task, $request->user(), $data);
        $this->audit->log('task.updated', $updated, $before, $this->snapshot($updated), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($updated->load(['assignee:id,name,email', 'creator:id,name,email']))]);
    }

    public function complete(Request $request, Branch $branch, Task $task): JsonResponse
    {
        abort_unless($task->branch_id === $branch->id, 404);
        $data = $request->validate(['note' => ['nullable', 'string']]);
        $before = $this->snapshot($task);
        $completed = $this->tasks->complete($task, $request->user(), $data['note'] ?? null);
        $this->audit->log('task.completed', $completed, $before, $this->snapshot($completed), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($completed)]);
    }

    public function cancel(Request $request, Branch $branch, Task $task): JsonResponse
    {
        abort_unless($task->branch_id === $branch->id, 404);
        $data = $request->validate(['reason' => ['nullable', 'string']]);
        $before = $this->snapshot($task);
        $cancelled = $this->tasks->cancel($task, $request->user(), $data['reason'] ?? null);
        $this->audit->log('task.cancelled', $cancelled, $before, $this->snapshot($cancelled), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($cancelled)]);
    }

    /** @return array<string, mixed> */
    private function payload(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->relationLoaded('assignee') ? $task->assignee : null,
            'created_by' => $task->created_by,
            'creator' => $task->relationLoaded('creator') ? $task->creator : null,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'event_type' => $task->event_type,
            'reference_type' => $task->reference_type,
            'reference_id' => $task->reference_id,
            'completions' => $task->relationLoaded('completions')
                ? $task->completions->map(fn ($completion): array => [
                    'id' => $completion->id,
                    'actor' => $completion->actor,
                    'note' => $completion->note,
                    'created_at' => $completion->created_at?->toIso8601String(),
                ])
                : [],
            'events' => $task->relationLoaded('events')
                ? $task->events->map(fn ($event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                : [],
            'counts' => [
                'completions' => $task->completions_count ?? 0,
                'events' => $task->events_count ?? 0,
                'notifications' => $task->notifications_count ?? 0,
            ],
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(Task $task): array
    {
        return [
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'assigned_to' => $task->assigned_to,
            'due_at' => $task->due_at?->toIso8601String(),
        ];
    }

    private function assertAssignable(Branch $branch, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $exists = BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->exists();

        throw_unless($exists, ValidationException::withMessages([
            'assigned_to' => 'The selected user is not an active member of this branch.',
        ]));
    }
}
