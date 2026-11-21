<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DemoController;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_a_usable_public_storefront_and_admin_login(): void
    {
        config([
            'seeding.admin_email' => 'admin@example.test',
            'seeding.admin_password' => 'secret123',
        ]);

        $this->seed(DatabaseSeeder::class);
        app(DemoController::class)->seed();

        $branch = Branch::where('code', 'TMS-01')->firstOrFail();

        $this->assertSame(5, Product::where('branch_id', $branch->id)->count());
        $this->assertSame(5, StockLevel::where('branch_id', $branch->id)->count());
        $this->assertSame(1, Product::where('branch_id', '!=', $branch->id)->count());

        $this->getJson('/api/public/site')->assertOk()
            ->assertJsonPath('catalogue_ready', true)
            ->assertJsonPath('branch.name', 'TMS Rusape Main Branch')
            ->assertJsonCount(4, 'featured');

        $this->getJson('/api/public/products/oil-filter')->assertOk()
            ->assertJsonPath('product.slug', 'oil-filter')
            ->assertJsonPath('product.image_url', url('/images/products/oil-filter.svg'))
            ->assertJsonPath('can_reserve', true);

        $this->postJson('/login', ['email' => 'admin@example.test', 'password' => 'secret123'])->assertNoContent();
    }
}
