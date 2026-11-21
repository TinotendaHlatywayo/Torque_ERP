export type SalaryGrade = {
  id: string
  name: string
  code: string
  pay_basis: 'monthly' | 'hourly'
  base_rate_minor: number
  overtime_multiplier: string
  overtime_hourly_rate_minor: number | null
  currency_code: string
  is_active: boolean
}

export type EmployeeRef = {
  id: string
  employee_number: string
  full_name: string
}

export type SalaryAssignment = {
  id: string
  employee: EmployeeRef | null
  salary_grade: Pick<SalaryGrade, 'id' | 'name' | 'code' | 'pay_basis' | 'base_rate_minor' | 'currency_code'> | null
  effective_from: string | null
  effective_to: string | null
  notes: string | null
}

export type PayComponent = {
  id: string
  employee: EmployeeRef | null
  type: 'allowance' | 'deduction'
  code: string
  label: string
  amount_minor: number
  effective_from: string | null
  effective_to: string | null
  is_active: boolean
}

export type StatutoryRule = {
  id: string
  name: string
  code: string
  applies_to: 'gross' | 'basic'
  employee_rate: string
  employer_rate: string
  fixed_employee_minor: number
  fixed_employer_minor: number
  effective_from: string | null
  effective_to: string | null
  is_active: boolean
}

export type EmployeeLoan = {
  id: string
  employee: EmployeeRef | null
  principal_minor: number
  balance_minor: number
  installment_minor: number
  status: 'active' | 'settled' | 'cancelled'
  issued_date: string | null
  notes: string | null
  repayments: Array<{
    id: string
    payslip_id: string
    amount_minor: number
    balance_before_minor: number
    balance_after_minor: number
    created_at: string | null
  }>
}

export type PayrollPeriod = {
  id: string
  start_date: string | null
  end_date: string | null
  pay_date: string | null
  status: 'open' | 'locked'
  locked_at: string | null
  latest_run: {
    id: string
    status: 'draft' | 'approved'
    version: number
    gross_minor: number
    deductions_minor: number
    net_minor: number
    warnings: string[]
  } | null
  runs: Array<{
    id: string
    status: 'draft' | 'approved'
    version: number
    gross_minor: number
    deductions_minor: number
    net_minor: number
  }>
}

export type PayslipLine = {
  id: string
  category: 'earning' | 'deduction' | 'statutory_employee' | 'statutory_employer' | 'loan'
  code: string
  label: string
  amount_minor: number
  units: string | null
  rate_minor: number | null
  source_type: string | null
  source_id: string | null
  metadata: Record<string, unknown> | null
}

export type Payslip = {
  id: string
  employee: EmployeeRef | null
  pay_basis: 'monthly' | 'hourly'
  base_rate_minor: number
  base_pay_minor: number
  overtime_minor: number
  allowances_minor: number
  adjustment_earnings_minor: number
  pre_tax_deductions_minor: number
  statutory_employee_minor: number
  loan_deductions_minor: number
  adjustment_deductions_minor: number
  gross_minor: number
  deductions_minor: number
  employer_statutory_minor: number
  net_minor: number
  period_working_days: number
  payable_working_days: number
  regular_minutes: number
  overtime_minutes: number
  breakdown: Record<string, unknown> | null
  lines: PayslipLine[]
}

export type PayrollRun = {
  id: string
  payroll_period_id: string
  kind: string
  version: number
  status: 'draft' | 'approved'
  gross_minor: number
  deductions_minor: number
  employer_statutory_minor: number
  net_minor: number
  warnings: string[]
  approved_at: string | null
  period: {
    id: string
    start_date: string | null
    end_date: string | null
    pay_date: string | null
    status: 'open' | 'locked'
  } | null
  payslips: Payslip[]
}

export type PayrollAdjustment = {
  id: string
  employee: EmployeeRef | null
  source_payslip_id: string | null
  type: 'earning' | 'deduction'
  label: string
  amount_minor: number
  reason: string
  status: 'pending' | 'applied' | 'reversed'
  applied_payslip_id: string | null
  applied_run_id: string | null
  reverses_adjustment_id: string | null
  applied_at: string | null
  reversed_at: string | null
}

export type ListResponse<T> = { data: T[] }
export type RunResponse = { data: PayrollRun }
export type AdjustmentResponse = { data: PayrollAdjustment }

export type EmployeeOption = {
  id: string
  employee_number: string
  full_name: string
  status: string
}

export type EmployeeOptionListResponse = {
  data: EmployeeOption[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type PayrollPayment = {
  id: string
  payment_account_id: string
  account: { id: string; name: string; type: string; currency_code: string } | null
  money_movement_id: string | null
  amount_minor: number
  currency_code: string
  paid_at: string | null
  method: string
  reference: string | null
  status: string
}

export type PayrollReconciliation = {
  payroll_run_id: string
  status: 'PASS' | 'FAIL'
  checks: Record<string, boolean>
  run_totals_minor: { gross: number; deductions: number; employer_statutory: number; net: number }
  payslip_totals_minor: { gross: number; deductions: number; net: number }
  payments_minor: { allocated: number; outstanding: number; money_out: number }
  accrual_minor: number
  currency_code: string
}

export type PaymentAccountRef = {
  id: string
  name: string
  type: string
  currency_code: string
  is_active: boolean
}
