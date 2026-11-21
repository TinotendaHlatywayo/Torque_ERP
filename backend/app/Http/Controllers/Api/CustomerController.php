<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true)
            ? $request->query('status')
            : 'active';

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $search = trim((string) $request->query('q', ''));

        $customers = $branch->customers()
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $customers->getCollection()
                ->map(fn (Customer $customer): array => $this->payload($customer)),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request, Branch $branch): JsonResponse
    {
        $data = $request->validated();
        $duplicates = $this->duplicateCandidates($branch, $data);

        $customer = $branch->customers()->create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log(
            'customer.created',
            $customer,
            null,
            $this->snapshot($customer),
            $branch->id,
            $request->user(),
        );

        return response()->json([
            'data' => $this->payload($customer),
            'duplicate_warning' => $duplicates->isNotEmpty(),
            'duplicate_candidates' => $duplicates
                ->map(fn (Customer $candidate): array => $this->payload($candidate))
                ->values()
                ->all(),
        ], 201);
    }

    public function show(Branch $branch, Customer $customer): JsonResponse
    {
        return response()->json(['data' => $this->payload($customer)]);
    }

    public function update(UpdateCustomerRequest $request, Branch $branch, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        $before = $this->snapshot($customer);

        $customer->update($data);

        $this->audit->log(
            'customer.updated',
            $customer->refresh(),
            $before,
            $this->snapshot($customer),
            $branch->id,
            $request->user(),
        );

        return response()->json(['data' => $this->payload($customer->refresh())]);
    }

    public function destroy(Request $request, Branch $branch, Customer $customer): Response
    {
        $before = $this->snapshot($customer);

        $customer->update(['is_active' => false]);

        $this->audit->log(
            'customer.archived',
            $customer->refresh(),
            $before,
            $this->snapshot($customer),
            $branch->id,
            $request->user(),
        );

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Customer>
     */
    private function duplicateCandidates(Branch $branch, array $data): Collection
    {
        $name = mb_strtolower(trim((string) ($data['name'] ?? '')));
        $phone = $this->normalizePhone((string) ($data['phone'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

        return $branch->customers()
            ->where(function ($query) use ($name, $phone, $email): void {
                if ($name !== '') {
                    $query->whereRaw('LOWER(name) = ?', [$name]);
                }

                if ($phone !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', '') = ?", [$phone]);
                }

                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->limit(10)
            ->get();
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'notes' => $customer->notes,
            'is_active' => $customer->is_active,
            'marketing_consent_at' => $customer->marketing_consent_at?->toIso8601String(),
            'marketing_channel' => $customer->marketing_channel,
            'marketing_channel_active' => $customer->marketing_channel_active,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'notes' => $customer->notes,
            'is_active' => $customer->is_active,
            'marketing_consent_at' => $customer->marketing_consent_at?->toIso8601String(),
            'marketing_channel' => $customer->marketing_channel,
            'marketing_channel_active' => $customer->marketing_channel_active,
        ];
    }
}
