<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoBranchScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_platform_user_cannot_use_demo_routes(): void
    {
        $branch = $this->createBranch('DEMO-A');
        $user = User::factory()->create();
        $this->createMembership($branch, $user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/branches/{$branch->id}/demo/seed")
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/branches/{$branch->id}/demo/wipe")
            ->assertForbidden();
    }

    public function test_seeded_platform_admin_can_seed_the_route_branch(): void
    {
        config([
            'seeding.admin_email' => 'admin@example.test',
            'seeding.admin_password' => 'secret123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $branch = Branch::where('code', 'TMS-01')->firstOrFail();
        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/branches/{$branch->id}/demo/seed")
            ->assertOk();

        $mutare = Branch::where('code', 'TMS-02')->firstOrFail();

        $this->assertSame(5, Product::where('branch_id', $branch->id)->count());
        $this->assertSame(5, Product::where('branch_id', $mutare->id)->count());
        $this->assertNotNull(Branch::where('code', 'DEMO-DST-TMS-01')->first());
        $this->assertNotNull(Branch::where('code', 'DEMO-DST-TMS-02')->first());
    }

    public function test_wipe_only_affects_the_requested_branch(): void
    {
        $branchA = $this->createBranch('DEMO-A');
        $branchB = $this->createBranch('DEMO-B');
        $platformAdmin = User::factory()->platformAdmin()->create();
        $this->createMembership($branchA, $platformAdmin);
        $token = $platformAdmin->createToken('test')->plainTextToken;

        $dataA = $this->createBranchData($branchA);
        $dataB = $this->createBranchData($branchB);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/branches/{$branchA->id}/demo/wipe")
            ->assertOk();

        foreach ($dataA as $table => $id) {
            $this->assertDatabaseMissing($table, ['id' => $id]);
        }

        foreach ($dataB as $table => $id) {
            $this->assertDatabaseHas($table, ['id' => $id]);
        }
    }

    private function createBranch(string $code): Branch
    {
        $platform = Platform::firstOrCreate(
            ['slug' => 'demo-platform'],
            ['name' => 'Demo Platform', 'currency_code' => 'USD']
        );

        return Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Demo '.$code,
            'code' => $code,
            'slug' => strtolower($code),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
            'approved_at' => now(),
        ]);
    }

    private function createMembership(Branch $branch, User $user): void
    {
        BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function createBranchData(Branch $branch): array
    {
        $unit = Unit::create([
            'branch_id' => $branch->id,
            'code' => 'PCS',
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => 'Demo Category',
            'slug' => 'demo-category-'.$branch->code,
        ]);

        $product = Product::create([
            'branch_id' => $branch->id,
            'base_unit_id' => $unit->id,
            'category_id' => $category->id,
            'name' => 'Demo Product',
            'sku' => 'DEMO-'.$branch->code,
            'selling_price_minor' => 1000,
            'currency_code' => 'USD',
        ]);

        $customer = Customer::create([
            'branch_id' => $branch->id,
            'name' => 'Demo Customer',
            'phone' => '+263770000000',
        ]);

        return [
            'products' => $product->id,
            'categories' => $category->id,
            'units' => $unit->id,
            'customers' => $customer->id,
        ];
    }
}
