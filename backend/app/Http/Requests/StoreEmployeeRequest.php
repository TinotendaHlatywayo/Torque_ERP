<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'first_name' => is_string($this->input('first_name')) ? trim($this->input('first_name')) : $this->input('first_name'),
            'last_name' => is_string($this->input('last_name')) ? trim($this->input('last_name')) : $this->input('last_name'),
            'employee_number' => is_string($this->input('employee_number')) && trim($this->input('employee_number')) !== ''
                ? strtoupper(trim($this->input('employee_number')))
                : null,
            'email' => is_string($this->input('email')) && trim($this->input('email')) !== ''
                ? strtolower(trim($this->input('email')))
                : null,
            'phone' => is_string($this->input('phone')) && trim($this->input('phone')) !== ''
                ? trim($this->input('phone'))
                : null,
            'job_title' => is_string($this->input('job_title')) && trim($this->input('job_title')) !== ''
                ? trim($this->input('job_title'))
                : null,
            'department' => is_string($this->input('department')) && trim($this->input('department')) !== ''
                ? trim($this->input('department'))
                : null,
            'national_id' => is_string($this->input('national_id')) && trim($this->input('national_id')) !== ''
                ? trim($this->input('national_id'))
                : null,
            'address' => is_string($this->input('address')) && trim($this->input('address')) !== ''
                ? trim($this->input('address'))
                : null,
            'emergency_contact_name' => is_string($this->input('emergency_contact_name')) && trim($this->input('emergency_contact_name')) !== ''
                ? trim($this->input('emergency_contact_name'))
                : null,
            'emergency_contact_phone' => is_string($this->input('emergency_contact_phone')) && trim($this->input('emergency_contact_phone')) !== ''
                ? trim($this->input('emergency_contact_phone'))
                : null,
            'notes' => is_string($this->input('notes')) && trim($this->input('notes')) !== ''
                ? trim($this->input('notes'))
                : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'employee_number' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('employees', 'employee_number')->where('branch_id', $branch->id),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('employees', 'user_id')->where('branch_id', $branch->id),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:160'],
            'employment_type' => ['sometimes', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'hire_date' => ['required', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status' => ['sometimes', Rule::in([Employee::STATUS_HIRED, Employee::STATUS_ACTIVE])],
            'national_id' => ['nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:5000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->has('user_id')) {
                    return;
                }

                $userId = $this->input('user_id');

                if (! $userId) {
                    return;
                }

                $user = User::find($userId);

                if (! $user) {
                    return;
                }

                if (! $user->is_active) {
                    $validator->errors()->add('user_id', 'The linked user account is inactive.');
                }
            },
        ];
    }
}
