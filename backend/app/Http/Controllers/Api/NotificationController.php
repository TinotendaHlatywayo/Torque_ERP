<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\NotificationDelivery;
use App\Support\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $deliveries = NotificationDelivery::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->with('task:id,title,status')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $deliveries->map(fn (NotificationDelivery $delivery): array => $this->payload($delivery))]);
    }

    public function markRead(Request $request, Branch $branch, NotificationDelivery $notification): JsonResponse
    {
        abort_unless($notification->branch_id === $branch->id && $notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['data' => $this->payload($notification->refresh())]);
    }

    public function preferences(Request $request, Branch $branch): JsonResponse
    {
        return response()->json(['data' => $this->notifications->preferences($branch, $request->user())]);
    }

    public function updatePreferences(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.event_type' => ['required', Rule::in(NotificationService::events())],
            'preferences.*.channel' => ['required', Rule::in(NotificationService::CHANNELS)],
            'preferences.*.is_enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->notifications->updatePreferences($branch, $request->user(), $data['preferences']),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(NotificationDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'task_id' => $delivery->task_id,
            'task' => $delivery->relationLoaded('task') ? $delivery->task : null,
            'event_type' => $delivery->event_type,
            'channel' => $delivery->channel,
            'title' => $delivery->title,
            'body' => $delivery->body,
            'status' => $delivery->status,
            'attempts' => $delivery->attempts,
            'last_error' => $delivery->last_error,
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'read_at' => $delivery->read_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }
}
