<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Platform;
use App\Models\ReportExport;
use App\Support\AuditLogger;
use App\Support\FinanceMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlatformReportController extends Controller
{
    public function __construct(
        private readonly FinanceMetricsService $metrics,
        private readonly AuditLogger $audit,
    ) {}

    public function branchComparison(Request $request, Platform $platform): JsonResponse
    {
        $branches = Branch::where('platform_id', $platform->id)
            ->orderBy('name')
            ->get();

        $data = $branches->map(fn (Branch $branch) => array_merge(
            ['branch_id' => $branch->id, 'branch_name' => $branch->name, 'branch_code' => $branch->code, 'status' => $branch->status],
            $this->metrics->metrics($branch, $request->query('as_of')),
        ))->all();

        return response()->json(['data' => $data]);
    }

    public function export(Request $request, Platform $platform): JsonResponse
    {
        $branches = Branch::where('platform_id', $platform->id)->orderBy('name')->get();
        $rows = [];

        foreach ($branches as $branch) {
            $metrics = $this->metrics->metrics($branch, $request->query('as_of'));
            $rows[] = [
                $branch->id,
                $branch->name,
                $branch->code,
                $branch->status,
                $metrics['money_in_minor'],
                $metrics['money_out_minor'],
                $metrics['invoiced_revenue_minor'],
                $metrics['money_owed_to_us_minor'],
                $metrics['bills_to_pay_minor'],
                $metrics['cash_position_minor'],
            ];
        }

        $fileName = 'platform-report-'.$platform->id.'-'.now()->format('YmdHis').'.csv';
        $path = 'private/exports/'.$fileName;

        $csv = implode(',', [
            'branch_id', 'branch_name', 'branch_code', 'status', 'money_in_minor',
            'money_out_minor', 'invoiced_revenue_minor', 'money_owed_to_us_minor',
            'bills_to_pay_minor', 'cash_position_minor',
        ])."\n";

        foreach ($rows as $row) {
            $csv .= implode(',', $row)."\n";
        }

        Storage::disk('local')->put($path, $csv);

        $export = ReportExport::create([
            'scope_type' => 'platform',
            'scope_id' => $platform->id,
            'report_type' => 'branch_comparison',
            'file_path' => $path,
            'expires_at' => now()->addHours(24),
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log(
            'report.exported',
            $export,
            null,
            [
                'platform_id' => $platform->id,
                'report_type' => 'branch_comparison',
                'expires_at' => $export->expires_at?->toIso8601String(),
            ],
            null,
            $request->user(),
        );

        return response()->json([
            'data' => [
                'id' => $export->id,
                'download_url' => url('/api/platform/report-exports/'.$export->id.'/download'),
                'expires_at' => $export->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function download(Request $request, ReportExport $reportExport)
    {
        $export = $reportExport;
        if ($export->expires_at->isPast()) {
            abort(410, 'This export has expired.');
        }

        if (! Storage::disk('local')->exists($export->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($export->file_path);
    }
}
