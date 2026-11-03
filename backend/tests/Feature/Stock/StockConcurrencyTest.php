<?php

namespace Tests\Feature\Stock;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private string $resultDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mariadb') {
            $this->markTestSkipped('Concurrent stock tests require MariaDB with row locking.');
        }

        $this->resultDir = sys_get_temp_dir().'/torqueerp-stock-concurrency-'.Str::uuid();
        mkdir($this->resultDir, 0777, true);

        $platform = Platform::create([
            'name' => 'Concurrency Platform',
            'slug' => 'concurrency-platform-'.uniqid(),
            'currency_code' => 'USD',
        ]);

        $branch = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Concurrency Branch',
            'code' => 'CONC',
            'slug' => 'concurrency-branch',
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);

        $unit = Unit::create([
            'branch_id' => $branch->id,
            'code' => 'PCS',
            'name' => 'Pieces',
            'allow_decimal' => false,
            'is_active' => true,
        ]);

        $product = Product::create([
            'branch_id' => $branch->id,
            'base_unit_id' => $unit->id,
            'sku' => 'CONC-1',
            'name' => 'Concurrency Product',
            'selling_price_minor' => 1000,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
        ]);

        StockLevel::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $admin = User::factory()->create(['name' => 'Concurrency Admin']);

        app(StockLedger::class)->postOpening(
            $branch,
            $product,
            '5',
            $unit->id,
            null,
            $admin,
            (string) Str::uuid(),
        );

        file_put_contents($this->resultDir.'/context.json', json_encode([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'admin_id' => $admin->id,
            'key_one' => (string) Str::uuid(),
            'key_two' => (string) Str::uuid(),
        ]));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->resultDir)) {
            foreach (glob($this->resultDir.'/*') as $file) {
                @unlink($file);
            }
            @rmdir($this->resultDir);
        }

        parent::tearDown();
    }

    public function test_two_concurrent_sale_attempts_cannot_oversell(): void
    {
        $context = json_decode(file_get_contents($this->resultDir.'/context.json'), true, flags: JSON_THROW_ON_ERROR);

        $pids = [];

        foreach (['key_one', 'key_two'] as $index => $keyName) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Unable to fork a concurrent stock process.');
            }

            if ($pid === 0) {
                try {
                    DB::purge(config('database.default'));
                    DB::reconnect();

                    $branch = Branch::findOrFail($context['branch_id']);
                    $product = Product::findOrFail($context['product_id']);
                    $unit = Unit::findOrFail($context['unit_id']);
                    $admin = User::findOrFail($context['admin_id']);

                    app(StockLedger::class)->postSale(
                        $branch,
                        $product,
                        '3',
                        $context['unit_id'],
                        'concurrent-sale-'.$index,
                        $admin,
                        $context[$keyName],
                    );

                    file_put_contents($this->resultDir.'/result-'.$index, 'OK');
                } catch (\Throwable $exception) {
                    file_put_contents($this->resultDir.'/result-'.$index, 'ERROR: '.$exception->getMessage());
                }

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [
            file_get_contents($this->resultDir.'/result-0'),
            file_get_contents($this->resultDir.'/result-1'),
        ];

        sort($results);

        $this->assertContains('OK', $results);
        $this->assertContains('ERROR: Insufficient stock: the posting would make quantity on hand negative.', $results);

        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $context['branch_id'],
            'product_id' => $context['product_id'],
            'quantity_on_hand' => '2.0000',
        ]);
    }
}
