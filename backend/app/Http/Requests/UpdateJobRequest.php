<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
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
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'vehicle_make' => ['sometimes', 'string', 'max:120'],
            'vehicle_model' => ['sometimes', 'string', 'max:160'],
            'vehicle_registration' => ['nullable', 'string', 'max:60'],
            'vehicle_year' => ['nullable', 'string', 'max:12'],
            'vin' => ['nullable', 'string', 'max:80'],
            'complaint' => ['sometimes', 'string'],
            'diagnosis' => ['nullable', 'string'],
        ];
    }
}
