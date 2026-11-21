<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\DisciplinaryRecord;
use App\Models\Employee;
use App\Models\HrAttachment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class LeaveAttendanceDisciplinaryTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $hrViewer;

    private User $hrManager;

    private Employee $employee;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'LVA');
        $this->branchB = $this->branch($this->platform, 'LVB');

        $this->admin = $this->user('HR Admin');
        $this->hrViewer = $this->user('HR Viewer');
        $this->hrManager = $this->user('HR Manager');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->hrViewer, 'viewer', true, ['branch.hr.view']);
        $this->membership($this->branchA, $this->hrManager, 'viewer', true, [
            'branch.hr.view',
            'branch.hr.manage',
        ]);
        $this->membership($this->branchB, $this->user('B Admin'), 'admin');

        $this->employee = $this->employee($this->branchA, 'EMP-100', 'Leave Tester');
        $this->employeeB = $this->employee($this->branchB, 'EMP-200', 'Branch B Employee');
    }

    public function test_leave_type_and_weekday_leave_request_are_created_and_approved(): void
    {
        $type = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-types", [
                'name' => 'Annual leave',
                'code' => 'annual',
                'max_days_per_year' => 20,
                'counts_weekends' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ANNUAL');

        $request = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $type->json('data.id'),
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-05',
                'reason' => 'Family trip',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertSame(5.0, (float) $request->json('data.days_requested'));

        $id = $request->json('data.id');

        $this->withAuthToken($this->hrViewer)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests/{$id}/approve")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests/{$id}/approve", [
                'decision_note' => 'Approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decision_note', 'Approved');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $id,
            'branch_id' => $this->branchA->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'leave_request.approved',
            'entity_id' => $id,
        ]);
    }

    public function test_leave_overlap_annual_limit_and_date_boundaries_are_rejected(): void
    {
        $type = LeaveType::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Annual',
            'code' => 'ANNUAL',
            'max_days_per_year' => 5,
            'requires_approval' => true,
        ]);

        $first = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-07-06',
                'end_date' => '2026-07-10',
            ])->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-07-09',
                'end_date' => '2026-07-14',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests/{$first->json('data.id')}/approve")
            ->assertOk();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-07-13',
                'end_date' => '2026-07-17',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['leave_type_id']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-08-10',
                'end_date' => '2026-08-08',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-08-08',
                'end_date' => '2026-08-09',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_leave_type_without_approval_is_auto_approved(): void
    {
        $type = LeaveType::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Compassionate',
            'code' => 'COMP',
            'requires_approval' => false,
        ]);

        $this->withAuthToken($this->hrManager)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-02',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decision_note', 'Auto-approved by leave type configuration.');
    }

    public function test_attendance_is_upserted_per_employee_and_date(): void
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'work_date' => '2026-06-01',
            'status' => 'present',
            'scheduled_minutes' => 480,
            'worked_minutes' => 470,
        ];

        $first = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/attendance", $payload)
            ->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/attendance", [
                ...$payload,
                'status' => 'late',
                'worked_minutes' => 430,
                'overtime_minutes' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.status', 'late');

        $this->assertSame(1, $this->branchA->attendanceRecords()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.updated']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/attendance", [
                ...$payload,
                'work_date' => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['work_date']);
    }

    public function test_disciplinary_records_require_sensitive_permission_and_enforce_transitions(): void
    {
        $this->withAuthToken($this->hrViewer)
            ->getJson("/api/branches/{$this->branchA->id}/disciplinary-records")
            ->assertForbidden();

        $created = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/disciplinary-records", [
                'employee_id' => $this->employee->id,
                'incident_date' => '2026-06-01',
                'category' => 'conduct',
                'severity' => 'written',
                'summary' => 'Late repeated times',
                'details' => 'Internal detail',
                'action_taken' => 'Verbal warning already given',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $id = $created->json('data.id');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/disciplinary-records/{$id}/transition", ['status' => 'appealed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/disciplinary-records/{$id}/transition", [
                'status' => 'resolved',
                'resolution_note' => 'Resolved with employee',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/disciplinary-records/{$id}/transition", [
                'status' => 'closed',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/disciplinary-records/{$id}", ['summary' => 'Changed'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('disciplinary_records', [
            'id' => $id,
            'status' => DisciplinaryRecord::STATUS_CLOSED,
        ]);
    }

    public function test_hr_attachments_are_private_safe_and_permission_checked(): void
    {
        Storage::fake('local');

        $leave = LeaveRequest::create([
            'branch_id' => $this->branchA->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => LeaveType::create([
                'branch_id' => $this->branchA->id,
                'name' => 'Sick',
                'code' => 'SICK',
            ])->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'days_requested' => 2,
            'status' => 'submitted',
        ]);

        $upload = $this->withAuthToken($this->hrManager)
            ->post("/api/branches/{$this->branchA->id}/hr/attachments", [
                'file' => UploadedFile::fake()->create('medical-note.pdf', 10, 'application/pdf'),
                'attachable_type' => HrAttachment::TYPE_LEAVE_REQUEST,
                'attachable_id' => $leave->id,
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $attachment = HrAttachment::firstOrFail();
        $this->assertSame('medical-note.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);

        $this->withAuthToken($this->hrViewer)
            ->get("/api/branches/{$this->branchA->id}/hr/attachments/{$attachment->id}/download")
            ->assertOk();

        $this->withAuthToken($this->hrManager)
            ->post("/api/branches/{$this->branchA->id}/hr/attachments", [
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
                'attachable_type' => HrAttachment::TYPE_LEAVE_REQUEST,
                'attachable_id' => $leave->id,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $disciplinary = DisciplinaryRecord::create([
            'branch_id' => $this->branchA->id,
            'employee_id' => $this->employee->id,
            'incident_date' => '2026-10-01',
            'category' => 'conduct',
            'severity' => 'verbal',
            'summary' => 'Test',
            'status' => 'open',
        ]);

        $this->withAuthToken($this->hrManager)
            ->post("/api/branches/{$this->branchA->id}/hr/attachments", [
                'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
                'attachable_type' => HrAttachment::TYPE_DISCIPLINARY_RECORD,
                'attachable_id' => $disciplinary->id,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachable_type']);
    }

    public function test_cross_branch_leave_and_attendance_data_is_isolated(): void
    {
        $typeB = LeaveType::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Annual B',
            'code' => 'ANNUAL',
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/leave-requests")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/leave-requests", [
                'employee_id' => $this->employeeB->id,
                'leave_type_id' => $typeB->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-02',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'leave_type_id']);

        $recordB = $this->branchB->attendanceRecords()->create([
            'employee_id' => $this->employeeB->id,
            'work_date' => '2026-06-01',
            'status' => 'present',
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/attendance")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertNotNull($recordB->id);
    }

    private function employee(Branch $branch, string $number, string $name): Employee
    {
        [$first, $last] = explode(' ', $name, 2);

        return Employee::create([
            'branch_id' => $branch->id,
            'employee_number' => $number,
            'first_name' => $first,
            'last_name' => $last,
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);
    }
}
