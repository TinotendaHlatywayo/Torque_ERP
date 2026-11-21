<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $hrViewer;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'HRA');
        $this->branchB = $this->branch($this->platform, 'HRB');

        $this->admin = $this->user('HR Admin');
        $this->hrViewer = $this->user('HR Viewer');
        $this->adminB = $this->user('HR B Admin');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->hrViewer, 'viewer', true, ['branch.hr.view']);
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_employee_can_be_created_with_a_generated_number_and_listed(): void
    {
        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees", [
                'first_name' => 'Tendai',
                'last_name' => 'Moyo',
                'email' => 'Tendai@Example.com',
                'job_title' => 'Storekeeper',
                'department' => 'Stores',
                'hire_date' => '2026-01-15',
                'status' => 'active',
                'national_id' => '63-123456-A-00',
                'date_of_birth' => '1990-05-04',
                'address' => '12 Main Street Rusape',
                'emergency_contact_name' => 'Rudo Moyo',
                'emergency_contact_phone' => '0771000000',
                'notes' => 'Internal note',
            ])
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Tendai')
            ->assertJsonPath('data.full_name', 'Tendai Moyo')
            ->assertJsonPath('data.email', 'tendai@example.com')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.sensitive_redacted', false)
            ->assertJsonPath('data.sensitive.national_id', '63-123456-A-00');

        $id = $response->json('data.id');

        $this->assertStringStartsWith('EMP-HRA-', $response->json('data.employee_number'));
        $this->assertDatabaseHas('employees', [
            'id' => $id,
            'branch_id' => $this->branchA->id,
            'email' => 'tendai@example.com',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'employee.created',
            'entity_id' => $id,
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/employees")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_sensitive_fields_require_the_sensitive_permission(): void
    {
        $employee = Employee::create([
            'branch_id' => $this->branchA->id,
            'employee_number' => 'EMP-001',
            'first_name' => 'Sensitive',
            'last_name' => 'Person',
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'national_id' => 'SECRET-ID',
            'address' => 'SECRET-ADDRESS',
            'notes' => 'SECRET-NOTE',
        ]);

        $this->withAuthToken($this->hrViewer)
            ->getJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.sensitive_redacted', true)
            ->assertJsonPath('data.sensitive.national_id', null)
            ->assertJsonPath('data.sensitive.address', null)
            ->assertJsonPath('data.sensitive.notes', null);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'national_id' => 'SECRET-ID',
        ]);
    }

    public function test_employee_and_user_lifecycles_are_separate_until_explicitly_linked(): void
    {
        $employee = Employee::create([
            'branch_id' => $this->branchA->id,
            'employee_number' => 'EMP-002',
            'first_name' => 'Unlinked',
            'last_name' => 'Employee',
            'hire_date' => '2026-02-01',
            'status' => 'hired',
        ]);

        $this->assertNull($employee->user_id);
        $this->assertDatabaseHas('users', ['email' => $this->admin->email]);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}", [
                'user_id' => $this->hrViewer->id,
                'job_title' => 'Clerk',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->hrViewer->id)
            ->assertJsonPath('data.job_title', 'Clerk');

        $this->assertTrue($this->hrViewer->fresh()->is_active);
        $this->assertTrue($this->hrViewer->fresh()->isMemberOfBranch($this->branchA->id));
    }

    public function test_lifecycle_transitions_are_enforced_and_archiving_requires_termination(): void
    {
        $employee = Employee::create([
            'branch_id' => $this->branchA->id,
            'employee_number' => 'EMP-003',
            'first_name' => 'Lifecycle',
            'last_name' => 'Tester',
            'hire_date' => '2026-03-01',
            'status' => 'hired',
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", ['status' => 'archived'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", ['status' => 'suspended', 'reason' => 'Investigation'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", [
                'status' => 'terminated',
                'effective_date' => '2026-04-30',
                'reason' => 'Resigned',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'terminated')
            ->assertJsonPath('data.termination_date', '2026-04-30');

        $this->withAuthToken($this->admin)
            ->deleteJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => 'archived',
        ]);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}", ['job_title' => 'Changed'])
            ->assertUnprocessable();
    }

    public function test_termination_revokes_the_linked_users_branch_access_and_tokens(): void
    {
        $cashier = $this->user('Linked Cashier');
        $this->membership($this->branchA, $cashier, 'cashier');
        $cashierToken = $this->tokenFor($cashier);

        $employee = Employee::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $cashier->id,
            'employee_number' => 'EMP-004',
            'first_name' => 'Linked',
            'last_name' => 'Cashier',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", [
                'status' => 'terminated',
                'effective_date' => '2026-05-01',
            ])
            ->assertOk();

        $this->assertDatabaseHas('branch_users', [
            'branch_id' => $this->branchA->id,
            'user_id' => $cashier->id,
            'is_active' => false,
        ]);
        $this->assertSame(0, $cashier->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'employee.access_revoked',
        ]);

        $this->withRawToken($cashierToken)
            ->getJson("/api/branches/{$this->branchA->id}/employees")
            ->assertUnauthorized();

        $this->assertTrue($cashier->fresh()->is_active);
    }

    public function test_terminating_the_last_linked_admin_is_blocked_until_another_admin_exists(): void
    {
        $employee = Employee::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->admin->id,
            'employee_number' => 'EMP-005',
            'first_name' => 'Only',
            'last_name' => 'Admin',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", [
                'status' => 'terminated',
                'revoke_access' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['revoke_access']);

        $secondAdmin = $this->user('Second Admin');
        $this->membership($this->branchA, $secondAdmin, 'admin');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", [
                'status' => 'terminated',
                'revoke_access' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('branch_users', [
            'branch_id' => $this->branchA->id,
            'user_id' => $this->admin->id,
            'is_active' => false,
        ]);
    }

    public function test_employee_numbers_and_linked_users_are_unique_within_a_branch(): void
    {
        Employee::create([
            'branch_id' => $this->branchA->id,
            'employee_number' => 'EMP-100',
            'first_name' => 'First',
            'last_name' => 'Employee',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees", [
                'employee_number' => 'EMP-100',
                'first_name' => 'Duplicate',
                'last_name' => 'Number',
                'hire_date' => '2026-01-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_number']);

        $linked = Employee::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->hrViewer->id,
            'employee_number' => 'EMP-101',
            'first_name' => 'Linked',
            'last_name' => 'User',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->assertNotNull($linked->user_id);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees", [
                'user_id' => $this->hrViewer->id,
                'first_name' => 'Second',
                'last_name' => 'Link',
                'hire_date' => '2026-01-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cross_branch_employees_are_not_readable_or_mutable(): void
    {
        $employeeB = Employee::create([
            'branch_id' => $this->branchB->id,
            'employee_number' => 'EMP-B-1',
            'first_name' => 'Branch',
            'last_name' => 'B',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/employees")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/employees/{$employeeB->id}")
            ->assertNotFound();

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/employees/{$employeeB->id}", ['job_title' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('employees', [
            'id' => $employeeB->id,
            'job_title' => null,
        ]);
    }

    public function test_hr_permissions_gate_view_and_manage_actions(): void
    {
        $employee = Employee::create([
            'branch_id' => $this->branchA->id,
            'employee_number' => 'EMP-006',
            'first_name' => 'Permission',
            'last_name' => 'Check',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $cashier = $this->user('No HR Cashier');
        $this->membership($this->branchA, $cashier, 'cashier');

        $this->withAuthToken($cashier)
            ->getJson("/api/branches/{$this->branchA->id}/employees")
            ->assertForbidden();

        $this->withAuthToken($this->hrViewer)
            ->getJson("/api/branches/{$this->branchA->id}/employees")
            ->assertOk();

        $this->withAuthToken($this->hrViewer)
            ->postJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}/transition", ['status' => 'suspended'])
            ->assertForbidden();

        $this->withAuthToken($this->hrViewer)
            ->patchJson("/api/branches/{$this->branchA->id}/employees/{$employee->id}", ['job_title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_validation_rejects_bad_dates_and_statuses(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/employees", [
                'first_name' => '',
                'last_name' => '',
                'hire_date' => '2026-05-01',
                'termination_date' => '2026-04-01',
                'status' => 'retired',
                'employment_type' => 'gig',
                'date_of_birth' => '2030-01-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'termination_date',
                'status',
                'employment_type',
                'date_of_birth',
            ]);
    }
}
