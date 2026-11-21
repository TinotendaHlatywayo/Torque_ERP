<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\CatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueSearchController extends Controller
{
    public function __construct(private readonly CatalogueService $service) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'make' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:160'],
            'year' => ['nullable', 'string', 'max:12'],
            'engine' => ['nullable', 'string', 'max:160'],
            'oem' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $this->service->search($branch, $data);

        return response()->json($result);
    }
}
