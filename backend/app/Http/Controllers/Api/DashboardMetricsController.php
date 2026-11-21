<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\DashboardMetricsService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DashboardMetricsController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
    ) {}

    public function show(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $membership = $request->attributes->get('branch_user');
        $canViewInventory = $membership?->hasPermission(Permissions::BRANCH_INVENTORY_VIEW) ?? false;
        $canViewFinance = $membership?->hasPermission(Permissions::BRANCH_FINANCE_VIEW) ?? false;

        if (! $canViewInventory && ! $canViewFinance) {
            throw new AccessDeniedHttpException('You do not have permission to view dashboard metrics.');
        }

        return response()->json([
            'data' => $this->metrics->metrics($branch, $data['as_of'] ?? null, [
                'inventory' => $canViewInventory,
                'finance' => $canViewFinance,
            ]),
        ]);
    }
}
