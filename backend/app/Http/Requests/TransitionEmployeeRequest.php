<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionEmployeeRequest extends FormRequest
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
            'status' => ['required', Rule::in(Employee::statuses())],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'revoke_access' => ['sometimes', 'boolean'],
        ];
    }
}
