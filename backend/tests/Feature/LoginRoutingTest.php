<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DemoController;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_determine_platform_branch_or_customer_destination(): void
    {
        $this->withHeaders(['Origin' => 'http://127.0.0.1:5173']);

        config([
            'seeding.admin_email' => 'admin@example.test',
            'seeding.admin_password' => 'secret123',
        ]);

        $this->seed(DatabaseSeeder::class);
        app(DemoController::class)->seed();

        $this->postJson('/login', ['email' => 'admin@example.test', 'password' => 'secret123'])
            ->assertNoContent();

        $platformMe = $this->getJson('/api/user')->assertOk()->json();

        $this->assertTrue($platformMe['user']['is_platform_admin']);
        $this->assertSame('platform', $platformMe['account_type']);
        $this->assertSame('/platform', $platformMe['default_destination']);
        $this->assertContains('TMS-01', array_column($platformMe['branches'], 'code'));
        $this->assertContains('TMS-02', array_column($platformMe['branches'], 'code'));
        $this->assertContains('TMS-03', array_column($platformMe['branches'], 'code'));

        $this->postJson('/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->postJson('/login', ['email' => 'manager@demo-tms-01.test', 'password' => 'password'])
            ->assertNoContent();

        $branchMe = $this->getJson('/api/user')->assertOk()->json();

        $this->assertFalse($branchMe['user']['is_platform_admin']);
        $this->assertSame('branch', $branchMe['account_type']);
        $this->assertSame('/app', $branchMe['default_destination']);
        $this->assertSame('TMS-01', $branchMe['branches'][0]['code']);
        $this->assertSame([], $branchMe['platform_permissions']);
        $this->getJson('/api/platform/access')->assertForbidden();

        $this->postJson('/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        User::factory()->create([
            'email' => 'customer@example.test',
            'password' => 'secret123',
        ]);

        $this->postJson('/login', ['email' => 'customer@example.test', 'password' => 'secret123'])
            ->assertNoContent();

        $customerMe = $this->getJson('/api/user')->assertOk()->json();

        $this->assertSame('customer', $customerMe['account_type']);
        $this->assertSame('/account', $customerMe['default_destination']);
        $this->assertSame([], $customerMe['branches']);
    }

    public function test_branch_administrator_credentials_open_each_branch(): void
    {
        $this->withHeaders(['Origin' => 'http://127.0.0.1:5173']);

        config([
            'seeding.admin_email' => 'admin@example.test',
            'seeding.admin_password' => 'secret123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $credentials = [
            ['admin@rusape.tms.local', 'Rusape2026!', 'TMS-01'],
            ['admin@mutare.tms.local', 'Mutare2026!', 'TMS-02'],
            ['admin@gweru.tms.local', 'Gweru2026!', 'TMS-03'],
        ];

        foreach ($credentials as [$email, $password, $code]) {
            $this->postJson('/login', ['email' => $email, 'password' => $password])
                ->assertNoContent();

            $me = $this->getJson('/api/user')->assertOk()->json();

            $this->assertSame($email, $me['user']['email']);
            $this->assertSame('branch', $me['account_type']);
            $this->assertSame('/app', $me['default_destination']);
            $this->assertSame($code, $me['branches'][0]['code']);

            $this->postJson('/logout')->assertNoContent();
            $this->app['auth']->forgetGuards();
            $this->app['auth']->shouldUse('web');
        }
    }
}
