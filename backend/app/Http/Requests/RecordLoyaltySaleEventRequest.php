<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordLoyaltySaleEventRequest extends FormRequest
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
            'event_key' => ['required', 'string', 'max:120'],
            'total_minor' => ['required', 'integer', 'min:0'],
            'occurred_at' => ['required', 'date'],
            'source_ref' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'in:sale,import'],
        ];
    }
}
