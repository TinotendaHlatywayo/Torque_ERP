<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveJobRequest extends FormRequest
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
            'approval_reference' => ['required', 'string', 'max:120'],
            'approval_channel' => ['nullable', 'string', 'max:60'],
        ];
    }
}
