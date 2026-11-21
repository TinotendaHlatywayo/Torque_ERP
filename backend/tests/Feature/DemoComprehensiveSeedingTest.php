<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DemoController;
use App\Models\Branch;
use App\Models\ReportExport;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoComprehensiveSeedingTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin@example.test';

    private const ADMIN_PASSWORD = 'secret123';

    public function test_seed_populates_all_modules_is_idempotent_and_wipe_is_branch_scoped(): void
    {
        config([
            'seeding.admin_email' => self::ADMIN_EMAIL,
            'seeding.admin_password' => self::ADMIN_PASSWORD,
        ]);

        $this->seed(DatabaseSeeder::class);

        $branch = Branch::where('code', 'TMS-01')->firstOrFail();
        $tables = $this->seededTables();
        $admin = User::where('email', self::ADMIN_EMAIL)->firstOrFail();

        $platformExport = ReportExport::create([
            'scope_type' => 'platform',
            'scope_id' => (string) Str::ulid(),
            'report_type' => 'branch_comparison',
            'file_path' => 'private/exports/platform-demo-wipe.csv',
            'expires_at' => now()->addDay(),
            'created_by' => $admin->id,
        ]);

        app(DemoController::class)->seed();

        $counts = $this->branchCounts($branch, $tables);
        $payslipLines = $this->payslipLineCount($branch);

        foreach ($counts as $table => $count) {
            $this->assertGreaterThan(0, $count, $table);
        }

        $this->assertGreaterThan(0, $payslipLines, 'payslip_lines');

        $this->assertSame(4, User::whereIn('email', $this->demoEmails($branch))->count());
        $this->assertSame(1, DB::table('cms_pages')->where('slug', 'demo-store-guide')->count());
        $this->assertSame(1, DB::table('settings')
            ->where('scope_type', 'branch')
            ->where('scope_id', $branch->id)
            ->where('key', 'registration_notes')
            ->count());
        $this->assertSame(1, DB::table('stock_transfers')
            ->where('source_branch_id', $branch->id)
            ->count());
        $this->assertDashboardIsClean($branch);

        $demoManager = User::where('email', array_values($this->demoEmails($branch))[0])->firstOrFail();
        $demoExport = ReportExport::create([
            'scope_type' => 'branch',
            'scope_id' => $branch->id,
            'report_type' => 'stock_valuation',
            'file_path' => 'private/exports/demo-manager-export.csv',
            'expires_at' => now()->addDay(),
            'created_by' => $demoManager->id,
        ]);

        app(DemoController::class)->seed();

        $this->assertSame($counts, $this->branchCounts($branch, $tables));
        $this->assertSame($payslipLines, $this->payslipLineCount($branch));
        $this->assertSame(4, User::whereIn('email', $this->demoEmails($branch))->count());
        $this->assertSame(1, DB::table('cms_pages')->where('slug', 'demo-store-guide')->count());
        $this->assertSame(1, DB::table('stock_transfers')
            ->where('source_branch_id', $branch->id)
            ->count());
        $this->assertDatabaseHas('report_exports', ['id' => $platformExport->id]);
        $this->assertDatabaseHas('report_exports', ['id' => $demoExport->id]);
        $this->assertDashboardIsClean($branch);

        app(DemoController::class)->wipe();

        foreach ($tables as $table) {
            $this->assertSame(0, DB::table($table)->where('branch_id', $branch->id)->count(), $table);
        }

        $this->assertSame(0, $this->payslipLineCount($branch));
        $this->assertSame(0, User::whereIn('email', $this->demoEmails($branch))->count());
        $this->assertSame(0, DB::table('cms_pages')->where('slug', 'demo-store-guide')->count());
        $this->assertSame(0, DB::table('settings')
            ->where('scope_type', 'branch')
            ->where('scope_id', $branch->id)
            ->count());
        $this->assertSame(0, DB::table('stock_transfers')
            ->where('source_branch_id', $branch->id)
            ->count());
        $this->assertDatabaseHas('report_exports', ['id' => $platformExport->id]);
        $this->assertDatabaseMissing('report_exports', ['id' => $demoExport->id]);

        $this->assertSame(0, Branch::withTrashed()->where('code', 'DEMO-DST-'.$branch->code)->count());
        $mutare = Branch::where('code', 'TMS-02')->firstOrFail();
        $mutareDestinationId = Branch::withTrashed()->where('code', 'DEMO-DST-TMS-02')->value('id');
        $this->assertSame(0, DB::table('products')
            ->where('sku', 'DEMO-RECV-001')
            ->whereNotIn('branch_id', array_filter([$mutare->id, $mutareDestinationId]))
            ->count());
        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    private function assertDashboardIsClean(Branch $branch): void
    {
        $admin = User::where('email', self::ADMIN_EMAIL)->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $data = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/branches/{$branch->id}/dashboard/v2")
            ->assertOk()
            ->json('data');

        $this->assertSame('PASS', $data['data_quality']['status']);
        $this->assertSame([], $data['data_quality']['warnings']);
        $this->assertNotNull($data['cash_forecast']['forecast_cash_position_minor']);
    }

    private function branchCounts(Branch $branch, array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->where('branch_id', $branch->id)->count();
        }

        return $counts;
    }

    private function payslipLineCount(Branch $branch): int
    {
        return DB::table('payslip_lines')
            ->whereIn('payslip_id', DB::table('payslips')->where('branch_id', $branch->id)->pluck('id'))
            ->count();
    }

    private function seededTables(): array
    {
        return [
            'stock_levels',
            'stock_movements',
            'payment_accounts',
            'money_movements',
            'sales',
            'sale_items',
            'payments',
            'sale_returns',
            'sale_return_items',
            'expenses',
            'supplier_bills',
            'supplier_bill_payments',
            'reservations',
            'workshop_services',
            'service_jobs',
            'stocktakes',
            'stocktake_lines',
            'assets',
            'asset_maintenances',
            'salary_grades',
            'leave_types',
            'payroll_statutory_rules',
            'employees',
            'employee_salary_assignments',
            'employee_pay_components',
            'attendance_records',
            'leave_requests',
            'disciplinary_records',
            'hr_attachments',
            'payroll_periods',
            'payroll_runs',
            'payslips',
            'payroll_accruals',
            'payroll_payments',
            'loyalty_configs',
            'loyalty_sale_events',
            'loyalty_qualifications',
            'tasks',
            'notification_deliveries',
            'products',
            'customers',
            'categories',
            'units',
        ];
    }

    private function demoEmails(Branch $branch): array
    {
        $slug = Str::slug($branch->code);

        return [
            'manager@demo-'.$slug.'.test',
            'cashier@demo-'.$slug.'.test',
            'storekeeper@demo-'.$slug.'.test',
            'accountant@demo-'.$slug.'.test',
        ];
    }
}
