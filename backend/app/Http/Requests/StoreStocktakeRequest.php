<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStocktakeRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'string', 'exists:products,id'],
            'lines.*.unit_id' => ['required', 'string', 'exists:units,id'],
            'lines.*.counted_quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
