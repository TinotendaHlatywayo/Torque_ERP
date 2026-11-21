<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        foreach (['email', 'phone', 'job_title', 'department', 'national_id', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower((string) $this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('employees', 'user_id')
                    ->where('branch_id', $employee->branch_id)
                    ->ignore($employee->id),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'department' => ['sometimes', 'nullable', 'string', 'max:160'],
            'employment_type' => ['sometimes', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'hire_date' => ['sometimes', 'date'],
            'termination_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:hire_date'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                $userId = $this->input('user_id');

                if (! $userId || $validator->errors()->has('user_id')) {
                    return;
                }

                $user = User::find($userId);

                if ($user && ! $user->is_active) {
                    $validator->errors()->add('user_id', 'The linked user account is inactive.');
                }
            },
        ];
    }
}
