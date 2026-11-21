<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\TransitionEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Employee;
use App\Support\AccessRevocation;
use App\Support\AuditLogger;
use App\Support\DocumentNumberService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccessRevocation $revocation,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = (string) $request->query('status', 'current');
        $allowedStatuses = array_merge(['current', 'all'], Employee::statuses());
        $status = in_array($status, $allowedStatuses, true) ? $status : 'current';

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $search = trim((string) $request->query('q', ''));
        $department = trim((string) $request->query('department', ''));

        $employees = $branch->employees()
            ->with('user')
            ->when($status === 'current', fn ($query) => $query->whereIn('status', [
                Employee::STATUS_HIRED,
                Employee::STATUS_ACTIVE,
                Employee::STATUS_SUSPENDED,
            ]))
            ->when($status !== 'current' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($department !== '', fn ($query) => $query->where('department', $department))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($nested) use ($like): void {
                    $nested->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $sensitive = $this->canViewSensitive($request);

        return response()->json([
            'data' => $employees->getCollection()
                ->map(fn (Employee $employee): array => $this->payload($employee, $sensitive)),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
            'statuses' => $this->statusOptions(),
            'employment_types' => Employee::EMPLOYMENT_TYPES,
        ]);
    }

    public function linkableUsers(Branch $branch): JsonResponse
    {
        $linked = $branch->employees()->whereNotNull('user_id')->pluck('user_id');

        $users = $branch->memberships()
            ->with('user')
            ->where('is_active', true)
            ->whereNotIn('user_id', $linked)
            ->orderBy('created_at')
            ->get()
            ->map(fn (BranchUser $membership): array => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role,
            ])
            ->values();

        return response()->json(['data' => $users]);
    }

    public function store(StoreEmployeeRequest $request, Branch $branch): JsonResponse
    {
        $data = $request->validated();

        $employee = DB::transaction(function () use ($branch, $data, $request): Employee {
            $employee = $branch->employees()->create([
                'user_id' => $data['user_id'] ?? null,
                'employee_number' => $data['employee_number'] ?? $this->numbers->allocate($branch, 'emp'),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'department' => $data['department'] ?? null,
                'employment_type' => $data['employment_type'] ?? 'full_time',
                'hire_date' => $data['hire_date'],
                'termination_date' => $data['termination_date'] ?? null,
                'status' => $data['status'] ?? Employee::STATUS_HIRED,
                'national_id' => $data['national_id'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'address' => $data['address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log(
                'employee.created',
                $employee,
                null,
                $this->snapshot($employee),
                $branch->id,
                $request->user(),
            );

            return $employee->load('user');
        });

        return response()->json([
            'data' => $this->payload($employee, $this->canViewSensitive($request)),
        ], 201);
    }

    public function show(Request $request, Branch $branch, Employee $employee): JsonResponse
    {
        $employee->load('user');

        return response()->json([
            'data' => $this->payload($employee, $this->canViewSensitive($request)),
            'allowed_transitions' => Employee::transitionsFor($employee->status),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Branch $branch, Employee $employee): JsonResponse
    {
        if ($employee->isArchived()) {
            throw ValidationException::withMessages([
                'employee' => 'Archived employee records cannot be edited.',
            ]);
        }

        $data = $request->validated();
        $before = $this->snapshot($employee);

        $employee->update($data);

        $this->audit->log(
            'employee.updated',
            $employee->refresh(),
            $before,
            $this->snapshot($employee),
            $branch->id,
            $request->user(),
        );

        return response()->json([
            'data' => $this->payload($employee->refresh()->load('user'), $this->canViewSensitive($request)),
        ]);
    }

    public function transition(TransitionEmployeeRequest $request, Branch $branch, Employee $employee): JsonResponse
    {
        $data = $request->validated();
        $target = $data['status'];

        if (! $employee->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Employee cannot move from {$employee->status} to {$target}.",
            ]);
        }

        $before = $this->snapshot($employee);

        DB::transaction(function () use ($branch, $employee, $target, $data, $request, $before): void {
            $effectiveDate = $data['effective_date'] ?? now()->toDateString();
            $attributes = ['status' => $target];

            if ($target === Employee::STATUS_TERMINATED) {
                $attributes['termination_date'] = $effectiveDate;
            }

            if ($target === Employee::STATUS_ACTIVE) {
                $attributes['termination_date'] = null;
            }

            if ($target === Employee::STATUS_ARCHIVED) {
                $attributes['archived_at'] = now();
            }

            $employee->update($attributes);

            $this->audit->log(
                'employee.status_changed',
                $employee->refresh(),
                $before,
                array_merge($this->snapshot($employee), [
                    'effective_date' => $effectiveDate,
                    'reason' => $data['reason'] ?? null,
                ]),
                $branch->id,
                $request->user(),
            );

            $revoke = $data['revoke_access'] ?? in_array($target, [Employee::STATUS_TERMINATED, Employee::STATUS_ARCHIVED], true);

            if ($revoke && in_array($target, [Employee::STATUS_TERMINATED, Employee::STATUS_ARCHIVED], true)) {
                $this->revokeBranchAccess($branch, $employee, $request);
            }
        });

        return response()->json([
            'data' => $this->payload($employee->refresh()->load('user'), $this->canViewSensitive($request)),
            'allowed_transitions' => Employee::transitionsFor($employee->status),
        ]);
    }

    public function archive(Request $request, Branch $branch, Employee $employee): Response
    {
        if ($employee->status !== Employee::STATUS_TERMINATED) {
            throw ValidationException::withMessages([
                'status' => 'Only a terminated employee can be archived. Terminate the employee first.',
            ]);
        }

        $before = $this->snapshot($employee);

        DB::transaction(function () use ($branch, $employee, $request, $before): void {
            $employee->update([
                'status' => Employee::STATUS_ARCHIVED,
                'archived_at' => now(),
            ]);

            $this->audit->log(
                'employee.archived',
                $employee->refresh(),
                $before,
                $this->snapshot($employee),
                $branch->id,
                $request->user(),
            );

            $this->revokeBranchAccess($branch, $employee, $request);
        });

        return response()->noContent();
    }

    private function revokeBranchAccess(Branch $branch, Employee $employee, Request $request): void
    {
        if (! $employee->user_id) {
            return;
        }

        $membership = BranchUser::where('branch_id', $branch->id)
            ->where('user_id', $employee->user_id)
            ->where('is_active', true)
            ->first();

        if (! $membership) {
            return;
        }

        if ($membership->isAdmin()) {
            $otherAdmins = BranchUser::where('branch_id', $branch->id)
                ->where('is_active', true)
                ->where('role', 'admin')
                ->where('id', '!=', $membership->id)
                ->count();

            if ($otherAdmins === 0) {
                throw ValidationException::withMessages([
                    'revoke_access' => 'Assign another active branch administrator before revoking this employee\'s access.',
                ]);
            }
        }

        $membership->update(['is_active' => false]);

        $this->audit->log(
            'employee.access_revoked',
            $employee,
            ['membership_id' => $membership->id, 'user_id' => $employee->user_id, 'is_active' => true],
            ['membership_id' => $membership->id, 'user_id' => $employee->user_id, 'is_active' => false],
            $branch->id,
            $request->user(),
        );

        $this->revocation->revokeIfNoBranchAccess($membership->user);
    }

    private function canViewSensitive(Request $request): bool
    {
        $branchUser = $request->attributes->get('branch_user');

        return $branchUser instanceof BranchUser
            && $branchUser->hasPermission(Permissions::BRANCH_HR_SENSITIVE_VIEW);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Employee $employee, bool $sensitive): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'full_name' => trim($employee->first_name.' '.$employee->last_name),
            'email' => $employee->email,
            'phone' => $employee->phone,
            'job_title' => $employee->job_title,
            'department' => $employee->department,
            'employment_type' => $employee->employment_type,
            'hire_date' => $employee->hire_date?->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'status' => $employee->status,
            'user' => $employee->user ? [
                'id' => $employee->user->id,
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'is_active' => $employee->user->is_active,
            ] : null,
            'sensitive' => [
                'national_id' => $sensitive ? $employee->national_id : null,
                'date_of_birth' => $sensitive ? $employee->date_of_birth?->toDateString() : null,
                'address' => $sensitive ? $employee->address : null,
                'emergency_contact_name' => $sensitive ? $employee->emergency_contact_name : null,
                'emergency_contact_phone' => $sensitive ? $employee->emergency_contact_phone : null,
                'notes' => $sensitive ? $employee->notes : null,
            ],
            'sensitive_redacted' => ! $sensitive,
            'archived_at' => $employee->archived_at?->toIso8601String(),
            'allowed_transitions' => Employee::transitionsFor($employee->status),
            'created_at' => $employee->created_at?->toIso8601String(),
            'updated_at' => $employee->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Employee $employee): array
    {
        return [
            'employee_number' => $employee->employee_number,
            'user_id' => $employee->user_id,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'job_title' => $employee->job_title,
            'department' => $employee->department,
            'employment_type' => $employee->employment_type,
            'hire_date' => $employee->hire_date?->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'status' => $employee->status,
            'national_id' => $employee->national_id,
            'date_of_birth' => $employee->date_of_birth?->toDateString(),
            'address' => $employee->address,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
            'notes' => $employee->notes,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => Employee::STATUS_HIRED, 'label' => 'Hired'],
            ['value' => Employee::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Employee::STATUS_SUSPENDED, 'label' => 'Suspended'],
            ['value' => Employee::STATUS_TERMINATED, 'label' => 'Terminated'],
            ['value' => Employee::STATUS_ARCHIVED, 'label' => 'Archived'],
        ];
    }
}
