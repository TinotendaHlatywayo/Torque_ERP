<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\PayrollAccrual;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PayrollPaymentService
{
    public function __construct(
        private readonly MoneyMovementService $movements,
        private readonly AuditLogger $audit,
    ) {}

    public function accrue(PayrollRun $run, User $actor): PayrollAccrual
    {
        if ($run->status !== PayrollRun::STATUS_APPROVED) {
            throw ValidationException::withMessages(['payroll_run' => 'Only approved payroll runs can be accrued.']);
        }

        return DB::transaction(function () use ($run, $actor): PayrollAccrual {
            $existing = PayrollAccrual::where('payroll_run_id', $run->id)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            $period = $run->period()->firstOrFail();
            $branch = Branch::whereKey($run->branch_id)->firstOrFail();
            $currencyCode = $branch->currency_code;
            $accruedMinor = $run->gross_minor + $run->employer_statutory_minor;
            $incurredDate = $period->pay_date?->toDateString() ?? $period->end_date?->toDateString() ?? now()->toDateString();

            $expense = $branch->expenses()->create([
                'category' => 'Payroll',
                'description' => 'Payroll accrual run v'.$run->version,
                'amount_minor' => $accruedMinor,
                'currency_code' => $currencyCode,
                'incurred_date' => $incurredDate,
                'status' => 'incurred',
                'actor_id' => $actor->id,
            ]);

            $accrual = $run->accrual()->create([
                'branch_id' => $branch->id,
                'expense_id' => $expense->id,
                'accrued_minor' => $accruedMinor,
                'net_pay_minor' => $run->net_minor,
                'currency_code' => $currencyCode,
                'incurred_date' => $incurredDate,
                'actor_id' => $actor->id,
            ]);

            $this->audit->log('payroll.accrual.created', $accrual, null, [
                'payroll_run_id' => $run->id,
                'expense_id' => $expense->id,
                'accrued_minor' => $accruedMinor,
                'net_pay_minor' => $run->net_minor,
            ], $branch->id, $actor);

            return $accrual;
        }, 3);
    }

    public function allocate(
        Branch $branch,
        PayrollRun $run,
        PaymentAccount $account,
        array $payload,
        User $actor,
        string $idempotencyKey,
    ): PayrollPayment {
        if ($run->branch_id !== $branch->id) {
            abort(404);
        }

        if ($run->status !== PayrollRun::STATUS_APPROVED) {
            throw ValidationException::withMessages(['payroll_run' => 'Payments can only be allocated to an approved run.']);
        }

        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'A non-empty Idempotency-Key header of at most 100 characters is required.']);
        }

        $paidAt = $payload['paid_at'];
        $requestHash = hash('sha256', json_encode([
            'payroll_run_id' => $run->id,
            'payment_account_id' => $account->id,
            'amount_minor' => $payload['amount_minor'],
            'currency_code' => $branch->currency_code,
            'paid_at' => $paidAt,
            'method' => $payload['method'],
            'reference' => $payload['reference'] ?? null,
        ], JSON_THROW_ON_ERROR));

        $existing = PayrollPayment::where('branch_id', $branch->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                throw new ConflictHttpException('The Idempotency-Key has already been used with a different payroll payment.');
            }

            return $existing;
        }

        if ($account->currency_code !== $branch->currency_code) {
            throw ValidationException::withMessages(['payment_account_id' => 'The payment account currency must match the payroll currency.']);
        }

        if ($payload['amount_minor'] <= 0) {
            throw ValidationException::withMessages(['amount_minor' => 'The payment amount must be greater than zero.']);
        }

        $allocate = function () use ($branch, $run, $account, $payload, $actor, $idempotencyKey, $requestHash): PayrollPayment {
            $existing = PayrollPayment::where('branch_id', $branch->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    throw new ConflictHttpException('The Idempotency-Key has already been used with a different payroll payment.');
                }

                return $existing;
            }

            $paidMinor = (int) $run->payments()->sum('amount_minor');
            if ($paidMinor + $payload['amount_minor'] > $run->net_minor) {
                throw ValidationException::withMessages(['amount_minor' => 'Payroll payments cannot exceed the approved net payroll.']);
            }

            $payment = $run->payments()->create([
                'branch_id' => $branch->id,
                'payment_account_id' => $account->id,
                'amount_minor' => $payload['amount_minor'],
                'currency_code' => $branch->currency_code,
                'paid_at' => $payload['paid_at'],
                'method' => $payload['method'],
                'reference' => $payload['reference'] ?? null,
                'status' => PayrollPayment::STATUS_RECORDED,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'actor_id' => $actor->id,
            ]);

            $movement = $this->movements->postMovement(
                $branch,
                $account,
                'adjustment_out',
                $payment->amount_minor,
                $payment->currency_code,
                $payment->reference,
                'Payroll payment for run v'.$run->version,
                'payroll',
                (string) $run->id,
                $actor,
                'payroll-payment:'.$payment->id,
            );

            $payment->update(['money_movement_id' => $movement->id]);

            $this->audit->log('payroll.payment.recorded', $payment, null, [
                'payroll_run_id' => $run->id,
                'amount_minor' => $payment->amount_minor,
                'payment_account_id' => $account->id,
                'money_movement_id' => $movement->id,
            ], $branch->id, $actor);

            return $payment->refresh();
        };

        try {
            return DB::transaction($allocate, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = PayrollPayment::where('branch_id', $branch->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    throw new ConflictHttpException('The Idempotency-Key has already been used with a different payroll payment.', $exception);
                }

                return $existing;
            }

            throw new ConflictHttpException('The payroll payment conflicted with another concurrent request.', $exception);
        }
    }

    public function reconcile(PayrollRun $run): array
    {
        $run->loadMissing(['period', 'accrual', 'payslips', 'payments']);

        $payslipGross = (int) $run->payslips->sum('gross_minor');
        $payslipDeductions = (int) $run->payslips->sum('deductions_minor');
        $payslipNet = (int) $run->payslips->sum('net_minor');
        $paidMinor = (int) $run->payments->sum('amount_minor');
        $moneyOut = (int) MoneyMovement::where('branch_id', $run->branch_id)
            ->where('source', 'payroll')
            ->where('source_id', $run->id)
            ->where('direction', 'out')
            ->sum('amount_minor');

        $checks = [
            'run_gross_matches_payslips' => $run->gross_minor === $payslipGross,
            'run_deductions_match_payslips' => $run->deductions_minor === $payslipDeductions,
            'run_net_matches_payslips' => $run->net_minor === $payslipNet,
            'payments_do_not_exceed_net' => $paidMinor <= $run->net_minor,
            'payments_match_money_movements' => $paidMinor === $moneyOut,
            'approved_run_has_accrual' => $run->status !== PayrollRun::STATUS_APPROVED || $run->accrual !== null,
        ];

        return [
            'payroll_run_id' => $run->id,
            'status' => in_array(false, $checks, true) ? 'FAIL' : 'PASS',
            'checks' => $checks,
            'run_totals_minor' => [
                'gross' => $run->gross_minor,
                'deductions' => $run->deductions_minor,
                'employer_statutory' => $run->employer_statutory_minor,
                'net' => $run->net_minor,
            ],
            'payslip_totals_minor' => [
                'gross' => $payslipGross,
                'deductions' => $payslipDeductions,
                'net' => $payslipNet,
            ],
            'payments_minor' => [
                'allocated' => $paidMinor,
                'outstanding' => max(0, $run->net_minor - $paidMinor),
                'money_out' => $moneyOut,
            ],
            'accrual_minor' => $run->accrual?->accrued_minor ?? 0,
            'currency_code' => $run->accrual?->currency_code ?? Branch::whereKey($run->branch_id)->value('currency_code'),
        ];
    }
}
