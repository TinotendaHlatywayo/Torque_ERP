<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PaymentAccount;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Support\PayrollPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollPaymentController extends Controller
{
    public function __construct(private readonly PayrollPaymentService $payments) {}

    public function index(Branch $branch, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($payrollRun->branch_id === $branch->id, 404);

        $payments = $payrollRun->payments()
            ->with(['account:id,name,type,currency_code'])
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (PayrollPayment $payment): array => [
                'id' => $payment->id,
                'payment_account_id' => $payment->payment_account_id,
                'account' => $payment->account ? [
                    'id' => $payment->account->id,
                    'name' => $payment->account->name,
                    'type' => $payment->account->type,
                    'currency_code' => $payment->account->currency_code,
                ] : null,
                'money_movement_id' => $payment->money_movement_id,
                'amount_minor' => $payment->amount_minor,
                'currency_code' => $payment->currency_code,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'status' => $payment->status,
            ]);

        return response()->json(['data' => $payments]);
    }

    public function store(Request $request, Branch $branch, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($payrollRun->branch_id === $branch->id, 404);

        $data = $request->validate([
            'payment_account_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'string', 'max:40', Rule::in(['cash', 'bank', 'mobile_money', 'other'])],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $account = PaymentAccount::where('branch_id', $branch->id)
            ->whereKey($data['payment_account_id'])
            ->firstOrFail();

        $payment = $this->payments->allocate(
            $branch,
            $payrollRun,
            $account,
            $data,
            $request->user(),
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json(['data' => [
            'id' => $payment->id,
            'payroll_run_id' => $payment->payroll_run_id,
            'amount_minor' => $payment->amount_minor,
            'currency_code' => $payment->currency_code,
            'status' => $payment->status,
            'money_movement_id' => $payment->money_movement_id,
        ]], 201);
    }
}
