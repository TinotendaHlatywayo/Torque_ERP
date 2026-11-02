<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\FinanceMetricsService;
use App\Support\FxConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceMetricsController extends Controller
{
    public function __construct(
        private readonly FinanceMetricsService $metrics,
        private readonly FxConversionService $fx,
    ) {}

    public function metrics(Request $request, Branch $branch): JsonResponse
    {
        return response()->json([
            'data' => $this->metrics->metrics($branch, $request->query('as_of')),
        ]);
    }

    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer'],
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'at' => ['nullable', 'date'],
        ]);

        return response()->json([
            'data' => [
                'amount_minor' => $this->fx->convert(
                    $data['amount_minor'],
                    $data['from_currency'],
                    $data['to_currency'],
                    isset($data['at']) ? Carbon::parse($data['at']) : null,
                ),
                'from_currency' => $data['from_currency'],
                'to_currency' => $data['to_currency'],
                'rate' => $this->fx->rate($data['from_currency'], $data['to_currency'], isset($data['at']) ? Carbon::parse($data['at']) : null),
            ],
        ]);
    }
}
