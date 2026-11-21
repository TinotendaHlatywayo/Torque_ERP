<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformBranchSessionController extends Controller
{
    private const IMPERSONATOR_ID = 'platform.impersonator_id';

    private const IMPERSONATED_USER_ID = 'platform.impersonated_user_id';

    private const IMPERSONATED_BRANCH_ID = 'platform.impersonated_branch_id';

    public function __construct(private readonly AuditLogger $audit) {}

    public function impersonate(Request $request, Branch $branch): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->is_platform_admin) {
            abort(403, 'Only platform administrators can open a branch workspace.');
        }

        if (! $request->hasSession()) {
            abort(403, 'A browser session is required to open a branch workspace.');
        }

        $session = $request->session();

        if ($session->has(self::IMPERSONATOR_ID)) {
            return response()->json(['message' => 'Return to the platform before opening another branch.'], 409);
        }

        if ($branch->status !== Branch::STATUS_ACTIVE) {
            return response()->json(['message' => 'Only active branches can be opened.'], 422);
        }

        $target = User::query()
            ->whereHas('activeBranchMemberships', function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id)
                    ->where('role', Roles::ADMIN);
            })
            ->where('is_active', true)
            ->where('is_platform_admin', false)
            ->orderBy('created_at')
            ->first();

        if (! $target instanceof User) {
            $email = 'branch-admin-'.$branch->slug.'@tms.local';

            $target = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Branch Administrator - '.$branch->name,
                    'password' => Hash::make(Str::password(32)),
                    'is_active' => true,
                    'is_platform_admin' => false,
                ]
            );

            BranchUser::firstOrCreate(
                ['branch_id' => $branch->id, 'user_id' => $target->id],
                [
                    'role' => Roles::ADMIN,
                    'is_active' => true,
                ]
            );
        }

        $session->put([
            self::IMPERSONATOR_ID => $admin->id,
            self::IMPERSONATED_USER_ID => $target->id,
            self::IMPERSONATED_BRANCH_ID => $branch->id,
        ]);

        Auth::guard('web')->login($target);
        $session->regenerate();

        $this->audit->log(
            'platform.branch.impersonated',
            $branch,
            null,
            [
                'platform_user_id' => $admin->id,
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_user_id' => $target->id,
            ],
            $branch->id,
            $admin,
        );

        return response()->json([
            'data' => [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'user' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'email' => $target->email,
                ],
            ],
        ]);
    }

    public function returnToPlatform(Request $request): JsonResponse
    {
        if (! $request->hasSession()) {
            abort(403, 'A browser session is required to return to the platform.');
        }

        $session = $request->session();
        $impersonatorId = $session->get(self::IMPERSONATOR_ID);
        $impersonatedUserId = $session->get(self::IMPERSONATED_USER_ID);

        if ($impersonatorId === null || $impersonatedUserId === null) {
            abort(403, 'There is no branch session to return from.');
        }

        if ((string) $request->user()->id !== (string) $impersonatedUserId) {
            abort(403, 'This branch session belongs to another user.');
        }

        $admin = User::query()
            ->whereKey($impersonatorId)
            ->where('is_active', true)
            ->where('is_platform_admin', true)
            ->first();

        if (! $admin instanceof User) {
            abort(403, 'The original platform account is no longer available.');
        }

        $branchId = $session->get(self::IMPERSONATED_BRANCH_ID);

        $session->forget([
            self::IMPERSONATOR_ID,
            self::IMPERSONATED_USER_ID,
            self::IMPERSONATED_BRANCH_ID,
        ]);

        Auth::guard('web')->login($admin);
        $session->regenerate();

        $this->audit->log(
            'platform.branch.impersonation_ended',
            'platform.branch_session',
            null,
            [
                'platform_user_id' => $admin->id,
                'branch_id' => $branchId,
            ],
            $branchId,
            $admin,
        );

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
            ],
        ]);
    }
}
