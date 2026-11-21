<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkshopServiceRequest;
use App\Http\Requests\UpdateWorkshopServiceRequest;
use App\Models\Branch;
use App\Models\WorkshopService;
use App\Support\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopServiceController extends Controller
{
    public function __construct(private readonly JobService $jobs) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $services = $branch->workshopServices()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $services->map(fn (WorkshopService $service): array => $this->payload($service))]);
    }

    public function store(StoreWorkshopServiceRequest $request, Branch $branch): JsonResponse
    {
        $service = $this->jobs->createWorkshopService($branch, $request->user(), $request->validated());

        return response()->json(['data' => $this->payload($service)], 201);
    }

    public function update(UpdateWorkshopServiceRequest $request, Branch $branch, WorkshopService $workshopService): JsonResponse
    {
        abort_unless($workshopService->branch_id === $branch->id, 404);

        $service = $this->jobs->updateWorkshopService($workshopService, $request->user(), $request->validated());

        return response()->json(['data' => $this->payload($service)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WorkshopService $service): array
    {
        return [
            'id' => $service->id,
            'code' => $service->code,
            'name' => $service->name,
            'description' => $service->description,
            'default_hours' => $service->default_hours,
            'default_rate_minor' => $service->default_rate_minor,
            'currency_code' => $service->currency_code,
            'is_active' => $service->is_active,
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }
}
