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
  AttendanceListResponse,
  AttendanceRecord,
  DisciplinaryListResponse,
  DisciplinaryRecord,
  EmployeeOptionListResponse,
  LeaveRequest,
  LeaveRequestListResponse,
  LeaveType,
  LeaveTypeListResponse,
} from '@/lib/hrRecords'

type Tab = 'leave' | 'attendance' | 'disciplinary'
type Feedback = { type: 'error' | 'success'; text: string }

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm'
const today = new Date().toISOString().slice(0, 10)

const emptyLeaveForm = { employee_id: '', leave_type_id: '', start_date: today, end_date: today, reason: '' }
const emptyLeaveTypeForm = { name: '', code: '', max_days_per_year: '', is_paid: true, requires_approval: true, counts_weekends: false }
const emptyAttendanceForm = { employee_id: '', work_date: today, status: 'present', scheduled_minutes: '480', worked_minutes: '480', overtime_minutes: '0', notes: '' }
const emptyDisciplinaryForm = { employee_id: '', incident_date: today, category: 'conduct', severity: 'verbal', summary: '', details: '', action_taken: '' }

function StatusPill({ status }: { status: string }) {
  const tone =
    status === 'approved' || status === 'present' || status === 'resolved'
      ? 'bg-green-600/10 text-green-700 border-green-600/30'
      : status === 'submitted' || status === 'open' || status === 'late'
        ? 'bg-blue-600/10 text-blue-700 border-blue-600/30'
        : status === 'rejected' || status === 'absent'
          ? 'bg-destructive/10 text-destructive border-destructive/30'
          : 'bg-muted text-muted-foreground border-border'

  return <span className={`rounded-full border px-2 py-0.5 text-xs capitalize ${tone}`}>{status.replace('_', ' ')}</span>
}

export default function HrRecordsPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.hr.view') ?? false
  const canManage = branch?.permissions.includes('branch.hr.manage') ?? false
  const canApproveLeave = branch?.permissions.includes('branch.hr.leave.approve') ?? false
  const canViewSensitive = branch?.permissions.includes('branch.hr.sensitive.view') ?? false

  const [tab, setTab] = useState<Tab>('leave')
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [leaveForm, setLeaveForm] = useState(emptyLeaveForm)
  const [leaveTypeForm, setLeaveTypeForm] = useState(emptyLeaveTypeForm)
  const [leaveStatus, setLeaveStatus] = useState('all')
  const [attendanceForm, setAttendanceForm] = useState(emptyAttendanceForm)
  const [attendanceFrom, setAttendanceFrom] = useState('')
  const [attendanceTo, setAttendanceTo] = useState('')
  const [disciplinaryForm, setDisciplinaryForm] = useState(emptyDisciplinaryForm)

  const employeesQuery = useQuery({
    queryKey: ['employees-options', branchId],
    queryFn: async () => {
      const response = await api.get<EmployeeOptionListResponse>(`/api/branches/${branchId}/employees`, {
        params: { status: 'current', per_page: 100 },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const leaveTypesQuery = useQuery({
    queryKey: ['leave-types', branchId],
    queryFn: async () => {
      const response = await api.get<LeaveTypeListResponse>(`/api/branches/${branchId}/leave-types`, { params: { status: 'active' } })
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const leaveQuery = useQuery({
    queryKey: ['leave-requests', branchId, leaveStatus],
    queryFn: async () => {
      const response = await api.get<LeaveRequestListResponse>(`/api/branches/${branchId}/leave-requests`, {
        params: { status: leaveStatus, per_page: 50 },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'leave'),
  })

  const attendanceQuery = useQuery({
    queryKey: ['attendance', branchId, attendanceFrom, attendanceTo],
    queryFn: async () => {
      const response = await api.get<AttendanceListResponse>(`/api/branches/${branchId}/attendance`, {
        params: { from: attendanceFrom || undefined, to: attendanceTo || undefined, per_page: 60 },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'attendance'),
  })

  const disciplinaryQuery = useQuery({
    queryKey: ['disciplinary-records', branchId],
    queryFn: async () => {
      const response = await api.get<DisciplinaryListResponse>(`/api/branches/${branchId}/disciplinary-records`, { params: { per_page: 50 } })
      return response.data
    },
    enabled: Boolean(branchId && canViewSensitive && tab === 'disciplinary'),
  })

  const refresh = async (keys: string[]) => {
    await Promise.all(keys.map((key) => queryClient.invalidateQueries({ queryKey: [key, branchId] })))
  }

  const leaveTypeMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/leave-types`, {
        name: leaveTypeForm.name,
        code: leaveTypeForm.code,
        max_days_per_year: leaveTypeForm.max_days_per_year ? Number(leaveTypeForm.max_days_per_year) : null,
        is_paid: leaveTypeForm.is_paid,
        requires_approval: leaveTypeForm.requires_approval,
        counts_weekends: leaveTypeForm.counts_weekends,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Leave type created.' })
      setLeaveTypeForm(emptyLeaveTypeForm)
      await refresh(['leave-types'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const leaveMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/leave-requests`, {
        employee_id: leaveForm.employee_id,
        leave_type_id: leaveForm.leave_type_id,
        start_date: leaveForm.start_date,
        end_date: leaveForm.end_date,
        reason: leaveForm.reason || null,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Leave request submitted.' })
      setLeaveForm(emptyLeaveForm)
      await refresh(['leave-requests'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const leaveDecisionMutation = useMutation({
    mutationFn: async ({ leave, action }: { leave: LeaveRequest; action: 'approve' | 'reject' | 'cancel' }) => {
      let note: string | null = null

      if (action === 'reject') {
        note = window.prompt('Reason for rejection') ?? ''
        if (!note.trim()) {
          throw new Error('A rejection reason is required.')
        }
      } else if (action === 'approve') {
        note = window.prompt('Decision note (optional)') ?? ''
      }

      const response = await api.post(
        `/api/branches/${branchId}/leave-requests/${leave.id}/${action}`,
        note === null ? {} : { decision_note: note },
      )
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Leave request updated.' })
      await refresh(['leave-requests'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const attendanceMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/attendance`, {
        employee_id: attendanceForm.employee_id,
        work_date: attendanceForm.work_date,
        status: attendanceForm.status,
        scheduled_minutes: Number(attendanceForm.scheduled_minutes || 0),
        worked_minutes: Number(attendanceForm.worked_minutes || 0),
        overtime_minutes: Number(attendanceForm.overtime_minutes || 0),
        notes: attendanceForm.notes || null,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Attendance saved.' })
      setAttendanceForm({ ...emptyAttendanceForm, work_date: attendanceForm.work_date })
      await refresh(['attendance'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const disciplinaryMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(`/api/branches/${branchId}/disciplinary-records`, {
        employee_id: disciplinaryForm.employee_id,
        incident_date: disciplinaryForm.incident_date,
        category: disciplinaryForm.category,
        severity: disciplinaryForm.severity,
        summary: disciplinaryForm.summary,
        details: disciplinaryForm.details || null,
        action_taken: disciplinaryForm.action_taken || null,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Disciplinary record created.' })
      setDisciplinaryForm(emptyDisciplinaryForm)
      await refresh(['disciplinary-records'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const disciplinaryTransitionMutation = useMutation({
    mutationFn: async ({ record, status }: { record: DisciplinaryRecord; status: string }) => {
      const response = await api.post(`/api/branches/${branchId}/disciplinary-records/${record.id}/transition`, { status })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Disciplinary record updated.' })
      await refresh(['disciplinary-records'])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const employees = employeesQuery.data?.data ?? []
  const leaveTypes = leaveTypesQuery.data?.data ?? []
  const leaveRequests = leaveQuery.data?.data ?? []
  const attendanceRecords = attendanceQuery.data?.data ?? []
  const disciplinaryRecords = disciplinaryQuery.data?.data ?? []

  const submit = (event: FormEvent<HTMLFormElement>, action: () => void) => {
    event.preventDefault()
    setFeedback(null)
    action()
  }

  if (!meQuery.data) {
    return <AppShell><LoadingState text="Loading HR access..." /></AppShell>
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
        <PermissionDeniedState description="Your role does not include permission to view HR records." />
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">HR records</h1>
          <p className="text-muted-foreground">{branch.name} ({branch.code}) — leave, attendance and disciplinary workflows</p>
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
          <Button variant={tab === 'leave' ? 'default' : 'outline'} aria-pressed={tab === 'leave'} onClick={() => setTab('leave')}>Leave</Button>
          <Button variant={tab === 'attendance' ? 'default' : 'outline'} aria-pressed={tab === 'attendance'} onClick={() => setTab('attendance')}>Attendance</Button>
          {canViewSensitive && (
            <Button variant={tab === 'disciplinary' ? 'default' : 'outline'} aria-pressed={tab === 'disciplinary'} onClick={() => setTab('disciplinary')}>Disciplinary</Button>
          )}
        </div>

        {tab === 'leave' && (
          <div className="space-y-6">
            {canManage && (
              <Card>
                <CardHeader><CardTitle className="text-base">New leave request</CardTitle></CardHeader>
                <CardContent>
                  <form className="grid gap-4 md:grid-cols-2" onSubmit={(event) => submit(event, () => leaveMutation.mutate())}>
                    <div>
                      <Label htmlFor="leave-employee">Employee</Label>
                      <select id="leave-employee" required className={selectClass} value={leaveForm.employee_id} onChange={(e) => setLeaveForm({ ...leaveForm, employee_id: e.target.value })}>
                        <option value="">Select employee</option>
                        {employees.map((employee) => <option key={employee.id} value={employee.id}>{employee.full_name} ({employee.employee_number})</option>)}
                      </select>
                    </div>
                    <div>
                      <Label htmlFor="leave-type">Leave type</Label>
                      <select id="leave-type" required className={selectClass} value={leaveForm.leave_type_id} onChange={(e) => setLeaveForm({ ...leaveForm, leave_type_id: e.target.value })}>
                        <option value="">Select leave type</option>
                        {leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}{type.max_days_per_year ? ` (max ${type.max_days_per_year}/yr)` : ''}</option>)}
                      </select>
                    </div>
                    <div>
                      <Label htmlFor="leave-start">Start date</Label>
                      <Input id="leave-start" type="date" required value={leaveForm.start_date} onChange={(e) => setLeaveForm({ ...leaveForm, start_date: e.target.value })} />
                    </div>
                    <div>
                      <Label htmlFor="leave-end">End date</Label>
                      <Input id="leave-end" type="date" required value={leaveForm.end_date} onChange={(e) => setLeaveForm({ ...leaveForm, end_date: e.target.value })} />
                    </div>
                    <div className="md:col-span-2">
                      <Label htmlFor="leave-reason">Reason</Label>
                      <Input id="leave-reason" value={leaveForm.reason} onChange={(e) => setLeaveForm({ ...leaveForm, reason: e.target.value })} />
                    </div>
                    <div className="md:col-span-2">
                      <Button type="submit" disabled={leaveMutation.isPending}>{leaveMutation.isPending ? 'Submitting...' : 'Submit leave request'}</Button>
                    </div>
                  </form>
                </CardContent>
              </Card>
            )}

            {canManage && (
              <Card>
                <CardHeader><CardTitle className="text-base">Leave types</CardTitle></CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex flex-wrap gap-2 text-sm">
                    {leaveTypes.length === 0 && <span className="text-muted-foreground">No leave types configured yet.</span>}
                    {leaveTypes.map((type: LeaveType) => (
                      <span key={type.id} className="rounded-full border px-3 py-1">
                        {type.name} ({type.code}) {type.is_paid ? '· paid' : '· unpaid'} {type.max_days_per_year ? `· max ${type.max_days_per_year}` : ''}
                      </span>
                    ))}
                  </div>
                  <form className="grid gap-3 md:grid-cols-5" onSubmit={(event) => submit(event, () => leaveTypeMutation.mutate())}>
                    <div className="md:col-span-2"><Label>Name<Input required value={leaveTypeForm.name} onChange={(e) => setLeaveTypeForm({ ...leaveTypeForm, name: e.target.value })} /></Label></div>
                    <div><Label>Code<Input required value={leaveTypeForm.code} onChange={(e) => setLeaveTypeForm({ ...leaveTypeForm, code: e.target.value })} placeholder="ANNUAL" /></Label></div>
                    <div><Label>Max days/year<Input type="number" min="0" step="0.5" value={leaveTypeForm.max_days_per_year} onChange={(e) => setLeaveTypeForm({ ...leaveTypeForm, max_days_per_year: e.target.value })} /></Label></div>
                    <div className="flex items-end"><Button type="submit" disabled={leaveTypeMutation.isPending}>Add type</Button></div>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={leaveTypeForm.is_paid} onChange={(e) => setLeaveTypeForm({ ...leaveTypeForm, is_paid: e.target.checked })} /> Paid</label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={leaveTypeForm.requires_approval} onChange={(e) => setLeaveTypeForm({ ...leaveTypeForm, requires_approval: e.target.checked })} /> Requires approval</label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={leaveTypeForm.counts_weekends} onChange={(e) => setLeaveTypeForm({ ...leaveTypeForm, counts_weekends: e.target.checked })} /> Count weekends</label>
                  </form>
                  <p className="text-xs text-muted-foreground">Public holidays are not configured yet; approval remains a documented manual check.</p>
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">Leave requests</CardTitle>
                <select className={selectClass} aria-label="Leave status" value={leaveStatus} onChange={(e) => setLeaveStatus(e.target.value)}>
                  <option value="all">All</option>
                  <option value="submitted">Submitted</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </CardHeader>
              <CardContent className="space-y-3">
                {leaveQuery.isLoading && <LoadingState text="Loading leave requests..." />}
                {leaveQuery.isError && <ErrorState retry={() => leaveQuery.refetch()} />}
                {!leaveQuery.isLoading && leaveRequests.length === 0 && <EmptyState title="No leave requests" />}
                {leaveRequests.map((leave) => (
                  <div key={leave.id} className="rounded-md border p-3">
                    <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-medium">{leave.employee?.full_name ?? 'Employee'}</span>
                          <StatusPill status={leave.status} />
                          <span className="text-sm text-muted-foreground">{leave.leave_type?.name}</span>
                        </div>
                        <div className="text-sm text-muted-foreground">
                          {leave.start_date} to {leave.end_date} · {leave.days_requested} days
                        </div>
                        {leave.reason && <div className="text-sm">{leave.reason}</div>}
                        {leave.decision_note && <div className="text-xs text-muted-foreground">Decision: {leave.decision_note}</div>}
                      </div>
                      <div className="flex flex-wrap gap-2">
                        {canApproveLeave && leave.status === 'submitted' && (
                          <>
                            <Button variant="outline" onClick={() => leaveDecisionMutation.mutate({ leave, action: 'approve' })}>Approve</Button>
                            <Button variant="outline" onClick={() => leaveDecisionMutation.mutate({ leave, action: 'reject' })}>Reject</Button>
                          </>
                        )}
                        {canManage && (leave.status === 'submitted' || leave.status === 'approved') && (
                          <Button variant="outline" onClick={() => leaveDecisionMutation.mutate({ leave, action: 'cancel' })}>Cancel</Button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        )}

        {tab === 'attendance' && (
          <div className="space-y-6">
            {canManage && (
              <Card>
                <CardHeader><CardTitle className="text-base">Record attendance</CardTitle></CardHeader>
                <CardContent>
                  <form className="grid gap-4 md:grid-cols-4" onSubmit={(event) => submit(event, () => attendanceMutation.mutate())}>
                    <div>
                      <Label>Employee
                      <select required className={selectClass} value={attendanceForm.employee_id} onChange={(e) => setAttendanceForm({ ...attendanceForm, employee_id: e.target.value })}>
                        <option value="">Select employee</option>
                        {employees.map((employee) => <option key={employee.id} value={employee.id}>{employee.full_name}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div><Label>Date<Input type="date" required max={today} value={attendanceForm.work_date} onChange={(e) => setAttendanceForm({ ...attendanceForm, work_date: e.target.value })} /></Label></div>
                    <div>
                      <Label>Status
                      <select className={selectClass} value={attendanceForm.status} onChange={(e) => setAttendanceForm({ ...attendanceForm, status: e.target.value })}>
                        {['present', 'absent', 'late', 'half_day', 'leave', 'holiday'].map((status) => <option key={status} value={status}>{status.replace('_', ' ')}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div><Label>Scheduled minutes<Input type="number" min="0" max="1440" value={attendanceForm.scheduled_minutes} onChange={(e) => setAttendanceForm({ ...attendanceForm, scheduled_minutes: e.target.value })} /></Label></div>
                    <div><Label>Worked minutes<Input type="number" min="0" max="1440" value={attendanceForm.worked_minutes} onChange={(e) => setAttendanceForm({ ...attendanceForm, worked_minutes: e.target.value })} /></Label></div>
                    <div><Label>Overtime minutes<Input type="number" min="0" max="720" value={attendanceForm.overtime_minutes} onChange={(e) => setAttendanceForm({ ...attendanceForm, overtime_minutes: e.target.value })} /></Label></div>
                    <div className="md:col-span-2"><Label>Notes<Input value={attendanceForm.notes} onChange={(e) => setAttendanceForm({ ...attendanceForm, notes: e.target.value })} /></Label></div>
                    <div className="md:col-span-4"><Button type="submit" disabled={attendanceMutation.isPending}>Save attendance</Button></div>
                  </form>
                  <p className="mt-2 text-xs text-muted-foreground">Saving the same employee and date updates the existing record instead of duplicating it.</p>
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader><CardTitle className="text-base">Attendance records</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                <div className="flex flex-col gap-2 md:flex-row">
                  <Input type="date" aria-label="From date" value={attendanceFrom} onChange={(e) => setAttendanceFrom(e.target.value)} />
                  <Input type="date" aria-label="To date" value={attendanceTo} onChange={(e) => setAttendanceTo(e.target.value)} />
                </div>
                {attendanceQuery.isLoading && <LoadingState text="Loading attendance..." />}
                {attendanceQuery.isError && <ErrorState retry={() => attendanceQuery.refetch()} />}
                {!attendanceQuery.isLoading && attendanceRecords.length === 0 && <EmptyState title="No attendance records" />}
                {attendanceRecords.map((record: AttendanceRecord) => (
                  <div key={record.id} className="flex flex-col gap-2 rounded-md border p-3 md:flex-row md:items-center md:justify-between">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-medium">{record.employee?.full_name ?? 'Employee'}</span>
                        <StatusPill status={record.status} />
                        <span className="text-sm text-muted-foreground">{record.work_date}</span>
                      </div>
                      <div className="text-xs text-muted-foreground">
                        Scheduled {record.scheduled_minutes}m · Worked {record.worked_minutes}m · Overtime {record.overtime_minutes}m
                      </div>
                      {record.notes && <div className="text-sm">{record.notes}</div>}
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        )}

        {tab === 'disciplinary' && canViewSensitive && (
          <div className="space-y-6">
            {canManage && (
              <Card>
                <CardHeader><CardTitle className="text-base">New disciplinary record</CardTitle></CardHeader>
                <CardContent>
                  <form className="grid gap-4 md:grid-cols-2" onSubmit={(event) => submit(event, () => disciplinaryMutation.mutate())}>
                    <div>
                      <Label>Employee
                      <select required className={selectClass} value={disciplinaryForm.employee_id} onChange={(e) => setDisciplinaryForm({ ...disciplinaryForm, employee_id: e.target.value })}>
                        <option value="">Select employee</option>
                        {employees.map((employee) => <option key={employee.id} value={employee.id}>{employee.full_name}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div><Label>Incident date<Input type="date" required max={today} value={disciplinaryForm.incident_date} onChange={(e) => setDisciplinaryForm({ ...disciplinaryForm, incident_date: e.target.value })} /></Label></div>
                    <div>
                      <Label>Category
                      <select className={selectClass} value={disciplinaryForm.category} onChange={(e) => setDisciplinaryForm({ ...disciplinaryForm, category: e.target.value })}>
                        {['conduct', 'performance', 'attendance', 'safety', 'policy', 'other'].map((value) => <option key={value} value={value}>{value}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div>
                      <Label>Severity
                      <select className={selectClass} value={disciplinaryForm.severity} onChange={(e) => setDisciplinaryForm({ ...disciplinaryForm, severity: e.target.value })}>
                        {['verbal', 'written', 'final_warning', 'suspension', 'other'].map((value) => <option key={value} value={value}>{value.replace('_', ' ')}</option>)}
                      </select>
                      </Label>
                    </div>
                    <div className="md:col-span-2"><Label>Summary<Input required maxLength={240} value={disciplinaryForm.summary} onChange={(e) => setDisciplinaryForm({ ...disciplinaryForm, summary: e.target.value })} /></Label></div>
                    <div className="md:col-span-2"><Label>Details<Input value={disciplinaryForm.details} onChange={(e) => setDisciplinaryForm({ ...disciplinaryForm, details: e.target.value })} /></Label></div>
                    <div className="md:col-span-2"><Label>Action taken<Input value={disciplinaryForm.action_taken} onChange={(e) => setDisciplinaryForm({ ...disciplinaryForm, action_taken: e.target.value })} /></Label></div>
                    <div className="md:col-span-2"><Button type="submit" disabled={disciplinaryMutation.isPending}>Create record</Button></div>
                  </form>
                  <p className="mt-2 text-xs text-muted-foreground">Disciplinary details are sensitive and restricted to users with sensitive HR permission.</p>
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader><CardTitle className="text-base">Disciplinary records</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                {disciplinaryQuery.isLoading && <LoadingState text="Loading disciplinary records..." />}
                {disciplinaryQuery.isError && <ErrorState retry={() => disciplinaryQuery.refetch()} />}
                {!disciplinaryQuery.isLoading && disciplinaryRecords.length === 0 && <EmptyState title="No disciplinary records" />}
                {disciplinaryRecords.map((record: DisciplinaryRecord) => (
                  <div key={record.id} className="rounded-md border p-3">
                    <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-medium">{record.employee?.full_name ?? 'Employee'}</span>
                          <StatusPill status={record.status} />
                          <span className="text-sm text-muted-foreground">{record.category} · {record.severity.replace('_', ' ')} · {record.incident_date}</span>
                        </div>
                        <div className="text-sm">{record.summary}</div>
                        {record.details && <div className="text-sm text-muted-foreground">{record.details}</div>}
                        {record.resolution_note && <div className="text-xs text-muted-foreground">Resolution: {record.resolution_note}</div>}
                      </div>
                      {canManage && record.allowed_transitions.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                          {record.allowed_transitions.map((status) => (
                            <Button key={status} variant="outline" onClick={() => disciplinaryTransitionMutation.mutate({ record, status })}>
                              Mark {status}
                            </Button>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </AppShell>
  )
}
