<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DocumentTemplate;
use App\Support\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    public function __construct(private readonly DocumentTemplateService $templates) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $templates = $branch->documentTemplates()
            ->when($request->filled('type'), fn ($query) => $query->where('document_type', $request->query('type')))
            ->orderBy('document_type')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => $templates->map(fn (DocumentTemplate $template) => [
                'id' => $template->id,
                'document_type' => $template->document_type,
                'name' => $template->name,
                'version' => $template->version,
                'is_active' => $template->is_active,
                'supported_placeholders' => $this->templates->allowedPlaceholders($template->document_type),
                'required_placeholders' => $this->templates->requiredPlaceholders($template->document_type),
                'content' => $template->content,
                'created_at' => $template->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:receipt,invoice,supplier_bill'],
            'name' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:100000'],
        ]);

        try {
            $this->templates->validateContent($data['document_type'], $data['content']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $template = $this->templates->createVersion(
            $branch,
            $data['document_type'],
            $data['name'],
            $data['content'],
            $request->user(),
        );

        return response()->json(['data' => [
            'id' => $template->id,
            'document_type' => $template->document_type,
            'name' => $template->name,
            'version' => $template->version,
            'is_active' => $template->is_active,
        ]], 201);
    }

    public function preview(Request $request, Branch $branch, DocumentTemplate $documentTemplate): JsonResponse
    {
        $data = $request->input('data', []);

        if (! is_array($data)) {
            $data = [];
        }

        $template = $documentTemplate;
        $rendered = $this->templates->render($template, array_merge($this->templates->sampleData($template->document_type), $data));

        return response()->json([
            'data' => [
                'template_id' => $template->id,
                'document_type' => $template->document_type,
                'rendered' => $rendered,
                'verification_url' => url('/api/verify/'.$template->document_type.'/sample'),
            ],
        ]);
    }
}
