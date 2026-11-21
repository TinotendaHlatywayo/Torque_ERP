<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
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
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'vehicle_make' => ['required', 'string', 'max:120'],
            'vehicle_model' => ['required', 'string', 'max:160'],
            'vehicle_registration' => ['nullable', 'string', 'max:60'],
            'vehicle_year' => ['nullable', 'string', 'max:12'],
            'vin' => ['nullable', 'string', 'max:80'],
            'complaint' => ['required', 'string'],
        ];
    }
}
