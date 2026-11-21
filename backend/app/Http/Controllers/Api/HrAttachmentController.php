<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\HrAttachment;
use App\Support\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrAttachmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'attachable_type' => ['required', Rule::in(HrAttachment::TYPES)],
            'attachable_id' => ['required', 'string', 'max:40'],
        ]);

        $target = $this->target($branch, $data['attachable_type'], $data['attachable_id'], $request);

        if ($target === null) {
            abort(404);
        }

        $file = $request->file('file');
        $path = $file->store('hr/'.$branch->id, 'local');

        if (! $path) {
            throw ValidationException::withMessages(['file' => 'The attachment could not be stored.']);
        }

        $attachment = $branch->hrAttachments()->create([
            'employee_id' => $target->employee_id,
            'attachable_type' => $data['attachable_type'],
            'attachable_id' => $target->id,
            'original_name' => basename($file->getClientOriginalName()),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->audit->log(
            'hr_attachment.created',
            $attachment,
            null,
            [
                'attachable_type' => $attachment->attachable_type,
                'attachable_id' => $attachment->attachable_id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ],
            $branch->id,
            $request->user(),
        );

        return response()->json([
            'data' => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function download(Request $request, Branch $branch, HrAttachment $hrAttachment)
    {
        if ($hrAttachment->attachable_type === HrAttachment::TYPE_DISCIPLINARY_RECORD) {
            $branchUser = $request->attributes->get('branch_user');

            if (! $branchUser instanceof BranchUser || ! $branchUser->hasPermission(Permissions::BRANCH_HR_SENSITIVE_VIEW)) {
                abort(403, 'You do not have permission to download this attachment.');
            }
        }

        if (! Storage::disk('local')->exists($hrAttachment->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($hrAttachment->path, $hrAttachment->original_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function target(Branch $branch, string $type, string $id, Request $request)
    {
        if ($type === HrAttachment::TYPE_LEAVE_REQUEST) {
            return $branch->leaveRequests()->findOrFail($id);
        }

        $branchUser = $request->attributes->get('branch_user');

        if (! $branchUser instanceof BranchUser || ! $branchUser->hasPermission(Permissions::BRANCH_HR_SENSITIVE_VIEW)) {
            throw ValidationException::withMessages(['attachable_type' => 'You do not have permission to add attachments to disciplinary records.']);
        }

        return $branch->disciplinaryRecords()->findOrFail($id);
    }
}
