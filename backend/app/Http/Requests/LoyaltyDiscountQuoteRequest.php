<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoyaltyDiscountQuoteRequest extends FormRequest
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
            'customer_id' => ['required', 'string'],
            'unit_price_minor' => ['required', 'integer', 'min:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'floor_price_minor' => ['nullable', 'integer', 'min:0'],
            'manual_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approval_reason' => ['nullable', 'string', 'max:500'],
            'product_id' => ['nullable', 'string'],
            'unit_id' => ['nullable', 'string'],
        ];
    }
}
