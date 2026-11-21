<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\AuditLogger;
use App\Support\CatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatalogueImportController extends Controller
{
    public function __construct(
        private readonly CatalogueService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function storeFitments(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:200'],
            'source_ref' => ['required', 'string', 'max:200'],
            'format' => ['required', 'string', 'in:json'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.product_id' => ['required', 'string'],
            'rows.*.make' => ['required', 'string', 'max:120'],
            'rows.*.model' => ['required', 'string', 'max:160'],
            'rows.*.year_from' => ['nullable', 'string', 'max:12'],
            'rows.*.year_to' => ['nullable', 'string', 'max:12'],
            'rows.*.engine' => ['nullable', 'string', 'max:160'],
            'rows.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Idempotency-Key header is required.']);
        }

        $result = $this->service->importFitments(
            $branch,
            $data['rows'],
            $data['source'],
            $data['source_ref'],
            $data['format'],
            $idempotencyKey,
            $request->user()?->id,
        );

        $this->audit->log(
            'catalogue.fitments_imported',
            $branch,
            null,
            [
                'source' => $data['source'],
                'source_ref' => $data['source_ref'],
                'format' => $data['format'],
                'idempotency_key' => $idempotencyKey,
                ...$result,
            ],
            $branch->id,
            $request->user(),
        );

        return response()->json(['data' => $result], 201);
    }

    public function storeOemRefs(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:200'],
            'source_ref' => ['required', 'string', 'max:200'],
            'format' => ['required', 'string', 'in:json'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.product_id' => ['required', 'string'],
            'rows.*.oem_number' => ['required', 'string', 'max:120'],
            'rows.*.brand' => ['nullable', 'string', 'max:120'],
            'rows.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Idempotency-Key header is required.']);
        }

        $result = $this->service->importOemRefs(
            $branch,
            $data['rows'],
            $data['source'],
            $data['source_ref'],
            $data['format'],
            $idempotencyKey,
            $request->user()?->id,
        );

        $this->audit->log(
            'catalogue.oem_imported',
            $branch,
            null,
            [
                'source' => $data['source'],
                'source_ref' => $data['source_ref'],
                'format' => $data['format'],
                'idempotency_key' => $idempotencyKey,
                ...$result,
            ],
            $branch->id,
            $request->user(),
        );

        return response()->json(['data' => $result], 201);
    }
}
