<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\BranchUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            abort(401, 'Account is inactive.');
        }

        $routeBranch = $request->route('branch');
        $routeBranchId = $routeBranch instanceof Branch ? $routeBranch->getKey() : $routeBranch;
        $headerBranchId = $request->header('X-Branch-Id');

        // The route branch is authoritative. A conflicting header is never used
        // to widen access, and both are checked against server-side membership.
        $explicitBranchId = $routeBranchId ?: $headerBranchId;

        if ($explicitBranchId) {
            $branch = Branch::find($explicitBranchId);
            $branchUser = $branch
                ? BranchUser::with('branch')
                    ->where('branch_id', $branch->id)
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first()
                : null;

            if (! $branchUser) {
                abort(403, 'You do not have access to this branch.');
            }

            $request->attributes->set('branch_id', $branch->id);
            $request->attributes->set('branch_user', $branchUser);
            $request->attributes->set('branch', $branch);

            if (in_array($branch->status, [Branch::STATUS_SUSPENDED, Branch::STATUS_ARCHIVED], true)
                && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                abort(403, 'This branch is suspended and cannot accept writes.');
            }

            return $next($request);
        }

        $branchUser = BranchUser::with('branch')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        if ($branchUser && $branchUser->branch) {
            $request->attributes->set('branch_id', $branchUser->branch_id);
            $request->attributes->set('branch_user', $branchUser);
            $request->attributes->set('branch', $branchUser->branch);
        }

        return $next($request);
    }
}
