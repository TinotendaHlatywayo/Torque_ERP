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
        $this->withHeaders(['Origin' => 'http://127.0.0.1:5173']);

        config([
            'seeding.admin_email' => 'admin@example.test',
            'seeding.admin_password' => 'secret123',
        ]);

        $this->seed(DatabaseSeeder::class);
        app(DemoController::class)->seed();

        $branch = Branch::where('code', 'TMS-01')->firstOrFail();
        $mutare = Branch::where('code', 'TMS-02')->firstOrFail();
        $gweru = Branch::where('code', 'TMS-03')->firstOrFail();

        $this->assertSame('TMS Mutare Main Branch', $mutare->name);
        $this->assertSame('TMS Gweru Main Branch', $gweru->name);
        $this->assertSame(5, Product::where('branch_id', $branch->id)->count());
        $this->assertSame(5, StockLevel::where('branch_id', $branch->id)->count());
        $this->assertSame(0, Product::where('branch_id', $mutare->id)->count());
        $this->assertSame(0, StockLevel::where('branch_id', $mutare->id)->count());

        $this->getJson('/api/public/site')->assertOk()
            ->assertJsonPath('catalogue_ready', true)
            ->assertJsonPath('branch.name', 'TMS Rusape Main Branch')
            ->assertJsonCount(4, 'featured');

        $this->getJson('/api/public/products/oil-filter')->assertOk()
            ->assertJsonPath('product.slug', 'oil-filter')
            ->assertJsonPath('product.image_url', url('/images/products/oil-filter.jpg'))
            ->assertJsonPath('can_reserve', true);

        $this->postJson('/login', ['email' => 'admin@example.test', 'password' => 'secret123'])->assertNoContent();
        $me = $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('account_type', 'platform')
            ->assertJsonPath('default_destination', '/platform')
            ->json();

        $this->assertContains('TMS-01', array_column($me['branches'], 'code'));
        $this->assertContains('TMS-02', array_column($me['branches'], 'code'));
        $this->assertContains('TMS-03', array_column($me['branches'], 'code'));
    }
}
