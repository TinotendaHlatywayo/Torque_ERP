<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobLabourRequest extends FormRequest
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
            'workshop_service_id' => ['nullable', 'string', 'exists:workshop_services,id'],
            'description' => ['required_without:workshop_service_id', 'nullable', 'string', 'max:200'],
            'hours' => ['nullable', 'numeric', 'min:0.01'],
            'rate_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
