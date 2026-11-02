<?php

use App\Http\Controllers\Api\BranchRoleController;
use App\Http\Controllers\Api\BranchUserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MoneyMovementController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\PlatformAccessController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductConversionController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierBillController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', MeController::class);

    Route::prefix('branches/{branch}')
        ->middleware('branch.context')
        ->scopeBindings()
        ->group(function () {
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
            Route::post('/sales/{sale}/void', [SaleController::class, 'void'])
                ->middleware('branch.permission:branch.sales.void');
            Route::get('/returns', [SaleController::class, 'returnsIndex'])
                ->middleware('branch.permission:branch.sales.view');
            Route::post('/sales/{sale}/returns', [SaleController::class, 'storeReturn'])
                ->middleware('branch.permission:branch.sales.void');
            Route::get('/returns/{saleReturn}', [SaleController::class, 'showReturn'])
                ->middleware('branch.permission:branch.sales.view');
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
            Route::post('/products/{product}/stock/movements', [StockMovementController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::post('/stock/movements/{stockMovement}/reverse', [StockMovementController::class, 'reverse'])
                ->middleware('branch.permission:branch.inventory.manage');
        });

    Route::get('/platform/access', PlatformAccessController::class)
        ->middleware('platform.permission:platform.users.view');
});
