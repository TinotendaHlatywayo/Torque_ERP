<?php

namespace App\Support;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SyncOperationProcessor
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly SaleCollectionService $collections,
        private readonly StockLedger $stockLedger,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{operation_type: string, idempotency_key: string, payload: array<string, mixed>}  $operation
     * @return array{status: string, result: array<string, mixed>|null, error: string|null, error_class: string|null}
     */
    public function process(Branch $branch, User $actor, array $operation): array
    {
        try {
            $result = match ($operation['operation_type']) {
                'checkout' => $this->checkout($branch, $actor, $operation),
                'customer_create' => $this->createCustomer($branch, $actor, $operation),
                'collection' => $this->collectPayment($branch, $actor, $operation),
                'stock_movement' => $this->postStockMovement($branch, $actor, $operation),
                default => throw ValidationException::withMessages([
                    'operation_type' => 'Unsupported operation type.',
                ]),
            };

            return [
                'status' => 'completed',
                'result' => $result,
                'error' => null,
                'error_class' => null,
            ];
        } catch (Throwable $throwable) {
            return [
                'status' => 'failed',
                'result' => null,
                'error' => $this->errorMessage($throwable),
                'error_class' => substr(class_basename($throwable), 0, 40),
            ];
        }
    }

    /**
     * @param  array{idempotency_key: string, payload: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    private function checkout(Branch $branch, User $actor, array $operation): array
    {
        $data = $this->validate($operation['payload'], (new CheckoutRequest)->rules());
        $sale = $this->checkout->checkout($branch, $actor, $data, $operation['idempotency_key']);

        return [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'payment_ids' => $sale->payments->pluck('id')->all(),
        ];
    }

    /**
     * @param  array{payload: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    private function createCustomer(Branch $branch, User $actor, array $operation): array
    {
        $data = $this->validate(
            $this->normalizeCustomerPayload($operation['payload']),
            (new StoreCustomerRequest)->rules(),
        );

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
            $this->customerSnapshot($customer),
            $branch->id,
            $actor,
        );

        return ['customer_id' => $customer->id];
    }

    /**
     * @param  array{idempotency_key: string, payload: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    private function collectPayment(Branch $branch, User $actor, array $operation): array
    {
        $data = $this->validate($operation['payload'], [
            'sale_id' => ['required', 'string'],
            'payment_account_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:cash,card,bank_transfer,mobile_money,other'],
            'currency_code' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $sale = Sale::where('branch_id', $branch->id)
            ->whereKey($data['sale_id'])
            ->first();

        if (! $sale) {
            throw ValidationException::withMessages([
                'sale_id' => 'The sale must belong to this branch.',
            ]);
        }

        $this->collections->collect($branch, $sale, $actor, $data, $operation['idempotency_key']);

        $payment = Payment::where('branch_id', $branch->id)
            ->where('sale_id', $sale->id)
            ->where('idempotency_key', $operation['idempotency_key'])
            ->first();

        return [
            'sale_id' => $sale->id,
            'payment_id' => $payment?->id,
        ];
    }

    /**
     * @param  array{idempotency_key: string, payload: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    private function postStockMovement(Branch $branch, User $actor, array $operation): array
    {
        $rules = (new StoreStockMovementRequest)->rules();
        unset($rules['idempotency_key']);

        $data = $this->validate($operation['payload'], array_merge([
            'product_id' => ['required', 'string'],
        ], $rules));

        $product = Product::where('branch_id', $branch->id)
            ->whereKey($data['product_id'])
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'The product must belong to this branch.',
            ]);
        }

        $result = $data['type'] === StockMovement::TYPE_OPENING
            ? $this->stockLedger->postOpening(
                $branch,
                $product,
                (string) $data['quantity'],
                (string) $data['unit_id'],
                $data['reason'] ?? null,
                $actor,
                $operation['idempotency_key'],
            )
            : $this->stockLedger->postCorrection(
                $branch,
                $product,
                (string) $data['quantity'],
                (string) $data['unit_id'],
                (string) $data['reason'],
                $actor,
                $operation['idempotency_key'],
            );

        return ['stock_movement_id' => $result->movement->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeCustomerPayload(array $payload): array
    {
        return [
            'name' => $payload['name'] ?? null,
            'phone' => is_string($payload['phone'] ?? null) && trim($payload['phone']) !== ''
                ? trim($payload['phone'])
                : null,
            'email' => is_string($payload['email'] ?? null) && trim($payload['email']) !== ''
                ? strtolower(trim($payload['email']))
                : null,
            'address' => is_string($payload['address'] ?? null) && trim($payload['address']) !== ''
                ? trim($payload['address'])
                : null,
            'notes' => is_string($payload['notes'] ?? null) && trim($payload['notes']) !== ''
                ? trim($payload['notes'])
                : null,
            'is_active' => $payload['is_active'] ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerSnapshot(Customer $customer): array
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $payload, array $rules): array
    {
        return Validator::make($payload, $rules)->validate();
    }

    private function errorMessage(Throwable $throwable): string
    {
        if ($throwable instanceof ValidationException) {
            $messages = [];

            foreach ($throwable->errors() as $field => $fieldMessages) {
                foreach ($fieldMessages as $message) {
                    $messages[] = $field.': '.$message;
                }
            }

            return Str::limit(implode('; ', $messages), 2000);
        }

        return Str::limit($throwable->getMessage() ?: class_basename($throwable), 2000);
    }
}
