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
  Employee,
  EmployeeFormState,
  EmployeeListResponse,
  EmployeeMutationResponse,
  EmployeeStatus,
  LinkableUsersResponse,
  employeeStatusLabels,
  employmentTypeLabels,
  emptyEmployeeForm,
} from '@/lib/hr'

type Feedback = { type: 'error' | 'success'; text: string }

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm'

function statusClass(status: EmployeeStatus): string {
  switch (status) {
    case 'active':
      return 'bg-green-600/10 text-green-700 border-green-600/30'
    case 'suspended':
      return 'bg-amber-500/10 text-amber-700 border-amber-500/30'
    case 'terminated':
      return 'bg-destructive/10 text-destructive border-destructive/30'
    case 'archived':
      return 'bg-muted text-muted-foreground border-border'
    default:
      return 'bg-blue-600/10 text-blue-700 border-blue-600/30'
  }
}

export default function EmployeesPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.hr.view') ?? false
  const canManage = branch?.permissions.includes('branch.hr.manage') ?? false
  const canViewSensitive = branch?.permissions.includes('branch.hr.sensitive.view') ?? false

  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('current')
  const [page, setPage] = useState(1)
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<Employee | null>(null)
  const [form, setForm] = useState<EmployeeFormState>(emptyEmployeeForm)
  const [feedback, setFeedback] = useState<Feedback | null>(null)

  const employeesQuery = useQuery({
    queryKey: ['employees', branchId, { search, status, page }],
    queryFn: async () => {
      const response = await api.get<EmployeeListResponse>(`/api/branches/${branchId}/employees`, {
        params: { q: search || undefined, status, page, per_page: 25 },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const linkableQuery = useQuery({
    queryKey: ['employees-linkable-users', branchId],
    queryFn: async () => {
      const response = await api.get<LinkableUsersResponse>(`/api/branches/${branchId}/employees/linkable-users`)
      return response.data
    },
    enabled: Boolean(branchId && canManage),
  })

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['employees', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['employees-linkable-users', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['me'] }),
    ])
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const sensitive = canViewSensitive
        ? {
            national_id: form.national_id || null,
            date_of_birth: form.date_of_birth || null,
            address: form.address || null,
            emergency_contact_name: form.emergency_contact_name || null,
            emergency_contact_phone: form.emergency_contact_phone || null,
            notes: form.notes || null,
          }
        : {}

      const payload: Record<string, unknown> = {
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email || null,
        phone: form.phone || null,
        job_title: form.job_title || null,
        department: form.department || null,
        employment_type: form.employment_type,
        hire_date: form.hire_date,
        user_id: form.user_id ? Number(form.user_id) : null,
        ...sensitive,
      }

      if (editing) {
        const response = await api.patch<EmployeeMutationResponse>(`/api/branches/${branchId}/employees/${editing.id}`, payload)
        return response.data
      }

      const response = await api.post<EmployeeMutationResponse>(`/api/branches/${branchId}/employees`, {
        ...payload,
        status: form.status,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: editing ? 'Employee updated.' : 'Employee created.' })
      setShowForm(false)
      setEditing(null)
      setForm(emptyEmployeeForm)
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const transitionMutation = useMutation({
    mutationFn: async ({ employee, next }: { employee: Employee; next: EmployeeStatus }) => {
      const response = await api.post<EmployeeMutationResponse>(`/api/branches/${branchId}/employees/${employee.id}/transition`, {
        status: next,
      })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Employee status updated.' })
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const archiveMutation = useMutation({
    mutationFn: async (employee: Employee) => {
      await api.delete(`/api/branches/${branchId}/employees/${employee.id}`)
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Employee archived. The record and its history are preserved.' })
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const openCreate = () => {
    setEditing(null)
    setForm(emptyEmployeeForm)
    setFeedback(null)
    setShowForm(true)
  }

  const openEdit = (employee: Employee) => {
    setEditing(employee)
    setForm({
      first_name: employee.first_name,
      last_name: employee.last_name,
      email: employee.email ?? '',
      phone: employee.phone ?? '',
      job_title: employee.job_title ?? '',
      department: employee.department ?? '',
      employment_type: employee.employment_type,
      hire_date: employee.hire_date ?? '',
      status: employee.status,
      user_id: employee.user ? String(employee.user.id) : '',
      national_id: employee.sensitive.national_id ?? '',
      date_of_birth: employee.sensitive.date_of_birth ?? '',
      address: employee.sensitive.address ?? '',
      emergency_contact_name: employee.sensitive.emergency_contact_name ?? '',
      emergency_contact_phone: employee.sensitive.emergency_contact_phone ?? '',
      notes: employee.sensitive.notes ?? '',
    })
    setFeedback(null)
    setShowForm(true)
  }

  const update = (patch: Partial<EmployeeFormState>) => setForm((current) => ({ ...current, ...patch }))

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    saveMutation.mutate()
  }

  const runTransition = (employee: Employee, next: EmployeeStatus) => {
    if (next === 'terminated' && !window.confirm(`Terminate ${employee.full_name}? This will revoke their branch access.`)) return
    transitionMutation.mutate({ employee, next })
  }

  if (!meQuery.data) {
    return (
      <AppShell>
        <LoadingState text="Loading branch access..." />
      </AppShell>
    )
  }

  if (!branch) {
    return (
      <AppShell>
        <PermissionDeniedState
          title="No branch membership"
          description="Your account is active but is not assigned to a branch. Ask a platform administrator for access."
        />
      </AppShell>
    )
  }

  if (!canView) {
    return (
      <AppShell>
        <PermissionDeniedState description="Your role does not include permission to view employee records." />
      </AppShell>
    )
  }

  const employees = employeesQuery.data?.data ?? []
  const meta = employeesQuery.data?.meta
  const statuses = employeesQuery.data?.statuses ?? []
  const linkableUsers = linkableQuery.data?.data ?? []
  const showSensitiveForm = canViewSensitive

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Employees</h1>
            <p className="text-muted-foreground">
              {branch.name} ({branch.code}) — employee records and lifecycle
            </p>
          </div>
          {canManage && <Button onClick={openCreate}>Add employee</Button>}
        </div>

        {feedback && (
          <div
            role={feedback.type === 'error' ? 'alert' : 'status'}
            className={
              feedback.type === 'error'
                ? 'rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive'
                : 'rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-700'
            }
          >
            {feedback.text}
          </div>
        )}

        {showForm && canManage && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{editing ? `Edit ${editing.full_name}` : 'New employee'}</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="space-y-4" onSubmit={handleSubmit}>
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label htmlFor="first_name">First name</Label>
                    <Input id="first_name" required value={form.first_name} onChange={(e) => update({ first_name: e.target.value })} />
                  </div>
                  <div>
                    <Label htmlFor="last_name">Last name</Label>
                    <Input id="last_name" required value={form.last_name} onChange={(e) => update({ last_name: e.target.value })} />
                  </div>
                  <div>
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" value={form.email} onChange={(e) => update({ email: e.target.value })} />
                  </div>
                  <div>
                    <Label htmlFor="phone">Phone</Label>
                    <Input id="phone" value={form.phone} onChange={(e) => update({ phone: e.target.value })} />
                  </div>
                  <div>
                    <Label htmlFor="job_title">Job title</Label>
                    <Input id="job_title" value={form.job_title} onChange={(e) => update({ job_title: e.target.value })} />
                  </div>
                  <div>
                    <Label htmlFor="department">Department</Label>
                    <Input id="department" value={form.department} onChange={(e) => update({ department: e.target.value })} />
                  </div>
                  <div>
                    <Label htmlFor="employment_type">Employment type</Label>
                    <select id="employment_type" className={selectClass} value={form.employment_type} onChange={(e) => update({ employment_type: e.target.value })}>
                      {Object.entries(employmentTypeLabels).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <Label htmlFor="hire_date">Hire date</Label>
                    <Input id="hire_date" type="date" required value={form.hire_date} onChange={(e) => update({ hire_date: e.target.value })} />
                  </div>
                  {!editing && (
                    <div>
                      <Label htmlFor="status">Initial status</Label>
                      <select id="status" className={selectClass} value={form.status} onChange={(e) => update({ status: e.target.value as EmployeeStatus })}>
                        <option value="hired">Hired</option>
                        <option value="active">Active</option>
                      </select>
                    </div>
                  )}
                  <div>
                    <Label htmlFor="user_id">Linked user account (optional)</Label>
                    <select id="user_id" className={selectClass} value={form.user_id} onChange={(e) => update({ user_id: e.target.value })}>
                      <option value="">Not linked</option>
                      {editing?.user && (
                        <option value={String(editing.user.id)}>{editing.user.name} ({editing.user.email})</option>
                      )}
                      {linkableUsers.map((user) => (
                        <option key={user.id} value={String(user.id)}>{user.name} ({user.email})</option>
                      ))}
                    </select>
                    <p className="mt-1 text-xs text-muted-foreground">Linking does not change access. Manage ERP access in Users and roles.</p>
                  </div>
                </div>

                {showSensitiveForm && (
                  <div className="space-y-4 rounded-md border p-4">
                    <div className="text-sm font-medium">Sensitive employee data</div>
                    <div className="grid gap-4 md:grid-cols-2">
                      <div>
                        <Label htmlFor="national_id">National ID</Label>
                        <Input id="national_id" value={form.national_id} onChange={(e) => update({ national_id: e.target.value })} />
                      </div>
                      <div>
                        <Label htmlFor="date_of_birth">Date of birth</Label>
                        <Input id="date_of_birth" type="date" value={form.date_of_birth} onChange={(e) => update({ date_of_birth: e.target.value })} />
                      </div>
                      <div className="md:col-span-2">
                        <Label htmlFor="address">Address</Label>
                        <Input id="address" value={form.address} onChange={(e) => update({ address: e.target.value })} />
                      </div>
                      <div>
                        <Label htmlFor="emergency_contact_name">Emergency contact</Label>
                        <Input id="emergency_contact_name" value={form.emergency_contact_name} onChange={(e) => update({ emergency_contact_name: e.target.value })} />
                      </div>
                      <div>
                        <Label htmlFor="emergency_contact_phone">Emergency phone</Label>
                        <Input id="emergency_contact_phone" value={form.emergency_contact_phone} onChange={(e) => update({ emergency_contact_phone: e.target.value })} />
                      </div>
                      <div className="md:col-span-2">
                        <Label htmlFor="notes">Internal notes</Label>
                        <Input id="notes" value={form.notes} onChange={(e) => update({ notes: e.target.value })} />
                      </div>
                    </div>
                  </div>
                )}

                <div className="flex gap-2">
                  <Button type="submit" disabled={saveMutation.isPending}>
                    {saveMutation.isPending ? 'Saving...' : editing ? 'Save changes' : 'Create employee'}
                  </Button>
                  <Button type="button" variant="outline" onClick={() => { setShowForm(false); setEditing(null) }}>
                    Cancel
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        <div className="flex flex-col gap-3 md:flex-row">
          <Input
            placeholder="Search name, employee number, email or phone"
            value={search}
            onChange={(event) => { setSearch(event.target.value); setPage(1) }}
          />
          <select
            className={selectClass}
            value={status}
            onChange={(event) => { setStatus(event.target.value); setPage(1) }}
            aria-label="Employee status"
          >
            <option value="current">Current employees</option>
            {statuses.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
            <option value="all">All</option>
          </select>
        </div>

        {employeesQuery.isLoading && <LoadingState text="Loading employees..." />}
        {employeesQuery.isError && <ErrorState description="Employee records could not be loaded." retry={() => employeesQuery.refetch()} />}

        {!employeesQuery.isLoading && !employeesQuery.isError && employees.length === 0 && (
          <EmptyState
            title="No employees found"
            description={canManage ? 'Create the first employee record for this branch.' : 'No employee records match the current filters.'}
            action={canManage ? <Button onClick={openCreate}>Add employee</Button> : undefined}
          />
        )}

        {employees.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Employee records</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {employees.map((employee) => (
                <div key={employee.id} className="rounded-md border p-4">
                  <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{employee.full_name}</span>
                        <span className={`rounded-full border px-2 py-0.5 text-xs ${statusClass(employee.status)}`}>
                          {employeeStatusLabels[employee.status]}
                        </span>
                        <span className="text-xs text-muted-foreground">{employee.employee_number}</span>
                      </div>
                      <div className="text-sm text-muted-foreground">
                        {[employee.job_title, employee.department, employmentTypeLabels[employee.employment_type]]
                          .filter(Boolean)
                          .join(' · ') || 'No role details'}
                      </div>
                      <div className="text-sm text-muted-foreground">
                        {[employee.email, employee.phone].filter(Boolean).join(' · ') || 'No contact details'}
                      </div>
                      <div className="text-xs text-muted-foreground">
                        Hired {employee.hire_date ?? '—'}
                        {employee.termination_date ? ` · Terminated ${employee.termination_date}` : ''}
                        {employee.user ? ` · Linked user: ${employee.user.email}` : ' · Not linked to a user account'}
                      </div>
                      {employee.sensitive_redacted && (
                        <div className="text-xs text-muted-foreground">Sensitive fields are hidden for your role.</div>
                      )}
                    </div>

                    {canManage && employee.status !== 'archived' && (
                      <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => openEdit(employee)}>Edit</Button>
                        {employee.allowed_transitions
                          .filter((next) => next !== 'archived')
                          .map((next) => (
                            <Button
                              key={next}
                              variant="outline"
                              onClick={() => runTransition(employee, next)}
                              disabled={transitionMutation.isPending}
                            >
                              {next === 'terminated' ? 'Terminate' : `Mark ${employeeStatusLabels[next].toLowerCase()}`}
                            </Button>
                          ))}
                        {employee.status === 'terminated' && (
                          <Button
                            variant="outline"
                            onClick={() => {
                              if (window.confirm(`Archive ${employee.full_name}? The record and history will be kept.`)) {
                                archiveMutation.mutate(employee)
                              }
                            }}
                            disabled={archiveMutation.isPending}
                          >
                            Archive
                          </Button>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              ))}

              {meta && meta.last_page > 1 && (
                <div className="flex items-center justify-between pt-2 text-sm">
                  <span className="text-muted-foreground">Page {meta.current_page} of {meta.last_page} · {meta.total} employees</span>
                  <div className="flex gap-2">
                    <Button variant="outline" disabled={meta.current_page <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Previous</Button>
                    <Button variant="outline" disabled={meta.current_page >= meta.last_page} onClick={() => setPage((value) => value + 1)}>Next</Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  )
}
