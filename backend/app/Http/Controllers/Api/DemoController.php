<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use App\Models\Customer;
use App\Models\DisciplinaryRecord;
use App\Models\Employee;
use App\Models\EmployeePayComponent;
use App\Models\EmployeeSalaryAssignment;
use App\Models\Expense;
use App\Models\HrAttachment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PaymentAccount;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollStatutoryRule;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalaryGrade;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\ServiceJob;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\StockTransfer;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkshopService;
use App\Support\AssetService;
use App\Support\AuditLogger;
use App\Support\CheckoutService;
use App\Support\JobService;
use App\Support\LoyaltyConfigService;
use App\Support\LoyaltyQualificationService;
use App\Support\MoneyMovementService;
use App\Support\PayrollEngine;
use App\Support\PayrollPaymentService;
use App\Support\Permissions;
use App\Support\ReservationService;
use App\Support\Roles;
use App\Support\SaleReturnService;
use App\Support\SettingService;
use App\Support\StockLedger;
use App\Support\StocktakeService;
use App\Support\StockTransferService;
use App\Support\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoController extends Controller
{
    private const CMS_SLUG = 'demo-store-guide';

    private const JOB_MARKER = 'Demo: brake inspection and filter replacement';

    private const STOCKTAKE_REASON = 'Demo cycle count';

    public function seed(): JsonResponse
    {
        $branch = $this->resolveBranch();

        DB::transaction(function () use ($branch): void {
            $actor = $this->resolveActor($branch, true);
            $this->seedBranch($branch, $actor);
        });

        return response()->json([
            'success' => true,
            'message' => 'Demo data seeded successfully for '.$branch->name.'.',
        ]);
    }

    private function seedBranch(Branch $branch, User $actor): void
    {
        $this->ensureMembership($branch, $actor, Roles::ADMIN);

        $destination = $this->ensureDestinationBranch($branch, $actor);
        $users = $this->seedUsers($branch);
        $products = $this->seedCatalogue($branch, $actor, $destination);
        $accounts = $this->seedFinance($branch, $actor);
        [$customers, $sales] = $this->seedSales($branch, $actor, $products, $accounts, $users);
        $this->seedReservations($branch, $actor, $products['OF-210']);
        $this->seedWorkshop($branch, $actor, $customers['RUSAPE-FLEET'], $products['OF-210'], $sales[0]);
        $this->seedStockOperations($branch, $actor, $destination, $products);
        $this->seedHrAndPayroll($branch, $actor, $users['manager']);
        $this->seedLoyaltyAndContent($branch, $actor, $customers['MAYA']);
        $this->seedTasks($branch, $actor, $users['manager']);
    }

    public function wipe(): JsonResponse
    {
        $branch = $this->resolveBranch();

        DB::transaction(function () use ($branch): void {
            $this->wipeBranch($branch);
        });

        return response()->json([
            'success' => true,
            'message' => 'Demo data wiped successfully.',
        ]);
    }

    private function resolveBranch(): Branch
    {
        $routeBranch = request()->route('branch');

        if ($routeBranch instanceof Branch) {
            return $routeBranch;
        }

        if (is_string($routeBranch) || is_int($routeBranch)) {
            return Branch::findOrFail($routeBranch);
        }

        return Branch::where('code', 'TMS-01')->firstOrFail();
    }

    private function resolveActor(Branch $branch, bool $ensureMembership): User
    {
        $actor = User::where('email', config('seeding.admin_email'))
            ->where('is_active', true)
            ->first();

        if (! $actor instanceof User) {
            $actor = User::where('is_platform_admin', true)
                ->where('is_active', true)
                ->first();
        }

        if (! $actor instanceof User) {
            throw new \RuntimeException('An active admin user is required to seed demo data.');
        }

        if ($ensureMembership) {
            $this->ensureMembership($branch, $actor, Roles::ADMIN);
        }

        return $actor;
    }

    private function ensureDestinationBranch(Branch $branch, User $actor): Branch
    {
        $code = 'DEMO-DST-'.$branch->code;
        $slug = 'demo-dst-'.$branch->slug;
        $name = str_ends_with($branch->name, ' Demo Destination')
            ? $branch->name
            : $branch->name.' Demo Destination';

        $destination = Branch::withTrashed()->firstOrCreate(
            ['code' => $code],
            [
                'platform_id' => $branch->platform_id,
                'name' => $name,
                'slug' => $slug,
                'status' => Branch::STATUS_ACTIVE,
                'timezone' => $branch->timezone,
                'currency_code' => $branch->currency_code,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ],
        );

        if ($destination->trashed()) {
            $destination->restore();
        }

        $destination->update([
            'platform_id' => $branch->platform_id,
            'name' => $name,
            'slug' => $slug,
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => $branch->timezone,
            'currency_code' => $branch->currency_code,
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ]);

        $this->ensureMembership($destination, $actor, Roles::ADMIN);

        return $destination->refresh();
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(Branch $branch): array
    {
        $definitions = [
            'manager' => ['Manager', 'manager'],
            'cashier' => ['Cashier', 'cashier'],
            'storekeeper' => ['Storekeeper', 'storekeeper'],
            'accountant' => ['Accountant', 'accountant'],
        ];

        $users = [];

        foreach ($definitions as $key => [$label, $role]) {
            $email = $this->demoEmails($branch)[$key];

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo '.$label,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $user->update(['is_active' => true]);
            $this->ensureMembership($branch, $user, $role);
            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @return array<string, Product>
     */
    private function seedCatalogue(Branch $branch, User $actor, Branch $destination): array
    {
        $piece = Unit::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'PCS'],
            ['name' => 'Piece', 'symbol' => 'pc', 'is_active' => true],
        );
        $set = Unit::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'SET'],
            ['name' => 'Set', 'symbol' => 'set', 'is_active' => true],
        );
        $box = Unit::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'BOX'],
            ['name' => 'Box', 'symbol' => 'box', 'is_active' => true],
        );

        foreach ([
            'Engine Parts' => 'Filters, belts and engine components',
            'Brakes' => 'Brake pads, discs and shoes',
            'Suspension' => 'Shocks, joints and steering links',
            'Filters' => 'Oil, air and fuel filters',
            'Electrical' => 'Plugs, lamps and sensors',
            'Transmission' => 'Clutch and drivetrain',
            'Batteries' => 'Starting power batteries',
            'Lubricants' => 'Oils and fluids',
        ] as $name => $description) {
            Category::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $name],
                ['slug' => Str::slug($name), 'description' => $description, 'is_active' => true],
            );
        }

        $categoryId = static fn (string $name): ?string => Category::where('branch_id', $branch->id)
            ->where('name', $name)
            ->value('id');

        $definitions = [
            [
                'sku' => 'BP-104',
                'name' => 'Brake Pad Set',
                'slug' => 'brake-pad-set',
                'description' => 'Quiet, dependable brake pads for daily commutes and fleet vehicles.',
                'image' => '/images/products/brake-pad-set.jpg',
                'image_alt' => 'Photo of a brake pad set',
                'category' => 'Brakes',
                'unit' => $set,
                'price' => 4500,
                'stock' => '12',
            ],
            [
                'sku' => 'OF-210',
                'name' => 'Oil Filter',
                'slug' => 'oil-filter',
                'description' => 'High-filtration oil filter that protects engines between service intervals.',
                'image' => '/images/products/oil-filter.jpg',
                'image_alt' => 'Photo of an oil filter',
                'category' => 'Filters',
                'unit' => $piece,
                'price' => 1250,
                'stock' => '24',
            ],
            [
                'sku' => 'SP-4',
                'name' => 'Spark Plugs (4 Pack)',
                'slug' => 'spark-plugs-4-pack',
                'description' => 'Four reliable spark plugs for smooth starting and efficient combustion.',
                'image' => '/images/products/spark-plugs-4-pack.jpg',
                'image_alt' => 'Photo of four spark plugs',
                'category' => 'Electrical',
                'unit' => $set,
                'price' => 2400,
                'stock' => '18',
            ],
            [
                'sku' => 'AF-115',
                'name' => 'Air Filter',
                'slug' => 'air-filter',
                'description' => 'Clean airflow filter that helps maintain engine performance and economy.',
                'image' => '/images/products/air-filter.jpg',
                'image_alt' => 'Photo of an air filter',
                'category' => 'Filters',
                'unit' => $piece,
                'price' => 1800,
                'stock' => '20',
            ],
            [
                'sku' => 'SA-490',
                'name' => 'Shock Absorber',
                'slug' => 'shock-absorber',
                'description' => 'Durable front shock absorber for stable handling on rough roads.',
                'image' => '/images/products/shock-absorber.jpg',
                'image_alt' => 'Photo of a shock absorber',
                'category' => 'Suspension',
                'unit' => $piece,
                'price' => 8500,
                'stock' => '8',
            ],
        ];

        $products = [];

        foreach ($definitions as $definition) {
            $attributes = [
                'name' => $definition['name'],
                'base_unit_id' => $definition['unit']->id,
                'category_id' => $categoryId($definition['category']),
                'selling_price_minor' => $definition['price'],
                'currency_code' => $branch->currency_code,
                'track_stock' => true,
                'is_active' => true,
                'public_slug' => $definition['slug'],
                'public_description' => $definition['description'],
                'is_public' => true,
                'public_image_url' => $definition['image'],
                'public_image_alt' => $definition['image_alt'],
            ];

            $product = Product::firstOrCreate(
                ['branch_id' => $branch->id, 'sku' => $definition['sku']],
                $attributes,
            );
            $product->update($attributes);

            $level = StockLevel::firstOrCreate(
                ['branch_id' => $branch->id, 'product_id' => $product->id],
                ['quantity_on_hand' => '0', 'quantity_reserved' => '0'],
            );

            $hasOpening = StockMovement::where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->where('opening_marker', StockMovement::OPENING_MARKER)
                ->exists();

            if (! $hasOpening && bccomp((string) $level->quantity_on_hand, '0', 4) > 0) {
                $level->update(['quantity_on_hand' => '0']);
            }

            app(StockLedger::class)->postOpening(
                $branch,
                $product,
                $definition['stock'],
                $definition['unit']->id,
                'Demo opening stock',
                $actor,
                'demo-opening:'.$branch->id.':'.$definition['sku'],
            );

            $products[$definition['sku']] = $product->refresh();
        }

        ProductUnit::firstOrCreate(
            ['product_id' => $products['OF-210']->id, 'unit_id' => $box->id],
            [
                'branch_id' => $branch->id,
                'conversion_factor' => '12',
                'selling_price_minor' => 14000,
                'is_active' => true,
            ],
        );

        $this->seedDestinationProduct($destination);

        return $products;
    }

    private function seedDestinationProduct(Branch $destination): void
    {
        $unit = Unit::firstOrCreate(
            ['branch_id' => $destination->id, 'code' => 'PCS'],
            ['name' => 'Piece', 'symbol' => 'pc', 'is_active' => true],
        );

        $category = Category::firstOrCreate(
            ['branch_id' => $destination->id, 'name' => 'Demo Transfers'],
            ['slug' => 'demo-transfers', 'description' => 'Demo transfer receiving stock', 'is_active' => true],
        );

        $product = Product::firstOrCreate(
            ['branch_id' => $destination->id, 'sku' => 'DEMO-RECV-001'],
            [
                'name' => 'Demo Transfer Receiver',
                'base_unit_id' => $unit->id,
                'category_id' => $category->id,
                'selling_price_minor' => 1000,
                'currency_code' => $destination->currency_code,
                'track_stock' => true,
                'is_active' => true,
                'is_public' => false,
            ],
        );

        StockLevel::firstOrCreate(
            ['branch_id' => $destination->id, 'product_id' => $product->id],
            ['quantity_on_hand' => '0', 'quantity_reserved' => '0'],
        );
    }

    /**
     * @return array<string, PaymentAccount>
     */
    private function seedFinance(Branch $branch, User $actor): array
    {
        $cash = PaymentAccount::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Demo Cash Drawer'],
            ['type' => 'cash', 'currency_code' => $branch->currency_code, 'is_active' => true],
        );
        $bank = PaymentAccount::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Demo Bank Account'],
            ['type' => 'bank', 'currency_code' => $branch->currency_code, 'is_active' => true],
        );

        $movements = app(MoneyMovementService::class);
        $currency = $branch->currency_code;

        $movements->postMovement($branch, $cash, 'opening', 500000, $currency, 'DEMO-OPENING', 'Demo opening cash balance', 'demo', 'demo-opening', $actor, 'demo-cash-opening');
        $movements->postMovement($branch, $bank, 'adjustment_in', 80000, $currency, 'DEMO-CASH-IN', 'Demo customer receipt', 'demo', 'demo-cash-in', $actor, 'demo-cash-in');
        $movements->postMovement($branch, $cash, 'adjustment_out', 25000, $currency, 'DEMO-CASH-OUT', 'Demo petty cash payment', 'demo', 'demo-cash-out', $actor, 'demo-cash-out');

        $expense = Expense::firstOrCreate(
            ['branch_id' => $branch->id, 'category' => 'Utilities', 'description' => 'Demo electricity bill'],
            [
                'amount_minor' => 12000,
                'currency_code' => $currency,
                'incurred_date' => now()->toDateString(),
                'paid_date' => now()->toDateString(),
                'payment_account_id' => $bank->id,
                'status' => 'paid',
                'actor_id' => $actor->id,
            ],
        );

        $assetExpense = Expense::firstOrCreate(
            ['branch_id' => $branch->id, 'category' => 'Equipment', 'description' => 'Demo diagnostic scanner'],
            [
                'amount_minor' => 150000,
                'currency_code' => $currency,
                'incurred_date' => now()->subDays(10)->toDateString(),
                'paid_date' => now()->subDays(10)->toDateString(),
                'payment_account_id' => $bank->id,
                'status' => 'paid',
                'actor_id' => $actor->id,
            ],
        );

        $bill = SupplierBill::firstOrCreate(
            ['branch_id' => $branch->id, 'supplier_name' => 'Apex Auto Parts', 'bill_number' => 'DEMO-INV-001'],
            [
                'amount_minor' => 250000,
                'currency_code' => $currency,
                'bill_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'paid_minor' => 100000,
                'status' => 'partial',
                'actor_id' => $actor->id,
            ],
        );

        if (! SupplierBillPayment::where('branch_id', $branch->id)
            ->where('supplier_bill_id', $bill->id)
            ->where('reference', 'DEMO-BILL-PAYMENT')
            ->exists()) {
            SupplierBillPayment::create([
                'branch_id' => $branch->id,
                'supplier_bill_id' => $bill->id,
                'payment_account_id' => $bank->id,
                'amount_minor' => 100000,
                'currency_code' => $currency,
                'paid_at' => now()->toDateString(),
                'reference' => 'DEMO-BILL-PAYMENT',
                'actor_id' => $actor->id,
            ]);
        }

        $asset = Asset::where('branch_id', $branch->id)
            ->where('source_type', Asset::SOURCE_EXPENSE)
            ->where('source_id', $assetExpense->id)
            ->first();

        if (! $asset instanceof Asset) {
            $asset = app(AssetService::class)->acquire($branch, $actor, [
                'name' => 'Demo Diagnostic Scanner',
                'description' => 'Workshop diagnostic tool used in demo jobs.',
                'acquisition_date' => now()->subDays(10)->toDateString(),
                'cost_minor' => 150000,
                'currency_code' => $currency,
                'location' => 'Workshop',
                'custodian' => 'Service Manager',
                'source_type' => Asset::SOURCE_EXPENSE,
                'source_id' => $assetExpense->id,
            ]);
        }

        if (! AssetMaintenance::where('branch_id', $branch->id)->where('asset_id', $asset->id)->exists()) {
            app(AssetService::class)->recordMaintenance($branch, $asset, $actor, [
                'performed_date' => now()->subDays(3)->toDateString(),
                'cost_minor' => 5000,
                'currency_code' => $currency,
                'provider' => 'Demo Service Provider',
                'description' => 'Demo calibration service.',
                'expense_id' => $expense->id,
            ]);
        }

        return ['cash' => $cash, 'bank' => $bank];
    }

    /**
     * @param  array<string, Product>  $products
     * @param  array<string, PaymentAccount>  $accounts
     * @param  array<string, User>  $users
     * @return array{0: array<string, Customer>, 1: array<int, Sale>}
     */
    private function seedSales(Branch $branch, User $actor, array $products, array $accounts, array $users): array
    {
        $customerDefinitions = [
            'RUSAPE-FLEET' => ['Rusape Fleet Services', '+263771234567', 'fleet@rusape.co.zw'],
            'TINO' => ['Tino Mutasa', '+263779876543', 'tino@example.com'],
            'MAYA' => ['Maya Ncube', '+263772345678', 'maya@example.com'],
        ];

        $customers = [];

        foreach ($customerDefinitions as $key => [$name, $phone, $email]) {
            $attributes = [
                'name' => $name,
                'email' => $email,
                'address' => 'Rusape, Zimbabwe',
                'is_active' => true,
            ];

            if ($key === 'MAYA') {
                $attributes['marketing_consent_at'] = now()->subDays(120);
                $attributes['marketing_channel'] = 'sms';
                $attributes['marketing_channel_active'] = true;
            }

            $customers[$key] = Customer::firstOrCreate(
                ['branch_id' => $branch->id, 'phone' => $phone],
                $attributes,
            );
        }

        $specs = [
            [
                'key' => 'one',
                'customer' => 'RUSAPE-FLEET',
                'lines' => ['BP-104' => '1', 'OF-210' => '1', 'SP-4' => '1', 'AF-115' => '1', 'SA-490' => '1'],
            ],
            [
                'key' => 'two',
                'customer' => 'TINO',
                'lines' => ['OF-210' => '2', 'AF-115' => '1', 'SP-4' => '1'],
            ],
            [
                'key' => 'three',
                'customer' => 'RUSAPE-FLEET',
                'lines' => ['BP-104' => '1', 'SA-490' => '2', 'OF-210' => '1'],
            ],
        ];

        $checkout = app(CheckoutService::class);
        $sales = [];

        foreach ($specs as $spec) {
            $sales[] = $checkout->checkout(
                $branch,
                $actor,
                $this->salePayload($branch, $customers[$spec['customer']], $products, $spec['lines'], $spec['key']),
                'demo-sale:'.$branch->id.':'.$spec['key'],
            );
        }

        $firstSale = $sales[0];
        $firstItem = $firstSale->items->first();

        if ($firstItem instanceof SaleItem) {
            $returnIdempotency = 'demo-return:'.$branch->id.':one';

            if (! SaleReturn::where('branch_id', $branch->id)->where('idempotency_key', $returnIdempotency)->exists()) {
                $returns = app(SaleReturnService::class);
                $saleReturn = $returns->create($branch, $firstSale, $actor, [
                    'reason' => 'Demo: customer changed mind on one item.',
                    'items' => [
                        ['sale_item_id' => $firstItem->id, 'quantity' => '1'],
                    ],
                ], $returnIdempotency);
                $returns->approve($branch, $saleReturn, $accounts['cash'], $actor);
            }
        }

        return [$customers, $sales];
    }

    /**
     * @param  array<string, Product>  $products
     * @param  array<string, string>  $lines
     * @return array<string, mixed>
     */
    private function salePayload(Branch $branch, Customer $customer, array $products, array $lines, string $key): array
    {
        $payloadLines = [];
        $totalMinor = 0;

        foreach ($lines as $sku => $quantity) {
            $product = $products[$sku];
            $payloadLines[] = [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'quantity' => $quantity,
                'discount_percent' => 0.0,
                'floor_price_minor' => 0,
                'tax_rate_percent' => 0.0,
            ];
            $totalMinor += $product->selling_price_minor * (int) $quantity;
        }

        return [
            'customer_id' => $customer->id,
            'lines' => $payloadLines,
            'payments' => [
                [
                    'method' => 'cash',
                    'amount_minor' => $totalMinor,
                    'currency_code' => $branch->currency_code,
                    'reference' => 'DEMO-SALE-'.$key,
                ],
            ],
        ];
    }

    private function seedReservations(Branch $branch, User $actor, Product $product): void
    {
        $services = app(ReservationService::class);
        $idempotencyKey = $this->stableUuid($branch->id.'|reservation|oil-filter');

        $result = $services->create($branch, $product, [
            'name' => 'Demo Online Customer',
            'email' => 'online@example.com',
            'phone' => '+263771111111',
            'quantity' => '1',
        ], $idempotencyKey, $actor);

        $result['reservation']->update(['expires_at' => now()->addDays(30)]);
    }

    private function seedWorkshop(Branch $branch, User $actor, Customer $customer, Product $product, Sale $sale): void
    {
        $jobs = app(JobService::class);

        $service = WorkshopService::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DEMO-SERVICE'],
            [
                'name' => 'Demo General Service',
                'description' => 'Demo labour service.',
                'default_hours' => '1.00',
                'default_rate_minor' => 5000,
                'currency_code' => $branch->currency_code,
                'is_active' => true,
            ],
        );

        $job = ServiceJob::where('branch_id', $branch->id)
            ->where('complaint', self::JOB_MARKER)
            ->first();

        if (! $job instanceof ServiceJob) {
            $job = $jobs->createJob($branch, $actor, [
                'customer_id' => $customer->id,
                'technician_id' => $actor->id,
                'vehicle_make' => 'Toyota',
                'vehicle_model' => 'Hilux',
                'vehicle_registration' => 'DEMO-123',
                'vehicle_year' => '2018',
                'complaint' => self::JOB_MARKER,
            ]);

            $jobs->addLabourLine($job, $actor, [
                'workshop_service_id' => $service->id,
                'description' => 'Demo brake inspection',
                'hours' => '1.00',
                'rate_minor' => 5000,
            ]);
            $jobs->addPartLine($job, $actor, [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'quantity' => '2',
                'unit_price_minor' => $product->selling_price_minor,
            ], 'demo-job-part:'.$branch->id);
            $jobs->updateJob($job, $actor, ['diagnosis' => 'Demo: worn brake pads and oil filter due for replacement.']);
            $job = $jobs->estimate($job, $actor);
            $job = $jobs->approve($job, $actor, ['approval_reference' => 'DEMO-APPROVAL']);
            $job = $jobs->start($job, $actor);
            $job = $jobs->complete($job, $actor);
        }

        if ($job->sale_id === null && in_array($job->status, [
            ServiceJob::STATUS_APPROVED,
            ServiceJob::STATUS_IN_PROGRESS,
            ServiceJob::STATUS_COMPLETED,
        ], true)) {
            $jobs->linkInvoice($job, $actor, $sale->id);
        }
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedStockOperations(Branch $branch, User $actor, Branch $destination, array $products): void
    {
        $transfers = app(StockTransferService::class);
        $destinationProduct = Product::where('branch_id', $destination->id)
            ->where('sku', 'DEMO-RECV-001')
            ->firstOrFail();

        $transfer = StockTransfer::where('source_branch_id', $branch->id)
            ->where('destination_branch_id', $destination->id)
            ->where('product_id', $products['SA-490']->id)
            ->first();

        if (! $transfer instanceof StockTransfer) {
            $transfer = $transfers->create($branch, $actor, [
                'destination_branch_id' => $destination->id,
                'product_id' => $products['SA-490']->id,
                'unit_id' => $products['SA-490']->base_unit_id,
                'destination_product_id' => $destinationProduct->id,
                'destination_unit_id' => $destinationProduct->base_unit_id,
                'quantity' => '3',
                'reason' => 'Demo interstate stock balancing.',
            ]);
            $transfer = $transfers->dispatch($transfer, $actor);
            $transfers->receive($transfer, $actor);
        }

        $stocktakes = app(StocktakeService::class);

        if (! Stocktake::where('branch_id', $branch->id)->where('reason', self::STOCKTAKE_REASON)->exists()) {
            $levels = StockLevel::where('branch_id', $branch->id)->get()->keyBy('product_id');
            $lines = [];
            $adjustments = ['1', '-1', '0', '0', '0'];
            $index = 0;

            foreach ($products as $product) {
                $level = $levels->get($product->id);
                $counted = bcadd((string) $level->quantity_on_hand, $adjustments[$index], 4);
                $lines[] = [
                    'product_id' => $product->id,
                    'unit_id' => $product->base_unit_id,
                    'counted_quantity' => $counted,
                    'reason' => bccomp($adjustments[$index], '0', 4) === 0 ? '' : 'Demo counted variance',
                ];
                $index++;
            }

            $stocktake = $stocktakes->create($branch, $actor, [
                'reason' => self::STOCKTAKE_REASON,
                'lines' => $lines,
            ]);
            $stocktake = $stocktakes->review($stocktake, $actor);
            $stocktakes->approve($stocktake, $actor, ['approval_reference' => 'DEMO-STOCKTAKE']);
        }
    }

    private function seedHrAndPayroll(Branch $branch, User $actor, User $manager): void
    {
        $grade = SalaryGrade::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DEMO-MONTHLY'],
            [
                'name' => 'Demo Monthly Staff',
                'pay_basis' => SalaryGrade::BASIS_MONTHLY,
                'base_rate_minor' => 250000,
                'overtime_multiplier' => '1.50',
                'overtime_hourly_rate_minor' => 10000,
                'currency_code' => $branch->currency_code,
                'is_active' => true,
            ],
        );

        PayrollStatutoryRule::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DEMO-PAYE'],
            [
                'name' => 'Demo PAYE',
                'applies_to' => 'gross',
                'employee_rate' => '10.0000',
                'employer_rate' => '5.0000',
                'fixed_employee_minor' => 0,
                'fixed_employer_minor' => 0,
                'effective_from' => now()->subYear()->toDateString(),
                'is_active' => true,
            ],
        );

        $leaveType = LeaveType::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DEMO-ANNUAL'],
            [
                'name' => 'Demo Annual Leave',
                'is_paid' => true,
                'requires_approval' => true,
                'max_days_per_year' => '21.00',
                'counts_weekends' => false,
                'is_active' => true,
            ],
        );

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();
        $period = PayrollPeriod::where('branch_id', $branch->id)
            ->whereDate('start_date', $periodStart->toDateString())
            ->whereDate('end_date', $periodEnd->toDateString())
            ->first();

        if (! $period instanceof PayrollPeriod) {
            $period = PayrollPeriod::create([
                'branch_id' => $branch->id,
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'pay_date' => $periodEnd->toDateString(),
                'status' => PayrollPeriod::STATUS_OPEN,
            ]);
        }

        $employeeDefinitions = [
            ['DEMO-EMP-001', 'Alice', 'Chipo', 'Store Supervisor'],
            ['DEMO-EMP-002', 'Brian', 'Takunda', 'Sales Assistant'],
        ];

        foreach ($employeeDefinitions as [$number, $firstName, $lastName, $title]) {
            $employee = Employee::firstOrCreate(
                ['branch_id' => $branch->id, 'employee_number' => $number],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => strtolower($firstName.'.'.$lastName.'@demo.test'),
                    'job_title' => $title,
                    'department' => 'Operations',
                    'employment_type' => 'full_time',
                    'hire_date' => now()->subYear()->toDateString(),
                    'status' => Employee::STATUS_ACTIVE,
                ],
            );

            if (! EmployeeSalaryAssignment::where('branch_id', $branch->id)
                ->where('employee_id', $employee->id)
                ->where('salary_grade_id', $grade->id)
                ->whereDate('effective_from', now()->subYear()->toDateString())
                ->exists()) {
                EmployeeSalaryAssignment::create([
                    'branch_id' => $branch->id,
                    'employee_id' => $employee->id,
                    'salary_grade_id' => $grade->id,
                    'effective_from' => now()->subYear()->toDateString(),
                ]);
            }

            if (! EmployeePayComponent::where('branch_id', $branch->id)
                ->where('employee_id', $employee->id)
                ->where('code', 'DEMO-ALLOWANCE')
                ->exists()) {
                EmployeePayComponent::create([
                    'branch_id' => $branch->id,
                    'employee_id' => $employee->id,
                    'type' => 'allowance',
                    'code' => 'DEMO-ALLOWANCE',
                    'label' => 'Demo transport allowance',
                    'amount_minor' => 10000,
                    'effective_from' => now()->subYear()->toDateString(),
                    'is_active' => true,
                ]);
            }

            $attendanceDate = CarbonImmutable::parse($periodStart->toDateString());
            $lastAttendanceDate = CarbonImmutable::parse(now()->toDateString())->min(CarbonImmutable::parse($periodEnd->toDateString()));

            while ($attendanceDate->lessThanOrEqualTo($lastAttendanceDate)) {
                if (! $attendanceDate->isWeekend()) {
                    $exists = AttendanceRecord::where('branch_id', $branch->id)
                        ->where('employee_id', $employee->id)
                        ->whereDate('work_date', $attendanceDate->toDateString())
                        ->exists();

                    if (! $exists) {
                        AttendanceRecord::create([
                            'branch_id' => $branch->id,
                            'employee_id' => $employee->id,
                            'work_date' => $attendanceDate->toDateString(),
                            'status' => 'present',
                            'scheduled_minutes' => 480,
                            'worked_minutes' => 480,
                            'overtime_minutes' => 0,
                            'recorded_by' => $actor->id,
                        ]);
                    }
                }

                $attendanceDate = $attendanceDate->addDay();
            }

            $leaveStart = CarbonImmutable::parse($periodStart->toDateString())->addDays(7)->toDateString();

            if (! LeaveRequest::where('branch_id', $branch->id)
                ->where('employee_id', $employee->id)
                ->whereDate('start_date', $leaveStart)
                ->exists()) {
                LeaveRequest::create([
                    'branch_id' => $branch->id,
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $leaveStart,
                    'end_date' => CarbonImmutable::parse($periodStart->toDateString())->addDays(8)->toDateString(),
                    'days_requested' => '2.00',
                    'reason' => 'Demo personal leave.',
                    'status' => 'approved',
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'created_by' => $actor->id,
                ]);
            }

            $disciplinary = DisciplinaryRecord::firstOrCreate(
                ['branch_id' => $branch->id, 'employee_id' => $employee->id, 'summary' => 'Demo late arrival coaching'],
                [
                    'incident_date' => now()->subDays(20)->toDateString(),
                    'category' => 'attendance',
                    'severity' => 'verbal',
                    'status' => 'resolved',
                    'issued_by' => $actor->id,
                    'issued_at' => now()->subDays(20),
                    'resolved_at' => now()->subDays(19),
                    'resolution_note' => 'Demo coaching completed.',
                ],
            );

            HrAttachment::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'employee_id' => $employee->id,
                    'attachable_type' => 'disciplinary_record',
                    'attachable_id' => $disciplinary->id,
                    'original_name' => 'demo-note.txt',
                ],
                [
                    'path' => 'demo/hr/demo-note.txt',
                    'mime_type' => 'text/plain',
                    'size_bytes' => 24,
                    'uploaded_by' => $actor->id,
                ],
            );
        }

        $run = $period->runs()->where('kind', PayrollRun::KIND_REGULAR)->first();

        if (! $run instanceof PayrollRun) {
            $run = app(PayrollEngine::class)->generate($period, $actor->id);
        }

        if ($run->status === PayrollRun::STATUS_DRAFT) {
            $run = app(PayrollEngine::class)->approve($run, $actor->id);
        }

        if ($run->status === PayrollRun::STATUS_APPROVED) {
            $payments = app(PayrollPaymentService::class);
            $payments->accrue($run, $actor);

            if ($run->net_minor > 0 && ! PayrollPayment::where('branch_id', $branch->id)
                ->where('idempotency_key', 'demo-payroll-payment:'.$run->id)
                ->exists()) {
                $payments->allocate($branch, $run, PaymentAccount::where('branch_id', $branch->id)
                    ->where('name', 'Demo Bank Account')
                    ->firstOrFail(), [
                        'amount_minor' => min($run->net_minor, 100000),
                        'paid_at' => now()->toDateTimeString(),
                        'method' => 'bank_transfer',
                        'reference' => 'DEMO-PAYROLL',
                    ], $actor, 'demo-payroll-payment:'.$run->id);
            }
        }
    }

    private function seedLoyaltyAndContent(Branch $branch, User $actor, Customer $maya): void
    {
        app(LoyaltyConfigService::class)->forBranch($branch);

        app(LoyaltyQualificationService::class)->recordSaleEvent($branch, [
            'customer_id' => $maya->id,
            'event_key' => 'demo-legacy:'.$branch->id,
            'source' => 'import',
            'source_ref' => 'DEMO-LEGACY',
            'total_minor' => 15000,
            'occurred_at' => '2020-01-01T00:00:00+00:00',
        ]);

        app(LoyaltyQualificationService::class)->recalculate($branch, $actor);

        app(SettingService::class)->set(
            'branch',
            $branch->id,
            'registration_notes',
            'Demo branch: demo users share the password "password".',
            $actor,
            app(AuditLogger::class),
        );

        $page = CmsPage::firstOrCreate(
            ['slug' => self::CMS_SLUG],
            [
                'title' => 'Demo Store Guide',
                'current_version' => 1,
                'created_by' => $actor->id,
            ],
        );

        CmsPageVersion::firstOrCreate(
            ['cms_page_id' => $page->id, 'version' => 1],
            [
                'title' => 'Demo Store Guide',
                'slug' => self::CMS_SLUG,
                'sections' => [
                    ['type' => 'hero', 'title' => 'Welcome to the demo store', 'body' => 'Browse stock, reserve online and review workshop services.'],
                ],
                'media_ids' => [],
                'created_by' => $actor->id,
            ],
        );

        $page->update([
            'title' => 'Demo Store Guide',
            'current_version' => 1,
            'published_version' => 1,
            'published_slug' => self::CMS_SLUG,
            'published_at' => now(),
        ]);
    }

    private function seedTasks(Branch $branch, User $actor, User $manager): void
    {
        $tasks = app(TaskService::class);

        $definitions = [
            ['Demo: check reorder suggestions', 'Review demo reorder suggestions and confirm stock levels.'],
            ['Demo: follow up overdue supplier bill', 'Contact the demo supplier and confirm payment timing.'],
        ];

        foreach ($definitions as [$title, $description]) {
            if (Task::where('branch_id', $branch->id)->where('title', $title)->exists()) {
                continue;
            }

            $tasks->create($branch, $actor, [
                'title' => $title,
                'description' => $description,
                'assigned_to' => $manager->id,
                'priority' => 'normal',
                'due_at' => now()->addDays(3),
            ]);
        }
    }

    private function ensureMembership(Branch $branch, User $user, string $role): void
    {
        $membership = BranchUser::firstOrCreate(
            ['branch_id' => $branch->id, 'user_id' => $user->id],
            ['role' => $role, 'is_active' => true],
        );

        $needsAdmin = $role === Roles::ADMIN
            && ! $membership->hasPermission(Permissions::BRANCH_TRANSFERS_MANAGE);

        if (! $membership->is_active || $needsAdmin) {
            $membership->update(['role' => $role, 'is_active' => true]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function demoEmails(Branch $branch): array
    {
        $slug = Str::slug($branch->code);

        return [
            'manager' => 'manager@demo-'.$slug.'.test',
            'cashier' => 'cashier@demo-'.$slug.'.test',
            'storekeeper' => 'storekeeper@demo-'.$slug.'.test',
            'accountant' => 'accountant@demo-'.$slug.'.test',
        ];
    }

    private function stableUuid(string $value): string
    {
        $hash = md5($value);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function deleteDemoDestinationBranch(Branch $branch): void
    {
        $destination = Branch::withTrashed()
            ->where('code', 'DEMO-DST-'.$branch->code)
            ->where('platform_id', $branch->platform_id)
            ->first();

        if (! $destination instanceof Branch) {
            return;
        }

        $this->wipeBranch($destination);

        DB::table('branch_users')->where('branch_id', $destination->id)->delete();
        DB::table('settings')->where('scope_type', 'branch')->where('scope_id', $destination->id)->delete();
        Branch::withTrashed()->where('id', $destination->id)->forceDelete();
    }

    private function wipeBranch(Branch $branch): void
    {
        $branchId = $branch->id;

        $pageIds = DB::table('cms_pages')->where('slug', self::CMS_SLUG)->pluck('id');
        DB::table('cms_page_versions')->whereIn('cms_page_id', $pageIds)->delete();
        DB::table('cms_pages')->whereIn('id', $pageIds)->delete();

        $reservationIds = DB::table('reservations')->where('branch_id', $branchId)->pluck('id');
        DB::table('reservation_payments')->whereIn('reservation_id', $reservationIds)->delete();
        DB::table('reservation_notification_deliveries')->where('branch_id', $branchId)->delete();
        DB::table('reservations')->where('branch_id', $branchId)->delete();

        $taskIds = DB::table('tasks')->where('branch_id', $branchId)->pluck('id');
        DB::table('notification_deliveries')->where('branch_id', $branchId)->delete();
        DB::table('task_completions')->where('branch_id', $branchId)->delete();
        DB::table('task_events')->where('branch_id', $branchId)->delete();
        DB::table('tasks')->where('branch_id', $branchId)->delete();
        DB::table('notification_preferences')->where('branch_id', $branchId)->delete();
        unset($taskIds);

        foreach ([
            'asset_maintenances',
            'assets',
            'stocktake_lines',
            'stocktakes',
            'job_part_lines',
            'job_labour_lines',
            'service_jobs',
            'workshop_services',
            'loyalty_qualifications',
            'loyalty_sale_events',
            'loyalty_configs',
        ] as $table) {
            DB::table($table)->where('branch_id', $branchId)->delete();
        }

        DB::table('stock_transfers')->where('source_branch_id', $branchId)
            ->orWhere('destination_branch_id', $branchId)
            ->delete();

        $payslipIds = DB::table('payslips')->where('branch_id', $branchId)->pluck('id');
        DB::table('payslip_lines')->whereIn('payslip_id', $payslipIds)->delete();

        foreach ([
            'loan_repayments',
            'payroll_adjustments',
            'payroll_payments',
            'payroll_accruals',
            'payslips',
            'payroll_runs',
            'payroll_periods',
            'employee_loans',
            'employee_pay_components',
            'employee_salary_assignments',
            'payroll_statutory_rules',
            'hr_attachments',
            'disciplinary_records',
            'leave_requests',
            'attendance_records',
            'employees',
            'salary_grades',
            'leave_types',
        ] as $table) {
            DB::table($table)->where('branch_id', $branchId)->delete();
        }

        foreach ([
            'supplier_bill_payments',
            'sale_return_items',
            'sale_returns',
            'payments',
            'receipts',
            'sale_items',
            'sales',
            'expenses',
            'supplier_bills',
            'money_movements',
            'payment_accounts',
            'stock_movements',
            'product_fitments',
            'product_oem_refs',
            'catalogue_import_logs',
            'product_units',
            'stock_levels',
            'products',
            'categories',
            'units',
            'customers',
            'sync_operations',
            'fx_rates',
            'document_templates',
        ] as $table) {
            DB::table($table)->where('branch_id', $branchId)->delete();
        }

        $demoUserIds = User::whereIn('email', array_values($this->demoEmails($branch)))->pluck('id');
        DB::table('report_exports')->whereIn('created_by', $demoUserIds)->delete();
        DB::table('settings')->where('scope_type', 'branch')->where('scope_id', $branchId)->delete();
        DB::table('branch_users')->where('branch_id', $branchId)->whereIn('user_id', $demoUserIds)->delete();
        User::whereIn('id', $demoUserIds)
            ->where('is_platform_admin', false)
            ->doesntHave('branchMemberships')
            ->delete();

        $this->deleteDemoDestinationBranch($branch);
    }
}
