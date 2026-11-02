<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentVerificationController extends Controller
{
    public function verify(Request $request, string $documentType, string $documentId): JsonResponse
    {
        if ($documentType === 'receipt' || $documentType === 'invoice') {
            $sale = Sale::find($documentId);

            if (! $sale) {
                return response()->json(['valid' => false, 'message' => 'Document not found.'], 404);
            }

            return response()->json([
                'valid' => $sale->status !== 'voided',
                'document_type' => $documentType,
                'document_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'receipt_number' => $sale->receipt?->receipt_number,
                'status' => $sale->status,
                'total_minor' => $sale->total_minor,
                'currency_code' => $sale->currency_code,
                'issued_at' => $sale->created_at?->toIso8601String(),
                'verification_url' => $request->fullUrl(),
            ]);
        }

        return response()->json([
            'valid' => false,
            'message' => 'Verification is not implemented for this document type.',
        ], 404);
    }
}
