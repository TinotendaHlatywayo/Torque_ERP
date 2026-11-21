import { FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import {
  AdjustmentResponse,
  EmployeeLoan,
  ListResponse,
  PayComponent,
  PayrollAdjustment,
  EmployeeOption as PayrollEmployeeRef,
  PayrollPayment,
  PayrollPeriod,
  PayrollReconciliation,
  PaymentAccountRef,
  PayrollRun,
  RunResponse,
  SalaryAssignment,
  SalaryGrade,
  StatutoryRule,
} from '@/lib/payroll'

type Tab = 'runs' | 'setup' | 'loans' | 'adjustments'
type Feedback = { type: 'error' | 'success'; text: string }

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm'
const today = new Date().toISOString().slice(0, 10)

const emptyPeriodForm = { start_date: today, end_date: today, pay_date: today }
const emptyGradeForm = { name: '', code: '', pay_basis: 'monthly', base_rate: '', overtime_multiplier: '1.50', overtime_hourly_rate: '' }
const emptyAssignmentForm = { employee_id: '', salary_grade_id: '', effective_from: today }
const emptyComponentForm = { employee_id: '', type: 'allowance', code: '', label: '', amount: '', effective_from: today }
const emptyRuleForm = { name: '', code: '', applies_to: 'gross', employee_rate: '', employer_rate: '', effective_from: today }
const emptyLoanForm = { employee_id: '', principal: '', installment: '', issued_date: today }
const emptyAdjustmentForm = { payslip_id: '', type: 'earning', label: '', amount: '', reason: '' }
const emptyPaymentForm = { payment_account_id: '', amount: '', paid_at: today, method: 'bank', reference: '' }

function money(minor: number): string {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(minor / 100)
}

function toMinor(value: string): number {
  return Math.round(Number(value || 0) * 100)
}

function StatusPill({ status }: { status: string }) {
  const tone = status === 'approved' || status === 'locked' || status === 'applied' || status === 'settled'
    ? 'bg-green-600/10 text-green-700 border-green-600/30'
    : status === 'draft' || status === 'open' || status === 'pending' || status === 'active'
      ? 'bg-blue-600/10 text-blue-700 border-blue-600/30'
      : 'bg-muted text-muted-foreground border-border'

  return <span className={`rounded-full border px-2 py-0.5 text-xs capitalize ${tone}`}>{status}</span>
}

export default function PayrollPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.payroll.view') ?? false
  const canManage = branch?.permissions.includes('branch.payroll.manage') ?? false
  const canApprove = branch?.permissions.includes('branch.payroll.approve') ?? false

  const [tab, setTab] = useState<Tab>('runs')
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [selectedRunId, setSelectedRunId] = useState<string | null>(null)
  const [periodForm, setPeriodForm] = useState(emptyPeriodForm)
  const [gradeForm, setGradeForm] = useState(emptyGradeForm)
  const [assignmentForm, setAssignmentForm] = useState(emptyAssignmentForm)
  const [componentForm, setComponentForm] = useState(emptyComponentForm)
  const [ruleForm, setRuleForm] = useState(emptyRuleForm)
  const [loanForm, setLoanForm] = useState(emptyLoanForm)
  const [adjustmentForm, setAdjustmentForm] = useState(emptyAdjustmentForm)
  const [paymentForm, setPaymentForm] = useState(emptyPaymentForm)

  const employeesQuery = useQuery({
    queryKey: ['payroll-employees', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<PayrollEmployeeRef>>(`/api/branches/${branchId}/payroll-employees`)
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const periodsQuery = useQuery({
    queryKey: ['payroll-periods', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<PayrollPeriod>>(`/api/branches/${branchId}/payroll-periods`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'runs'),
  })

  const runQuery = useQuery({
    queryKey: ['payroll-run', branchId, selectedRunId],
    queryFn: async () => {
      const response = await api.get<RunResponse>(`/api/branches/${branchId}/payroll-runs/${selectedRunId}`)
      return response.data
    },
    enabled: Boolean(branchId && canView && selectedRunId && tab === 'runs'),
  })

  const paymentsQuery = useQuery({
    queryKey: ['payroll-payments', branchId, selectedRunId],
    queryFn: async () => {
      const response = await api.get<ListResponse<PayrollPayment>>(`/api/branches/${branchId}/payroll-runs/${selectedRunId}/payments`)
      return response.data
    },
    enabled: Boolean(branchId && canView && selectedRunId && tab === 'runs'),
  })

  const reconciliationQuery = useQuery({
    queryKey: ['payroll-reconciliation', branchId, selectedRunId],
    queryFn: async () => {
      const response = await api.get<{ data: PayrollReconciliation }>(`/api/branches/${branchId}/payroll-runs/${selectedRunId}/reconciliation`)
      return response.data
    },
    enabled: Boolean(branchId && canView && selectedRunId && tab === 'runs'),
  })

  const accountsQuery = useQuery({
    queryKey: ['payroll-payment-accounts', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<PaymentAccountRef>>(`/api/branches/${branchId}/payment-accounts?status=active`)
      return response.data
    },
    enabled: Boolean(branchId && canView && canManage && tab === 'runs'),
  })

  const gradesQuery = useQuery({
    queryKey: ['salary-grades', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<SalaryGrade>>(`/api/branches/${branchId}/salary-grades`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'setup'),
  })

  const assignmentsQuery = useQuery({
    queryKey: ['salary-assignments', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<SalaryAssignment>>(`/api/branches/${branchId}/employee-salary-assignments`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'setup'),
  })

  const componentsQuery = useQuery({
    queryKey: ['pay-components', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<PayComponent>>(`/api/branches/${branchId}/employee-pay-components`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'setup'),
  })

  const rulesQuery = useQuery({
    queryKey: ['statutory-rules', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<StatutoryRule>>(`/api/branches/${branchId}/payroll-statutory-rules`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'setup'),
  })

  const loansQuery = useQuery({
    queryKey: ['employee-loans', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<EmployeeLoan>>(`/api/branches/${branchId}/employee-loans`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'loans'),
  })

  const adjustmentsQuery = useQuery({
    queryKey: ['payroll-adjustments', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<PayrollAdjustment>>(`/api/branches/${branchId}/payroll-adjustments`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'adjustments'),
  })

  const refresh = async (keys: string[]) => {
    await Promise.all(keys.map((key) => queryClient.invalidateQueries({ queryKey: [key, branchId] })))
  }

  const periodMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/payroll-periods`, periodForm)
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Payroll period created.' })
      setPeriodForm(emptyPeriodForm)
      await refresh(['payroll-periods'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const generateMutation = useMutation({
    mutationFn: async (period: PayrollPeriod) => {
      const response = await api.post(`/api/branches/${branchId}/payroll-periods/${period.id}/run`)
      return response.data as RunResponse
    },
    onSuccess: async (response) => {
      setFeedback({ type: 'success', text: `Payroll run v${response.data.version} generated.` })
      setSelectedRunId(response.data.id)
      await refresh(['payroll-periods', 'payroll-run'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const approveMutation = useMutation({
    mutationFn: async (run: PayrollRun) => {
      const response = await api.post(`/api/branches/${branchId}/payroll-runs/${run.id}/approve`)
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Payroll run approved and period locked.' })
      await refresh(['payroll-periods', 'payroll-run', 'employee-loans', 'payroll-adjustments'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const gradeMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/salary-grades`, {
        name: gradeForm.name,
        code: gradeForm.code,
        pay_basis: gradeForm.pay_basis,
        base_rate_minor: toMinor(gradeForm.base_rate),
        overtime_multiplier: gradeForm.overtime_multiplier,
        overtime_hourly_rate_minor: gradeForm.overtime_hourly_rate ? toMinor(gradeForm.overtime_hourly_rate) : null,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Salary grade created.' })
      setGradeForm(emptyGradeForm)
      await refresh(['salary-grades'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const assignmentMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/employee-salary-assignments`, assignmentForm)
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Salary assignment created.' })
      setAssignmentForm(emptyAssignmentForm)
      await refresh(['salary-assignments'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const componentMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/employee-pay-components`, {
        ...componentForm,
        amount_minor: toMinor(componentForm.amount),
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Pay component created.' })
      setComponentForm(emptyComponentForm)
      await refresh(['pay-components'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const ruleMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/payroll-statutory-rules`, {
        ...ruleForm,
        employee_rate: ruleForm.employee_rate || 0,
        employer_rate: ruleForm.employer_rate || 0,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Statutory rule created.' })
      setRuleForm(emptyRuleForm)
      await refresh(['statutory-rules'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const loanMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/employee-loans`, {
        employee_id: loanForm.employee_id,
        principal_minor: toMinor(loanForm.principal),
        installment_minor: toMinor(loanForm.installment),
        issued_date: loanForm.issued_date,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Employee loan created.' })
      setLoanForm(emptyLoanForm)
      await refresh(['employee-loans'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const adjustmentMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/payslips/${adjustmentForm.payslip_id}/adjustments`, {
        type: adjustmentForm.type,
        label: adjustmentForm.label,
        amount_minor: toMinor(adjustmentForm.amount),
        reason: adjustmentForm.reason,
      })
      return response.data as AdjustmentResponse
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Adjustment queued for the next open payroll run.' })
      setAdjustmentForm(emptyAdjustmentForm)
      await refresh(['payroll-adjustments', 'payroll-run'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const paymentMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/payroll-runs/${selectedRunId}/payments`, {
        ...paymentForm,
        amount_minor: toMinor(paymentForm.amount),
      }, { headers: { 'Idempotency-Key': crypto.randomUUID() } })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Payroll payment recorded.' })
      setPaymentForm(emptyPaymentForm)
      await refresh(['payroll-payments', 'payroll-reconciliation', 'payroll-run'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const reversalMutation = useMutation({
    mutationFn: async (adjustment: PayrollAdjustment) => {
      const response = await api.post(`/api/branches/${branchId}/payroll-adjustments/${adjustment.id}/reverse`)
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Adjustment reversal queued.' })
      await refresh(['payroll-adjustments'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const employees = employeesQuery.data?.data ?? []
  const periods = periodsQuery.data?.data ?? []
  const grades = gradesQuery.data?.data ?? []
  const assignments = assignmentsQuery.data?.data ?? []
  const components = componentsQuery.data?.data ?? []
  const rules = rulesQuery.data?.data ?? []
  const loans = loansQuery.data?.data ?? []
  const adjustments = adjustmentsQuery.data?.data ?? []
  const payments = paymentsQuery.data?.data ?? []
  const reconciliation = reconciliationQuery.data?.data ?? null
  const accounts = accountsQuery.data?.data ?? []
  const canExport = branch?.permissions.includes('branch.payroll.export') ?? false
  const run = runQuery.data?.data ?? null

  const submit = (event: FormEvent<HTMLFormElement>, action: () => void) => {
    event.preventDefault()
    setFeedback(null)
    action()
  }

  if (!meQuery.data) {
    return <AppShell><LoadingState text="Loading payroll access..." /></AppShell>
  }

  if (!branch) {
    return (
      <AppShell>
        <PermissionDeniedState title="No branch membership" description="Your account is active but is not assigned to a branch." />
      </AppShell>
    )
  }

  if (!canView) {
    return (
      <AppShell>
        <PermissionDeniedState description="Your role does not include permission to view payroll records." />
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Payroll</h1>
          <p className="text-muted-foreground">{branch.name} ({branch.code}) — deterministic salary, statutory and loan processing</p>
        </div>

        {feedback && (
          <div
            role={feedback.type === 'error' ? 'alert' : 'status'}
            className={feedback.type === 'error'
              ? 'rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive'
              : 'rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-700'}
          >
            {feedback.text}
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          <Button variant={tab === 'runs' ? 'default' : 'outline'} aria-pressed={tab === 'runs'} onClick={() => setTab('runs')}>Runs</Button>
          <Button variant={tab === 'setup' ? 'default' : 'outline'} aria-pressed={tab === 'setup'} onClick={() => setTab('setup')}>Setup</Button>
          <Button variant={tab === 'loans' ? 'default' : 'outline'} aria-pressed={tab === 'loans'} onClick={() => setTab('loans')}>Loans</Button>
          <Button variant={tab === 'adjustments' ? 'default' : 'outline'} aria-pressed={tab === 'adjustments'} onClick={() => setTab('adjustments')}>Adjustments</Button>
        </div>

        {tab === 'runs' && (
          <div className="space-y-6">
            {canManage && (
              <Card>
                <CardHeader><CardTitle className="text-base">New payroll period</CardTitle></CardHeader>
                <CardContent>
                  <form className="grid gap-4 md:grid-cols-4" onSubmit={(event) => submit(event, () => periodMutation.mutate())}>
                    <div><Label>Start date<Input type="date" required value={periodForm.start_date} onChange={(e) => setPeriodForm({ ...periodForm, start_date: e.target.value })} /></Label></div>
                    <div><Label>End date<Input type="date" required value={periodForm.end_date} onChange={(e) => setPeriodForm({ ...periodForm, end_date: e.target.value })} /></Label></div>
                    <div><Label>Pay date<Input type="date" required value={periodForm.pay_date} onChange={(e) => setPeriodForm({ ...periodForm, pay_date: e.target.value })} /></Label></div>
                    <div className="flex items-end"><Button type="submit" disabled={periodMutation.isPending}>Create period</Button></div>
                  </form>
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader><CardTitle className="text-base">Payroll periods</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                {periodsQuery.isLoading && <LoadingState text="Loading payroll periods..." />}
                {periodsQuery.isError && <ErrorState retry={() => periodsQuery.refetch()} />}
                {!periodsQuery.isLoading && periods.length === 0 && <EmptyState title="No payroll periods" />}
                {periods.map((period) => (
                  <div key={period.id} className="rounded-md border p-3">
                    <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-medium">{period.start_date} to {period.end_date}</span>
                          <StatusPill status={period.status} />
                          {period.latest_run && <StatusPill status={period.latest_run.status} />}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          Pay date {period.pay_date}
                          {period.latest_run && ` · v${period.latest_run.version} · gross ${money(period.latest_run.gross_minor)} · net ${money(period.latest_run.net_minor)}`}
                        </div>
                        {period.latest_run?.warnings.map((warning) => (
                          <div key={warning} className="text-xs text-amber-700">{warning}</div>
                        ))}
                      </div>
                      <div className="flex flex-wrap gap-2">
                        {canManage && period.status === 'open' && (
                          <Button variant="outline" onClick={() => generateMutation.mutate(period)} disabled={generateMutation.isPending}>
                            {period.latest_run ? 'Regenerate draft' : 'Generate run'}
                          </Button>
                        )}
                        {period.latest_run && (
                          <Button variant="outline" onClick={() => setSelectedRunId(period.latest_run?.id ?? null)}>View run</Button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>

            {selectedRunId && (
              <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                  <CardTitle className="text-base">Run detail</CardTitle>
                  {run && run.status === 'draft' && canApprove && (
                    <Button onClick={() => approveMutation.mutate(run)} disabled={approveMutation.isPending}>Approve and lock</Button>
                  )}
                </CardHeader>
                <CardContent className="space-y-4">
                  {runQuery.isLoading && <LoadingState text="Loading run..." />}
                  {runQuery.isError && <ErrorState retry={() => runQuery.refetch()} />}
                  {run && (
                    <>
                      <div className="grid gap-2 text-sm md:grid-cols-4">
                        <div>Gross: {money(run.gross_minor)}</div>
                        <div>Deductions: {money(run.deductions_minor)}</div>
                        <div>Employer statutory: {money(run.employer_statutory_minor)}</div>
                        <div>Net: {money(run.net_minor)}</div>
                      </div>
                      {reconciliation && (
                        <div className="rounded-md border p-3 text-sm">
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">Reconciliation</span>
                            <StatusPill status={reconciliation.status === 'PASS' ? 'approved' : 'cancelled'} />
                            <span>Allocated {money(reconciliation.payments_minor.allocated)}</span>
                            <span>Outstanding {money(reconciliation.payments_minor.outstanding)}</span>
                            <span>Accrued expense {money(reconciliation.accrual_minor)}</span>
                          </div>
                          {canExport && (
                            <Button
                              className="mt-2"
                              variant="outline"
                              onClick={async () => {
                                const response = await api.get<Blob>(`/api/branches/${branchId}/payroll-runs/${selectedRunId}/payslips/export`, { responseType: 'blob' })
                                const url = URL.createObjectURL(response.data)
                                const link = document.createElement('a')
                                link.href = url
                                link.download = `payroll-run-${selectedRunId}.csv`
                                link.click()
                                URL.revokeObjectURL(url)
                              }}
                            >
                              Export payslips
                            </Button>
                          )}
                        </div>
                      )}
                      {canManage && run.status === 'approved' && (
                        <form className="grid gap-3 rounded-md border p-3 md:grid-cols-5" onSubmit={(event) => submit(event, () => paymentMutation.mutate())}>
                          <div>
                            <Label>Account
                            <select required className={selectClass} value={paymentForm.payment_account_id} onChange={(e) => setPaymentForm({ ...paymentForm, payment_account_id: e.target.value })}>
                              <option value="">Select account</option>
                              {accounts.map((account) => <option key={account.id} value={account.id}>{account.name} ({account.currency_code})</option>)}
                            </select>
                            </Label>
                          </div>
                          <div><Label>Amount<Input type="number" min="0.01" step="0.01" required value={paymentForm.amount} onChange={(e) => setPaymentForm({ ...paymentForm, amount: e.target.value })} /></Label></div>
                          <div><Label>Paid at<Input type="datetime-local" required value={paymentForm.paid_at} onChange={(e) => setPaymentForm({ ...paymentForm, paid_at: e.target.value })} /></Label></div>
                          <div><Label>Reference<Input value={paymentForm.reference} onChange={(e) => setPaymentForm({ ...paymentForm, reference: e.target.value })} /></Label></div>
                          <div className="flex items-end"><Button type="submit" disabled={paymentMutation.isPending}>Record payment</Button></div>
                        </form>
                      )}
                      {payments.length > 0 && (
                        <div className="space-y-1 text-sm">
                          {payments.map((payment) => (
                            <div key={payment.id} className="flex justify-between border-b py-1">
                              <span>{payment.account?.name ?? 'Account'} · {payment.method} · {payment.reference ?? '—'}</span>
                              <span>{money(payment.amount_minor)}</span>
                            </div>
                          ))}
                        </div>
                      )}
                      {run.warnings.map((warning) => (
                        <div key={warning} className="rounded-md border border-amber-600/30 bg-amber-600/10 p-2 text-sm text-amber-800">{warning}</div>
                      ))}
                      {run.payslips.length === 0 && <EmptyState title="No payslips" description="Assign an eligible employee to a salary grade before generating." />}
                      {run.payslips.map((payslip) => (
                        <div key={payslip.id} className="rounded-md border p-3">
                          <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                              <div className="font-medium">{payslip.employee?.full_name ?? 'Employee'} ({payslip.employee?.employee_number})</div>
                              <div className="text-sm text-muted-foreground">
                                Gross {money(payslip.gross_minor)} · deductions {money(payslip.deductions_minor)} · net {money(payslip.net_minor)}
                              </div>
                              <div className="text-xs text-muted-foreground">
                                {payslip.pay_basis} · {payslip.payable_working_days}/{payslip.period_working_days} days · {payslip.regular_minutes} regular minutes · {payslip.overtime_minutes} overtime minutes
                              </div>
                            </div>
                            {canManage && run.status === 'approved' && (
                              <Button
                                variant="outline"
                                onClick={() => setAdjustmentForm({ ...emptyAdjustmentForm, payslip_id: payslip.id })}
                              >
                                Adjust
                              </Button>
                            )}
                          </div>
                          <details className="mt-2 text-sm">
                            <summary className="cursor-pointer text-muted-foreground">Lines ({payslip.lines.length})</summary>
                            <div className="mt-2 space-y-1">
                              {payslip.lines.map((line) => (
                                <div key={line.id} className="flex justify-between border-b py-1">
                                  <span>{line.label} {money(line.amount_minor)}</span>
                                  <span className="text-muted-foreground">{line.category}</span>
                                </div>
                              ))}
                            </div>
                          </details>
                          {adjustmentForm.payslip_id === payslip.id && (
                            <form
                              className="mt-3 grid gap-3 rounded-md border bg-muted/20 p-3 md:grid-cols-5"
                              onSubmit={(event) => submit(event, () => adjustmentMutation.mutate())}
                            >
                              <div>
                                <Label>Type
                                <select className={selectClass} value={adjustmentForm.type} onChange={(e) => setAdjustmentForm({ ...adjustmentForm, type: e.target.value })}>
                                  <option value="earning">Earning</option>
                                  <option value="deduction">Deduction</option>
                                </select>
                                </Label>
                              </div>
                              <div className="md:col-span-2"><Label>Label<Input required value={adjustmentForm.label} onChange={(e) => setAdjustmentForm({ ...adjustmentForm, label: e.target.value })} /></Label></div>
                              <div><Label>Amount<Input type="number" min="0.01" step="0.01" required value={adjustmentForm.amount} onChange={(e) => setAdjustmentForm({ ...adjustmentForm, amount: e.target.value })} /></Label></div>
                              <div className="md:col-span-5"><Label>Reason<Input required value={adjustmentForm.reason} onChange={(e) => setAdjustmentForm({ ...adjustmentForm, reason: e.target.value })} /></Label></div>
                              <div className="md:col-span-5 flex gap-2">
                                <Button type="submit" disabled={adjustmentMutation.isPending}>Queue adjustment</Button>
                                <Button type="button" variant="outline" onClick={() => setAdjustmentForm(emptyAdjustmentForm)}>Cancel</Button>
                              </div>
                            </form>
                          )}
                        </div>
                      ))}
                    </>
                  )}
                </CardContent>
              </Card>
            )}
          </div>
        )}

        {tab === 'setup' && (
          <div className="space-y-6">
            <Card>
              <CardHeader><CardTitle className="text-base">Salary grades</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                <div className="flex flex-wrap gap-2 text-sm">
                  {grades.length === 0 && <span className="text-muted-foreground">No salary grades configured.</span>}
                  {grades.map((grade) => (
                    <span key={grade.id} className="rounded-full border px-3 py-1">
                      {grade.name} ({grade.code}) · {grade.pay_basis} · {money(grade.base_rate_minor)} · OT x{grade.overtime_multiplier}
                    </span>
                  ))}
                </div>
                {canManage && (
                  <form className="grid gap-3 md:grid-cols-6" onSubmit={(event) => submit(event, () => gradeMutation.mutate())}>
                    <div className="md:col-span-2"><Label>Name<Input required value={gradeForm.name} onChange={(e) => setGradeForm({ ...gradeForm, name: e.target.value })} /></Label></div>
                    <div><Label>Code<Input required value={gradeForm.code} onChange={(e) => setGradeForm({ ...gradeForm, code: e.target.value })} /></Label></div>
                    <div>
                      <Label>Basis
                      <select className={selectClass} value={gradeForm.pay_basis} onChange={(e) => setGradeForm({ ...gradeForm, pay_basis: e.target.value })}>
                        <option value="monthly">Monthly</option>
                        <option value="hourly">Hourly</option>
                      </select>
                      </Label>
                    </div>
                    <div><Label>Base rate<Input type="number" min="0.01" step="0.01" required value={gradeForm.base_rate} onChange={(e) => setGradeForm({ ...gradeForm, base_rate: e.target.value })} /></Label></div>
                    <div><Label>OT multiplier<Input type="number" min="1" max="5" step="0.01" value={gradeForm.overtime_multiplier} onChange={(e) => setGradeForm({ ...gradeForm, overtime_multiplier: e.target.value })} /></Label></div>
                    <div><Label>OT hourly rate<Input type="number" min="0" step="0.01" value={gradeForm.overtime_hourly_rate} onChange={(e) => setGradeForm({ ...gradeForm, overtime_hourly_rate: e.target.value })} /></Label></div>
                    <div className="md:col-span-6"><Button type="submit" disabled={gradeMutation.isPending}>Add grade</Button></div>
                  </form>
                )}
                <p className="text-xs text-muted-foreground">Base rate is monthly salary or hourly rate according to the selected basis. Overtime uses the OT hourly rate when supplied.</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle className="text-base">Employee salary assignments</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                {assignments.length === 0 && <EmptyState title="No assignments" />}
                {assignments.map((assignment) => (
                  <div key={assignment.id} className="rounded-md border p-2 text-sm">
                    {assignment.employee?.full_name} · {assignment.salary_grade?.name} · from {assignment.effective_from} · {money(assignment.salary_grade?.base_rate_minor ?? 0)}
                  </div>
                ))}
                {canManage && (
                  <form className="grid gap-3 md:grid-cols-4" onSubmit={(event) => submit(event, () => assignmentMutation.mutate())}>
                    <div>
                      <Label>Employee
                      <select required className={selectClass} value={assignmentForm.employee_id} onChange={(e) => setAssignmentForm({ ...assignmentForm, employee_id: e.target.value })}>
                        <option value="">Select employee</option>
                        {employees.map((employee) => <option key={employee.id} value={employee.id}>{employee.full_name}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div>
                      <Label>Grade
                      <select required className={selectClass} value={assignmentForm.salary_grade_id} onChange={(e) => setAssignmentForm({ ...assignmentForm, salary_grade_id: e.target.value })}>
                        <option value="">Select grade</option>
                        {grades.map((grade) => <option key={grade.id} value={grade.id}>{grade.name}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div><Label>Effective from<Input type="date" required value={assignmentForm.effective_from} onChange={(e) => setAssignmentForm({ ...assignmentForm, effective_from: e.target.value })} /></Label></div>
                    <div className="flex items-end"><Button type="submit" disabled={assignmentMutation.isPending}>Assign</Button></div>
                  </form>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle className="text-base">Allowances and deductions</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                {components.length === 0 && <EmptyState title="No pay components" />}
                {components.map((component) => (
                  <div key={component.id} className="rounded-md border p-2 text-sm">
                    {component.employee?.full_name} · {component.label} ({component.code}) · {component.type} · {money(component.amount_minor)} from {component.effective_from}
                  </div>
                ))}
                {canManage && (
                  <form className="grid gap-3 md:grid-cols-6" onSubmit={(event) => submit(event, () => componentMutation.mutate())}>
                    <div>
                      <Label>Employee
                      <select required className={selectClass} value={componentForm.employee_id} onChange={(e) => setComponentForm({ ...componentForm, employee_id: e.target.value })}>
                        <option value="">Select employee</option>
                        {employees.map((employee) => <option key={employee.id} value={employee.id}>{employee.full_name}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div>
                      <Label>Type
                      <select className={selectClass} value={componentForm.type} onChange={(e) => setComponentForm({ ...componentForm, type: e.target.value })}>
                        <option value="allowance">Allowance</option>
                        <option value="deduction">Deduction</option>
                      </select>
                      </Label>
                    </div>
                    <div><Label>Code<Input required value={componentForm.code} onChange={(e) => setComponentForm({ ...componentForm, code: e.target.value })} /></Label></div>
                    <div><Label>Label<Input required value={componentForm.label} onChange={(e) => setComponentForm({ ...componentForm, label: e.target.value })} /></Label></div>
                    <div><Label>Amount<Input type="number" min="0.01" step="0.01" required value={componentForm.amount} onChange={(e) => setComponentForm({ ...componentForm, amount: e.target.value })} /></Label></div>
                    <div><Label>Effective from<Input type="date" required value={componentForm.effective_from} onChange={(e) => setComponentForm({ ...componentForm, effective_from: e.target.value })} /></Label></div>
                    <div className="md:col-span-6"><Button type="submit" disabled={componentMutation.isPending}>Add component</Button></div>
                  </form>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle className="text-base">Statutory rules</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                {rules.length === 0 && <EmptyState title="No statutory rules" />}
                {rules.map((rule) => (
                  <div key={rule.id} className="rounded-md border p-2 text-sm">
                    {rule.name} ({rule.code}) · {rule.applies_to} · employee {rule.employee_rate}% · employer {rule.employer_rate}%
                  </div>
                ))}
                {canManage && (
                  <form className="grid gap-3 md:grid-cols-6" onSubmit={(event) => submit(event, () => ruleMutation.mutate())}>
                    <div><Label>Name<Input required value={ruleForm.name} onChange={(e) => setRuleForm({ ...ruleForm, name: e.target.value })} /></Label></div>
                    <div><Label>Code<Input required value={ruleForm.code} onChange={(e) => setRuleForm({ ...ruleForm, code: e.target.value })} /></Label></div>
                    <div>
                      <Label>Applies to
                      <select className={selectClass} value={ruleForm.applies_to} onChange={(e) => setRuleForm({ ...ruleForm, applies_to: e.target.value })}>
                        <option value="gross">Gross</option>
                        <option value="basic">Basic + overtime</option>
                      </select>
                      </Label>
                    </div>
                    <div><Label>Employee %<Input type="number" min="0" max="100" step="0.0001" value={ruleForm.employee_rate} onChange={(e) => setRuleForm({ ...ruleForm, employee_rate: e.target.value })} /></Label></div>
                    <div><Label>Employer %<Input type="number" min="0" max="100" step="0.0001" value={ruleForm.employer_rate} onChange={(e) => setRuleForm({ ...ruleForm, employer_rate: e.target.value })} /></Label></div>
                    <div><Label>Effective from<Input type="date" required value={ruleForm.effective_from} onChange={(e) => setRuleForm({ ...ruleForm, effective_from: e.target.value })} /></Label></div>
                    <div className="md:col-span-6"><Button type="submit" disabled={ruleMutation.isPending}>Add rule</Button></div>
                  </form>
                )}
                <p className="text-xs text-muted-foreground">Rates are configurable and unverified; they are production blockers until legally confirmed.</p>
              </CardContent>
            </Card>
          </div>
        )}

        {tab === 'loans' && (
          <div className="space-y-6">
            {canManage && (
              <Card>
                <CardHeader><CardTitle className="text-base">New employee loan</CardTitle></CardHeader>
                <CardContent>
                  <form className="grid gap-4 md:grid-cols-4" onSubmit={(event) => submit(event, () => loanMutation.mutate())}>
                    <div>
                      <Label>Employee
                      <select required className={selectClass} value={loanForm.employee_id} onChange={(e) => setLoanForm({ ...loanForm, employee_id: e.target.value })}>
                        <option value="">Select employee</option>
                        {employees.map((employee) => <option key={employee.id} value={employee.id}>{employee.full_name}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div><Label>Principal<Input type="number" min="0.01" step="0.01" required value={loanForm.principal} onChange={(e) => setLoanForm({ ...loanForm, principal: e.target.value })} /></Label></div>
                    <div><Label>Installment<Input type="number" min="0.01" step="0.01" required value={loanForm.installment} onChange={(e) => setLoanForm({ ...loanForm, installment: e.target.value })} /></Label></div>
                    <div><Label>Issued date<Input type="date" required max={today} value={loanForm.issued_date} onChange={(e) => setLoanForm({ ...loanForm, issued_date: e.target.value })} /></Label></div>
                    <div className="md:col-span-4"><Button type="submit" disabled={loanMutation.isPending}>Create loan</Button></div>
                  </form>
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader><CardTitle className="text-base">Employee loans</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                {loansQuery.isLoading && <LoadingState text="Loading loans..." />}
                {loansQuery.isError && <ErrorState retry={() => loansQuery.refetch()} />}
                {!loansQuery.isLoading && loans.length === 0 && <EmptyState title="No loans" />}
                {loans.map((loan) => (
                  <div key={loan.id} className="rounded-md border p-3">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">{loan.employee?.full_name ?? 'Employee'}</span>
                      <StatusPill status={loan.status} />
                      <span className="text-sm text-muted-foreground">Issued {loan.issued_date}</span>
                    </div>
                    <div className="text-sm text-muted-foreground">
                      Principal {money(loan.principal_minor)} · balance {money(loan.balance_minor)} · installment {money(loan.installment_minor)} · repayments {loan.repayments.length}
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        )}

        {tab === 'adjustments' && (
          <Card>
            <CardHeader><CardTitle className="text-base">Payroll adjustments</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              {adjustmentsQuery.isLoading && <LoadingState text="Loading adjustments..." />}
              {adjustmentsQuery.isError && <ErrorState retry={() => adjustmentsQuery.refetch()} />}
              {!adjustmentsQuery.isLoading && adjustments.length === 0 && <EmptyState title="No adjustments" />}
              {adjustments.map((adjustment) => (
                <div key={adjustment.id} className="flex flex-col gap-2 rounded-md border p-3 md:flex-row md:items-center md:justify-between">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">{adjustment.employee?.full_name ?? 'Employee'}</span>
                      <StatusPill status={adjustment.status} />
                      <span className="text-sm text-muted-foreground">{adjustment.type} · {money(adjustment.amount_minor)}</span>
                    </div>
                    <div className="text-sm">{adjustment.label}</div>
                    <div className="text-xs text-muted-foreground">{adjustment.reason}</div>
                  </div>
                  {canManage && adjustment.status === 'applied' && (
                    <Button variant="outline" onClick={() => reversalMutation.mutate(adjustment)} disabled={reversalMutation.isPending}>
                      Queue reversal
                    </Button>
                  )}
                </div>
              ))}
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  )
}
