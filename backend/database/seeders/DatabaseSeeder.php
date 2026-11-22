<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Platform::firstOrCreate(
            ['slug' => 'tms-rusape'],
            [
                'name' => 'Taziva Motor Spares Platform',
                'currency_code' => 'USD',
            ]
        );

        $branches = collect(config('seeding.branches'))
            ->map(fn (array $definition): Branch => $this->ensureBranch($platform, $definition))
            ->all();

        $email = config('seeding.admin_email');
        $password = config('seeding.admin_password');

        if (! $password && app()->environment('production')) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD must be set before seeding production.');
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password ?: 'admin123'),
                'is_active' => true,
                'is_platform_admin' => true,
            ]
        );

        $user->forceFill([
            'is_active' => true,
            'is_platform_admin' => true,
        ])->save();

        foreach ($branches as $branch) {
            $this->ensureMembership($branch, $user);
            $this->ensureBranchAdministrator($branch);
        }
    }

    private function ensureBranch(Platform $platform, array $definition): Branch
    {
        $branch = Branch::firstOrCreate(
            ['code' => $definition['code']],
            [
                'platform_id' => $platform->id,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'timezone' => 'Africa/Harare',
                'currency_code' => 'USD',
                'status' => Branch::STATUS_ACTIVE,
                'approved_at' => now(),
            ]
        );

        $branch->forceFill([
            'platform_id' => $platform->id,
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'status' => Branch::STATUS_ACTIVE,
            'approved_at' => $branch->approved_at ?? now(),
        ])->save();

        return $branch;
    }

    private function ensureBranchAdministrator(Branch $branch): void
    {
        $credentials = config('seeding.branch_admins.'.$branch->slug);

        if (! is_array($credentials)) {
            return;
        }

        $target = User::firstOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => 'Branch Administrator - '.$branch->name,
                'password' => Hash::make($credentials['password']),
                'is_active' => true,
                'is_platform_admin' => false,
            ]
        );

        $target->forceFill([
            'name' => 'Branch Administrator - '.$branch->name,
            'password' => Hash::make($credentials['password']),
            'is_active' => true,
            'is_platform_admin' => false,
        ])->save();

        $this->ensureMembership($branch, $target);
    }

    private function ensureMembership(Branch $branch, User $user): void
    {
        $membership = BranchUser::firstOrCreate(
            ['branch_id' => $branch->id, 'user_id' => $user->id],
            [
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $membership->forceFill([
            'role' => 'admin',
            'is_active' => true,
        ])->save();
    }
}
