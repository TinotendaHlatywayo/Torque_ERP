<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BranchRoleController;
use App\Http\Controllers\Api\BranchUserController;
use App\Http\Controllers\Api\CatalogueImportController;
use App\Http\Controllers\Api\CatalogueSearchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CmsContentController;
use App\Http\Controllers\Api\CmsSiteContentController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardMetricsController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\DisciplinaryRecordController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\DocumentVerificationController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeLoanController;
use App\Http\Controllers\Api\EmployeePayComponentController;
use App\Http\Controllers\Api\EmployeeSalaryAssignmentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceMetricsController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HrAttachmentController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MoneyMovementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\PaynowResultController;
use App\Http\Controllers\Api\PayrollAdjustmentController;
use App\Http\Controllers\Api\PayrollEmployeeController;
use App\Http\Controllers\Api\PayrollPaymentController;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Controllers\Api\PayrollReconciliationController;
use App\Http\Controllers\Api\PayrollRunController;
use App\Http\Controllers\Api\PayrollStatutoryRuleController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\PlatformAccessController;
use App\Http\Controllers\Api\PlatformBranchController;
use App\Http\Controllers\Api\PlatformReportController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductConversionController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\PublicSiteController;
use App\Http\Controllers\Api\PublicStorefrontController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StocktakeController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\SupplierBillController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\WorkshopServiceController;
use App\Http\Middleware\PublicReservationHeaders;
use App\Models\Branch;
use App\Models\PaymentAccount;
use App\Models\SaleReturn;
use App\Support\SaleReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);
Route::get('/verify/{documentType}/{documentId}', [DocumentVerificationController::class, 'verify']);
Route::post('/paynow/result', PaynowResultController::class)->middleware('throttle:60,1')->name('paynow.result');

Route::prefix('/public')->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/site', [PublicSiteController::class, 'site'])->name('public.site');
        Route::get('/products', [PublicSiteController::class, 'products'])->name('public.site.products');
        Route::get('/products/{publicSlug}', [PublicSiteController::class, 'product'])
            ->where('publicSlug', '[a-z0-9-]+')->name('public.site.product');
        Route::get('/pages/{slug}', [PublicSiteController::class, 'page'])
            ->where('slug', '[a-z0-9-]+')->name('public.site.page');
    });

    Route::middleware([PublicReservationHeaders::class, 'throttle:10,1'])->group(function () {
        Route::post('/reservations/start', [PublicSiteController::class, 'startReservation'])->name('public.site.reservation.start');
        Route::post('/reservations', [PublicSiteController::class, 'createReservation'])->name('public.site.reservation.store');
        Route::post('/reservations/{token}/cancel', [PublicSiteController::class, 'cancel'])
            ->where('token', '[a-f0-9]{64}')->name('public.site.reservation.cancel');
    });

    Route::get('/reservations/{token}', [PublicSiteController::class, 'status'])
        ->middleware([PublicReservationHeaders::class, 'throttle:30,1'])
        ->where('token', '[a-f0-9]{64}')->name('public.site.reservation.status');

    Route::post('/register', [PublicStorefrontController::class, 'register'])
        ->middleware('throttle:5,1')->name('public.storefront.register');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/account', [PublicStorefrontController::class, 'account'])->name('public.storefront.account');
        Route::post('/logout', [PublicStorefrontController::class, 'logout'])->name('public.storefront.logout');
        Route::post('/checkout', [PublicStorefrontController::class, 'checkout'])
            ->middleware('throttle:10,1')->name('public.storefront.checkout');
    });
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', MeController::class);

    Route::prefix('branches/{branch}')
        ->middleware('branch.context')
        ->scopeBindings()
        ->group(function () {
            Route::post('/demo/seed', [DemoController::class, 'seed'])
                ->middleware('platform.permission:platform.settings.manage');
            Route::post('/demo/wipe', [DemoController::class, 'wipe'])
                ->middleware('platform.permission:platform.settings.manage');
            Route::get('/users', [BranchUserController::class, 'index'])
                ->middleware('branch.permission:branch.users.view');
            Route::post('/users', [BranchUserController::class, 'store'])
                ->middleware('branch.permission:branch.users.manage');
            Route::patch('/users/{membership}', [BranchUserController::class, 'update'])
                ->middleware('branch.permission:branch.users.manage');
            Route::delete('/users/{membership}', [BranchUserController::class, 'destroy'])
                ->middleware('branch.permission:branch.users.manage');
            Route::get('/roles', [BranchRoleController::class, 'index'])
                ->middleware('branch.permission:branch.roles.view');

            Route::get('/categories', [CategoryController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::post('/categories', [CategoryController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::patch('/categories/{category}', [CategoryController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::get('/units', [UnitController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::post('/units', [UnitController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::patch('/units/{unit}', [UnitController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/units/{unit}', [UnitController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::get('/products', [ProductController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::post('/products', [ProductController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::get('/products/{product}', [ProductController::class, 'show'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::patch('/products/{product}', [ProductController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::post('/products/{product}/units', [ProductUnitController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::patch('/products/{product}/units/{productUnit}', [ProductUnitController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/products/{product}/units/{productUnit}', [ProductUnitController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::post('/products/{product}/convert', ProductConversionController::class)
                ->middleware('branch.permission:branch.inventory.view');

            Route::post('/checkout', [CheckoutController::class, 'store'])
                ->middleware('branch.permission:branch.sales.create');
            Route::get('/sales/{sale}', [CheckoutController::class, 'show'])
                ->middleware('branch.permission:branch.sales.view');
            Route::post('/sales/{sale}/payments', [SaleController::class, 'collectPayment'])
                ->middleware('branch.permission:branch.sales.create');
            Route::get('/receivables', [SaleController::class, 'receivables'])
                ->middleware('branch.permission:branch.sales.view');
            Route::get('/receivables/ageing', [SaleController::class, 'receivablesAgeing'])
                ->middleware('branch.permission:branch.sales.view');
            Route::get('/sales', [SaleController::class, 'index'])
                ->middleware('branch.permission:branch.sales.view');
            Route::get('/reservations', [ReservationController::class, 'index'])
                ->middleware('branch.permission:branch.reservations.view');
            Route::post('/sales/{sale}/void', [SaleController::class, 'void'])
                ->middleware('branch.permission:branch.sales.void');
            Route::get('/returns', [SaleController::class, 'returnsIndex'])
                ->middleware('branch.permission:branch.sales.view');
            Route::post('/sales/{sale}/returns', [SaleController::class, 'storeReturn'])
                ->middleware('branch.permission:branch.sales.void');
            Route::get('/returns/{saleReturn}', [SaleController::class, 'showReturn'])
                ->middleware('branch.permission:branch.sales.view');
            Route::post('/returns/{saleReturn}/approve', function (Request $request, Branch $branch, SaleReturn $saleReturn, SaleReturnService $returns): JsonResponse {
                $data = $request->validate([
                    'payment_account_id' => ['required', 'string'],
                ]);
                $account = PaymentAccount::where('branch_id', $branch->id)->whereKey($data['payment_account_id'])->firstOrFail();
                $saleReturn = $returns->approve($branch, $saleReturn, $account, $request->user());
                $saleReturn->load(['sale:id,sale_number', 'items']);

                return response()->json(['data' => [
                    'id' => $saleReturn->id,
                    'return_number' => $saleReturn->return_number,
                    'sale_id' => $saleReturn->sale_id,
                    'sale_number' => $saleReturn->sale?->sale_number,
                    'status' => $saleReturn->status,
                    'restock_decision' => $saleReturn->restock_decision,
                    'refund_status' => $saleReturn->refund_status,
                    'currency_code' => $saleReturn->currency_code,
                    'total_minor' => $saleReturn->total_minor,
                    'items' => $saleReturn->items->map(fn ($item): array => [
                        'id' => $item->id,
                        'sale_item_id' => $item->sale_item_id,
                        'product_id' => $item->product_id,
                        'quantity' => (string) $item->quantity,
                        'amount_minor' => $item->amount_minor,
                        'currency_code' => $item->currency_code,
                    ])->all(),
                ]]);
            })->middleware('branch.permission:branch.sales.void');
            Route::get('/employees', [EmployeeController::class, 'index'])
                ->middleware('branch.permission:branch.hr.view');
            Route::post('/employees', [EmployeeController::class, 'store'])
                ->middleware('branch.permission:branch.hr.manage');
            Route::get('/employees/linkable-users', [EmployeeController::class, 'linkableUsers'])
                ->middleware('branch.permission:branch.hr.manage');
            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
                ->middleware('branch.permission:branch.hr.view');
            Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])
                ->middleware('branch.permission:branch.hr.manage');
            Route::post('/employees/{employee}/transition', [EmployeeController::class, 'transition'])
                ->middleware('branch.permission:branch.hr.manage');
            Route::delete('/employees/{employee}', [EmployeeController::class, 'archive'])
                ->middleware('branch.permission:branch.hr.manage');

            Route::get('/leave-types', [LeaveTypeController::class, 'index'])
                ->middleware('branch.permission:branch.hr.view');
            Route::post('/leave-types', [LeaveTypeController::class, 'store'])
                ->middleware('branch.permission:branch.hr.manage');
            Route::patch('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])
                ->middleware('branch.permission:branch.hr.manage');

            Route::get('/leave-requests', [LeaveRequestController::class, 'index'])
                ->middleware('branch.permission:branch.hr.view');
            Route::post('/leave-requests', [LeaveRequestController::class, 'store'])
                ->middleware('branch.permission:branch.hr.manage');
            Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])
                ->middleware('branch.permission:branch.hr.view');
            Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])
                ->middleware('branch.permission:branch.hr.leave.approve');
            Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])
                ->middleware('branch.permission:branch.hr.leave.approve');
            Route::post('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])
                ->middleware('branch.permission:branch.hr.manage');

            Route::get('/attendance', [AttendanceController::class, 'index'])
                ->middleware('branch.permission:branch.hr.view');
            Route::post('/attendance', [AttendanceController::class, 'store'])
                ->middleware('branch.permission:branch.hr.manage');

            Route::get('/disciplinary-records', [DisciplinaryRecordController::class, 'index'])
                ->middleware('branch.permission:branch.hr.sensitive.view');
            Route::post('/disciplinary-records', [DisciplinaryRecordController::class, 'store'])
                ->middleware(['branch.permission:branch.hr.manage', 'branch.permission:branch.hr.sensitive.view']);
            Route::get('/disciplinary-records/{disciplinaryRecord}', [DisciplinaryRecordController::class, 'show'])
                ->middleware('branch.permission:branch.hr.sensitive.view');
            Route::patch('/disciplinary-records/{disciplinaryRecord}', [DisciplinaryRecordController::class, 'update'])
                ->middleware(['branch.permission:branch.hr.manage', 'branch.permission:branch.hr.sensitive.view']);
            Route::post('/disciplinary-records/{disciplinaryRecord}/transition', [DisciplinaryRecordController::class, 'transition'])
                ->middleware(['branch.permission:branch.hr.manage', 'branch.permission:branch.hr.sensitive.view']);

            Route::post('/hr/attachments', [HrAttachmentController::class, 'store'])
                ->middleware('branch.permission:branch.hr.manage');
            Route::get('/hr/attachments/{hrAttachment}/download', [HrAttachmentController::class, 'download'])
                ->middleware('branch.permission:branch.hr.view');

            Route::get('/payroll-employees', [PayrollEmployeeController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');

            Route::get('/salary-grades', [SalaryGradeController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/salary-grades', [SalaryGradeController::class, 'store'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::patch('/salary-grades/{salaryGrade}', [SalaryGradeController::class, 'update'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/employee-salary-assignments', [EmployeeSalaryAssignmentController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/employee-salary-assignments', [EmployeeSalaryAssignmentController::class, 'store'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::patch('/employee-salary-assignments/{employeeSalaryAssignment}', [EmployeeSalaryAssignmentController::class, 'update'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/employee-pay-components', [EmployeePayComponentController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/employee-pay-components', [EmployeePayComponentController::class, 'store'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::patch('/employee-pay-components/{employeePayComponent}', [EmployeePayComponentController::class, 'update'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::delete('/employee-pay-components/{employeePayComponent}', [EmployeePayComponentController::class, 'destroy'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/payroll-statutory-rules', [PayrollStatutoryRuleController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/payroll-statutory-rules', [PayrollStatutoryRuleController::class, 'store'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::patch('/payroll-statutory-rules/{payrollStatutoryRule}', [PayrollStatutoryRuleController::class, 'update'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/employee-loans', [EmployeeLoanController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/employee-loans', [EmployeeLoanController::class, 'store'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::get('/employee-loans/{employeeLoan}', [EmployeeLoanController::class, 'show'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::patch('/employee-loans/{employeeLoan}', [EmployeeLoanController::class, 'update'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/payroll-periods', [PayrollPeriodController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/payroll-periods', [PayrollPeriodController::class, 'store'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::get('/payroll-periods/{payrollPeriod}', [PayrollPeriodController::class, 'show'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::get('/payroll-periods/{payrollPeriod}/runs', [PayrollRunController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/payroll-periods/{payrollPeriod}/run', [PayrollRunController::class, 'generate'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/payroll-runs/{payrollRun}/approve', [PayrollRunController::class, 'approve'])
                ->middleware('branch.permission:branch.payroll.approve');
            Route::get('/payroll-runs/{payrollRun}/payments', [PayrollPaymentController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/payroll-runs/{payrollRun}/payments', [PayrollPaymentController::class, 'store'])
                ->middleware('branch.permission:branch.payroll.manage');
            Route::get('/payroll-runs/{payrollRun}/reconciliation', [PayrollReconciliationController::class, 'show'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::get('/payroll-runs/{payrollRun}/payslips/export', [PayrollReconciliationController::class, 'export'])
                ->middleware('branch.permission:branch.payroll.export');

            Route::get('/payslips/{payslip}', [PayslipController::class, 'show'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/payslips/{payslip}/adjustments', [PayslipController::class, 'adjustment'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/payroll-adjustments', [PayrollAdjustmentController::class, 'index'])
                ->middleware('branch.permission:branch.payroll.view');
            Route::post('/payroll-adjustments/{payrollAdjustment}/reverse', [PayrollAdjustmentController::class, 'reverse'])
                ->middleware('branch.permission:branch.payroll.manage');

            Route::get('/tasks', [TaskController::class, 'index'])
                ->middleware('branch.permission:branch.tasks.view');
            Route::post('/tasks', [TaskController::class, 'store'])
                ->middleware('branch.permission:branch.tasks.manage');
            Route::get('/tasks/{task}', [TaskController::class, 'show'])
                ->middleware('branch.permission:branch.tasks.view');
            Route::patch('/tasks/{task}', [TaskController::class, 'update'])
                ->middleware('branch.permission:branch.tasks.manage');
            Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])
                ->middleware('branch.permission:branch.tasks.manage');
            Route::post('/tasks/{task}/cancel', [TaskController::class, 'cancel'])
                ->middleware('branch.permission:branch.tasks.manage');

            Route::get('/notifications', [NotificationController::class, 'index'])
                ->middleware('branch.permission:branch.tasks.view');
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
                ->middleware('branch.permission:branch.tasks.view');
            Route::get('/notification-preferences', [NotificationController::class, 'preferences'])
                ->middleware('branch.permission:branch.tasks.view');
            Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences'])
                ->middleware('branch.permission:branch.tasks.view');

            Route::get('/customers', [CustomerController::class, 'index'])
                ->middleware('branch.permission:branch.customers.view');
            Route::post('/customers', [CustomerController::class, 'store'])
                ->middleware('branch.permission:branch.customers.manage');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])
                ->middleware('branch.permission:branch.customers.view');
            Route::patch('/customers/{customer}', [CustomerController::class, 'update'])
                ->middleware('branch.permission:branch.customers.manage');
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
                ->middleware('branch.permission:branch.customers.manage');

            Route::get('/loyalty/config', [LoyaltyController::class, 'config'])
                ->middleware('branch.permission:branch.loyalty.view');
            Route::put('/loyalty/config', [LoyaltyController::class, 'updateConfig'])
                ->middleware('branch.permission:branch.loyalty.manage');
            Route::get('/loyalty/qualifications', [LoyaltyController::class, 'qualifications'])
                ->middleware('branch.permission:branch.loyalty.view');
            Route::post('/loyalty/recalculate', [LoyaltyController::class, 'recalculate'])
                ->middleware('branch.permission:branch.loyalty.manage');
            Route::post('/loyalty/sale-events', [LoyaltyController::class, 'recordSaleEvent'])
                ->middleware('branch.permission:branch.loyalty.manage');
            Route::get('/loyalty/winback/candidates', [LoyaltyController::class, 'winbackCandidates'])
                ->middleware('branch.permission:branch.loyalty.view');
            Route::post('/loyalty/winback/export', [LoyaltyController::class, 'winbackExport'])
                ->middleware('branch.permission:branch.loyalty.view');
            Route::post('/loyalty/discount/quote', [LoyaltyController::class, 'discountQuote'])
                ->middleware('branch.permission:branch.loyalty.view');

            Route::get('/stock/levels', StockLevelController::class)
                ->middleware('branch.permission:branch.inventory.view');
            Route::get('/stock/movements', [StockMovementController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');

            Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])
                ->middleware('branch.permission:branch.finance.view');
            Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])
                ->middleware('branch.permission:branch.finance.manage');
            Route::patch('/payment-accounts/{paymentAccount}', [PaymentAccountController::class, 'update'])
                ->middleware('branch.permission:branch.finance.manage');

            Route::get('/money-movements', [MoneyMovementController::class, 'index'])
                ->middleware('branch.permission:branch.finance.view');
            Route::post('/payment-accounts/{paymentAccount}/movements', [MoneyMovementController::class, 'store'])
                ->middleware('branch.permission:branch.finance.manage');
            Route::post('/transfers', [MoneyMovementController::class, 'transfer'])
                ->middleware('branch.permission:branch.finance.manage');
            Route::post('/money-movements/{moneyMovement}/reverse', [MoneyMovementController::class, 'reverse'])
                ->middleware('branch.permission:branch.finance.manage');

            Route::get('/expenses', [ExpenseController::class, 'index'])
                ->middleware('branch.permission:branch.finance.view');
            Route::post('/expenses', [ExpenseController::class, 'store'])
                ->middleware('branch.permission:branch.finance.manage');
            Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay'])
                ->middleware('branch.permission:branch.finance.manage');

            Route::get('/supplier-bills', [SupplierBillController::class, 'index'])
                ->middleware('branch.permission:branch.finance.view');
            Route::post('/supplier-bills', [SupplierBillController::class, 'store'])
                ->middleware('branch.permission:branch.finance.manage');
            Route::post('/supplier-bills/{supplierBill}/pay', [SupplierBillController::class, 'pay'])
                ->middleware('branch.permission:branch.finance.manage');
            Route::get('/bills-to-pay/ageing', [SupplierBillController::class, 'ageing'])
                ->middleware('branch.permission:branch.finance.view');

            Route::get('/assets', [AssetController::class, 'index'])
                ->middleware('branch.permission:branch.assets.view');
            Route::post('/assets', [AssetController::class, 'store'])
                ->middleware('branch.permission:branch.assets.manage');
            Route::get('/assets/depreciation-estimate', [AssetController::class, 'depreciationEstimate'])
                ->middleware('branch.permission:branch.assets.view');
            Route::get('/assets/{asset}', [AssetController::class, 'show'])
                ->middleware('branch.permission:branch.assets.view');
            Route::get('/assets/{asset}/depreciation-estimate', [AssetController::class, 'assetDepreciationEstimate'])
                ->middleware('branch.permission:branch.assets.view');
            Route::post('/assets/{asset}/maintenances', [AssetController::class, 'storeMaintenance'])
                ->middleware('branch.permission:branch.assets.manage');
            Route::post('/assets/{asset}/dispose', [AssetController::class, 'dispose'])
                ->middleware('branch.permission:branch.assets.dispose');

            Route::get('/finance/metrics', [FinanceMetricsController::class, 'metrics'])
                ->middleware('branch.permission:branch.finance.view');
            Route::get('/dashboard/v2', [DashboardMetricsController::class, 'show']);
            Route::post('/sync/upload', [SyncController::class, 'upload'])
                ->middleware('branch.permission:branch.sales.create');
            Route::get('/sync/status', [SyncController::class, 'status'])
                ->middleware('branch.permission:branch.sales.view');
            Route::get('/settings', [SettingController::class, 'branchIndex'])
                ->middleware('branch.permission:branch.settings.view');
            Route::patch('/settings', [SettingController::class, 'branchUpdate'])
                ->middleware('branch.permission:branch.settings.manage');
            Route::get('/audit-logs', [AuditLogController::class, 'branchIndex'])
                ->middleware('branch.permission:branch.audit.view');
            Route::get('/reports/sales', [ReportController::class, 'sales'])
                ->middleware('branch.permission:branch.reports.view');
            Route::get('/reports/stock', [ReportController::class, 'stock'])
                ->middleware('branch.permission:branch.reports.view');
            Route::get('/reports/receivables', [ReportController::class, 'receivables'])
                ->middleware('branch.permission:branch.reports.view');
            Route::get('/document-templates', [DocumentTemplateController::class, 'index'])
                ->middleware('branch.permission:branch.finance.view');
            Route::post('/document-templates', [DocumentTemplateController::class, 'store'])
                ->middleware('branch.permission:branch.finance.manage');
            Route::post('/document-templates/{documentTemplate}/preview', [DocumentTemplateController::class, 'preview'])
                ->middleware('branch.permission:branch.finance.view');
            Route::post('/finance/fx/convert', [FinanceMetricsController::class, 'convert'])
                ->middleware('branch.permission:branch.finance.view');
            Route::post('/products/{product}/stock/movements', [StockMovementController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::post('/stock/movements/{stockMovement}/reverse', [StockMovementController::class, 'reverse'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::get('/catalogue/search', [CatalogueSearchController::class, 'index'])
                ->middleware('branch.permission:branch.catalogue.view');
            Route::post('/catalogue/import-fitments', [CatalogueImportController::class, 'storeFitments'])
                ->middleware('branch.permission:branch.catalogue.manage');
            Route::post('/catalogue/import-oem', [CatalogueImportController::class, 'storeOemRefs'])
                ->middleware('branch.permission:branch.catalogue.manage');

            Route::get('/services', [WorkshopServiceController::class, 'index'])
                ->middleware('branch.permission:branch.services.view');
            Route::post('/services', [WorkshopServiceController::class, 'store'])
                ->middleware('branch.permission:branch.services.manage');
            Route::patch('/services/{workshopService}', [WorkshopServiceController::class, 'update'])
                ->middleware('branch.permission:branch.services.manage');

            Route::get('/jobs', [JobController::class, 'index'])
                ->middleware('branch.permission:branch.jobs.view');
            Route::post('/jobs', [JobController::class, 'store'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::get('/jobs/{job}', [JobController::class, 'show'])
                ->middleware('branch.permission:branch.jobs.view');
            Route::patch('/jobs/{job}', [JobController::class, 'update'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/labour', [JobController::class, 'addLabour'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/parts', [JobController::class, 'addPart'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/estimate', [JobController::class, 'estimate'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/approve', [JobController::class, 'approve'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/start', [JobController::class, 'start'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/complete', [JobController::class, 'complete'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/cancel', [JobController::class, 'cancel'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/reopen', [JobController::class, 'reopen'])
                ->middleware('branch.permission:branch.jobs.manage');
            Route::post('/jobs/{job}/invoice', [JobController::class, 'linkInvoice'])
                ->middleware('branch.permission:branch.jobs.manage');

            Route::get('/stock-transfers', [StockTransferController::class, 'index'])
                ->middleware('branch.permission:branch.transfers.view');
            Route::post('/stock-transfers', [StockTransferController::class, 'store'])
                ->middleware('branch.permission:branch.transfers.manage');
            Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])
                ->middleware('branch.permission:branch.transfers.view');
            Route::post('/stock-transfers/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])
                ->middleware('branch.permission:branch.transfers.manage');
            Route::post('/stock-transfers/{stockTransfer}/in-transit', [StockTransferController::class, 'inTransit'])
                ->middleware('branch.permission:branch.transfers.manage');
            Route::post('/stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])
                ->middleware('branch.permission:branch.transfers.manage');
            Route::post('/stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])
                ->middleware('branch.permission:branch.transfers.manage');

            Route::get('/stocktakes', [StocktakeController::class, 'index'])
                ->middleware('branch.permission:branch.stocktakes.view');
            Route::post('/stocktakes', [StocktakeController::class, 'store'])
                ->middleware('branch.permission:branch.stocktakes.manage');
            Route::get('/stocktakes/{stocktake}', [StocktakeController::class, 'show'])
                ->middleware('branch.permission:branch.stocktakes.view');
            Route::post('/stocktakes/{stocktake}/review', [StocktakeController::class, 'review'])
                ->middleware('branch.permission:branch.stocktakes.manage');
            Route::post('/stocktakes/{stocktake}/approve', [StocktakeController::class, 'approve'])
                ->middleware('branch.permission:branch.stocktakes.approve');
        });

    Route::post('/branches/{branch}/products/media', [ProductController::class, 'uploadImage'])
        ->middleware(['branch.context', 'branch.permission:branch.inventory.manage']);
    Route::get('/branches/{branch}/products/media/{cmsMedia}/preview/{variant}', [ProductController::class, 'mediaPreview'])
        ->middleware(['branch.context', 'branch.permission:branch.inventory.manage'])
        ->name('branch.product.media.preview');

    Route::get('/platform/access', PlatformAccessController::class)
        ->middleware('platform.permission:platform.users.view');
    Route::get('/platform/audit-logs', [AuditLogController::class, 'platformIndex'])
        ->middleware('platform.permission:platform.audit.view');
    Route::prefix('/platform/cms')->middleware('platform.permission:platform.content.manage')->name('platform.cms.')->group(function () {
        Route::get('/pages', [CmsContentController::class, 'index'])->name('pages.index');
        Route::post('/pages', [CmsContentController::class, 'store'])->name('pages.store');
        Route::get('/pages/{cmsPage}', [CmsContentController::class, 'show'])->name('pages.show');
        Route::get('/pages/{cmsPage}/versions', [CmsContentController::class, 'versions'])->name('pages.versions');
        Route::put('/pages/{cmsPage}', [CmsContentController::class, 'update'])->name('pages.update');
        Route::post('/pages/{cmsPage}/publish', [CmsContentController::class, 'publish'])->name('pages.publish');
        Route::post('/pages/{cmsPage}/rollback/{version}', [CmsContentController::class, 'rollback'])->name('pages.rollback');
        Route::get('/media', [CmsContentController::class, 'mediaIndex'])->name('media.index');
        Route::post('/media', [CmsContentController::class, 'upload'])->name('media.upload');
        Route::get('/media/{cmsMedia}/preview/{variant}', [CmsContentController::class, 'mediaPreview'])->name('media.preview');
        Route::get('/site-content', [CmsSiteContentController::class, 'show'])->name('site-content.show');
        Route::put('/site-content', [CmsSiteContentController::class, 'update'])->name('site-content.update');
        Route::post('/site-content/publish', [CmsSiteContentController::class, 'publish'])->name('site-content.publish');
    });
    Route::get('/platforms/{platform}/reports/branches', [PlatformReportController::class, 'branchComparison'])
        ->middleware('platform.permission:platform.reports.view');
    Route::post('/platforms/{platform}/reports/export', [PlatformReportController::class, 'export'])
        ->middleware('platform.permission:platform.reports.view');
    Route::get('/platform/report-exports/{reportExport}/download', [PlatformReportController::class, 'download'])
        ->middleware('platform.permission:platform.reports.view');
    Route::get('/platforms/{platform}/settings', [SettingController::class, 'platformIndex'])
        ->middleware('platform.permission:platform.settings.manage');
    Route::patch('/platforms/{platform}/settings', [SettingController::class, 'platformUpdate'])
        ->middleware('platform.permission:platform.settings.manage');

    Route::get('/platform/branches', [PlatformBranchController::class, 'index'])
        ->middleware('platform.permission:platform.branches.view');
    Route::post('/platform/branches', [PlatformBranchController::class, 'store'])
        ->middleware('platform.permission:platform.branches.manage');
    Route::patch('/platform/branches/{branch}/approve', [PlatformBranchController::class, 'approve'])
        ->middleware('platform.permission:platform.branches.manage');
    Route::patch('/platform/branches/{branch}/suspend', [PlatformBranchController::class, 'suspend'])
        ->middleware('platform.permission:platform.branches.manage');
    Route::patch('/platform/branches/{branch}/activate', [PlatformBranchController::class, 'activate'])
        ->middleware('platform.permission:platform.branches.manage');
    Route::patch('/platform/branches/{branch}/archive', [PlatformBranchController::class, 'archive'])
        ->middleware('platform.permission:platform.branches.manage');
});

Route::get('/media/{cmsMedia}/{variant}', [CmsContentController::class, 'publicMedia'])->name('public.media');
