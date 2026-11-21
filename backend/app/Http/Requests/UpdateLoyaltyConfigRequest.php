<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoyaltyConfigRequest extends FormRequest
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
            'inactivity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'min_qualifying_minor' => ['required', 'integer', 'min:0'],
            'min_qualifying_sales' => ['required', 'integer', 'min:1'],
            'loyalty_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'combined_approval_threshold_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
