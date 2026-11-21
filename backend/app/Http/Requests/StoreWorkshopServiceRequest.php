<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkshopServiceRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'default_hours' => ['required', 'numeric', 'min:0.01'],
            'default_rate_minor' => ['required', 'integer', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
