<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'string'],
            'lines.*.unit_id' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.floor_price_minor' => ['nullable', 'integer', 'min:0'],
            'lines.*.tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => ['sometimes', 'array'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,bank_transfer,mobile_money,other'],
            'payments.*.amount_minor' => ['required', 'integer', 'min:1'],
            'payments.*.currency_code' => ['required', 'string', 'size:3'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'discount_override_reason' => ['nullable', 'string', 'max:500'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('lines'))) {
            $lines = [];

            foreach ($this->input('lines') as $line) {
                if (! is_array($line)) {
                    $lines[] = $line;

                    continue;
                }

                $lines[] = [
                    'product_id' => $line['product_id'] ?? null,
                    'unit_id' => $line['unit_id'] ?? null,
                    'quantity' => $line['quantity'] ?? null,
                    'discount_percent' => $line['discount_percent'] ?? 0,
                    'floor_price_minor' => $line['floor_price_minor'] ?? 0,
                    'tax_rate_percent' => $line['tax_rate_percent'] ?? 0,
                ];
            }

            $this->merge(['lines' => $lines]);
        }
    }
}
