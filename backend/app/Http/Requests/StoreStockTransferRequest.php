<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
        $source = $this->route('branch');

        return [
            'destination_branch_id' => [
                'required',
                'string',
                'exists:branches,id',
                function (string $attribute, mixed $value, callable $fail) use ($source): void {
                    if ($source instanceof Branch && $value === $source->id) {
                        $fail('The destination branch must be different from the source branch.');
                    }
                },
            ],
            'product_id' => ['required', 'string', 'exists:products,id'],
            'unit_id' => ['required', 'string', 'exists:units,id'],
            'destination_product_id' => ['required', 'string', 'exists:products,id'],
            'destination_unit_id' => ['required', 'string', 'exists:units,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
