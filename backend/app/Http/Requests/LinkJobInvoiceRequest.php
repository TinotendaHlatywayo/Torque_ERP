<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkJobInvoiceRequest extends FormRequest
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
            'sale_id' => ['required', 'string', 'exists:sales,id'],
        ];
    }
}
