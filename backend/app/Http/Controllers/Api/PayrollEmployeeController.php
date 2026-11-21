<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class PayrollEmployeeController extends Controller
{
    public function index(Branch $branch): JsonResponse
    {
        $employees = $branch->employees()
            ->whereIn('status', [Employee::STATUS_HIRED, Employee::STATUS_ACTIVE, Employee::STATUS_TERMINATED])
            ->orderBy('employee_number')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => trim($employee->first_name.' '.$employee->last_name),
                'status' => $employee->status,
            ]);

        return response()->json(['data' => $employees]);
    }
}
