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
import { Customer, CustomerListResponse } from '@/lib/customers'
import { PaginatedProducts, Product } from '@/lib/catalogue'
import {
  JobActionResponse,
  JobCustomer,
  JobInvoice,
  JobPartResponse,
  JobRecord,
  JobService,
  JobUser,
  ListResponse,
} from '@/lib/jobs'

type Feedback = { type: 'error' | 'success'; text: string }
type ActionName = 'estimate' | 'approve' | 'start' | 'cancel' | 'reopen' | 'invoice'

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
const emptyJobForm = {
  customer_id: '',
  technician_id: '',
  vehicle_make: '',
  vehicle_model: '',
  vehicle_registration: '',
  vehicle_year: '',
  complaint: '',
}
const emptyLabourForm = { workshop_service_id: '', description: '', hours: '1', rate_minor: '1000' }
const emptyPartForm = { product_id: '', unit_id: '', quantity: '1', unit_price_minor: '' }

function money(minor: number, currency = 'USD') {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100)
}

export default function JobsPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id ?? ''
  const currency = 'USD'
  const canView = branch?.permissions.includes('branch.jobs.view') ?? false
  const canManage = branch?.permissions.includes('branch.jobs.manage') ?? false
  const canViewSales = branch?.permissions.includes('branch.sales.view') ?? false

  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [selectedJobId, setSelectedJobId] = useState<string | null>(null)
  const [jobForm, setJobForm] = useState(emptyJobForm)
  const [labourForm, setLabourForm] = useState(emptyLabourForm)
  const [partForm, setPartForm] = useState(emptyPartForm)
  const [approvalReference, setApprovalReference] = useState('')
  const [approvalChannel, setApprovalChannel] = useState('phone')
  const [diagnosis, setDiagnosis] = useState('')
  const [cancelReason, setCancelReason] = useState('')
  const [invoiceId, setInvoiceId] = useState('')

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['jobs', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['job', branchId, selectedJobId ?? ''] }),
    ])
  }

  const jobsQuery = useQuery({
    queryKey: ['jobs', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<JobRecord>>(`/api/branches/${branchId}/jobs`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const jobQuery = useQuery({
    queryKey: ['job', branchId, selectedJobId],
    queryFn: async () => {
      const response = await api.get<JobActionResponse>(`/api/branches/${branchId}/jobs/${selectedJobId!}`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView && selectedJobId),
  })

  const customersQuery = useQuery({
    queryKey: ['customers', branchId, 'jobs'],
    queryFn: async () => {
      const response = await api.get<CustomerListResponse>(`/api/branches/${branchId}/customers`, {
        params: { status: 'active', per_page: 100 },
      })
      return response.data.data
    },
    enabled: Boolean(branchId && canManage),
  })

  const usersQuery = useQuery({
    queryKey: ['branch-users', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<JobUser>>(`/api/branches/${branchId}/users`)
      return response.data.data
    },
    enabled: Boolean(branchId && canManage),
  })

  const servicesQuery = useQuery({
    queryKey: ['workshop-services', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<JobService>>(`/api/branches/${branchId}/services`)
      return response.data.data
    },
    enabled: Boolean(branchId && canManage),
  })

  const productsQuery = useQuery({
    queryKey: ['catalogue', branchId, 'products', 'jobs'],
    queryFn: async () => {
      const response = await api.get<PaginatedProducts>(`/api/branches/${branchId}/products`, {
        params: { status: 'active', per_page: 100 },
      })
      return response.data.data
    },
    enabled: Boolean(branchId && canManage),
  })

  const invoicesQuery = useQuery({
    queryKey: ['sales', branchId, 'jobs'],
    queryFn: async () => {
      const response = await api.get<ListResponse<JobInvoice>>(`/api/branches/${branchId}/sales`)
      return response.data.data
    },
    enabled: Boolean(branchId && canManage && canViewSales),
  })

  const resetJobForm = () => {
    setJobForm(emptyJobForm)
    setLabourForm(emptyLabourForm)
    setPartForm(emptyPartForm)
    setApprovalReference('')
    setApprovalChannel('phone')
    setDiagnosis('')
    setCancelReason('')
    setInvoiceId('')
  }

  const reportError = (error: unknown) => setFeedback({ type: 'error', text: apiErrorMessage(error) })

  const createMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post<JobActionResponse>(`/api/branches/${branchId}/jobs`, {
        customer_id: jobForm.customer_id,
        technician_id: jobForm.technician_id ? Number(jobForm.technician_id) : null,
        vehicle_make: jobForm.vehicle_make,
        vehicle_model: jobForm.vehicle_model,
        vehicle_registration: jobForm.vehicle_registration || null,
        vehicle_year: jobForm.vehicle_year || null,
        complaint: jobForm.complaint,
      })
      return response.data.data
    },
    onSuccess: async (job) => {
      setFeedback({ type: 'success', text: `Job ${job.job_number} created.` })
      resetJobForm()
      setSelectedJobId(job.id)
      await refresh()
    },
    onError: reportError,
  })

  const addLabourMutation = useMutation({
    mutationFn: async () => {
      const payload = labourForm.workshop_service_id
        ? { workshop_service_id: labourForm.workshop_service_id }
        : {
            description: labourForm.description,
            hours: labourForm.hours,
            rate_minor: Number(labourForm.rate_minor),
          }
      await api.post(`/api/branches/${branchId}/jobs/${selectedJobId}/labour`, payload)
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Labour line added.' })
      setLabourForm(emptyLabourForm)
      await refresh()
    },
    onError: reportError,
  })

  const addPartMutation = useMutation({
    mutationFn: async () => {
      const payload: Record<string, unknown> = {
        product_id: partForm.product_id,
        unit_id: partForm.unit_id,
        quantity: partForm.quantity,
      }
      if (partForm.unit_price_minor !== '') {
        payload.unit_price_minor = Number(partForm.unit_price_minor)
      }
      const response = await api.post<JobPartResponse>(
        `/api/branches/${branchId}/jobs/${selectedJobId}/parts`,
        payload,
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      return response.data
    },
    onSuccess: async (result) => {
      setFeedback({
        type: 'success',
        text: result.idempotent_replay ? 'Part line already recorded; no extra stock movement.' : 'Part line added and stock consumed.',
      })
      setPartForm(emptyPartForm)
      await refresh()
    },
    onError: reportError,
  })

  const actionMutation = useMutation({
    mutationFn: async ({ action, payload }: { action: ActionName; payload?: Record<string, unknown> }) => {
      await api.post(`/api/branches/${branchId}/jobs/${selectedJobId}/${action}`, payload ?? {})
    },
    onSuccess: async (_result, variables) => {
      setFeedback({ type: 'success', text: `Job ${variables.action} completed.` })
      await refresh()
    },
    onError: reportError,
  })

  const completeMutation = useMutation({
    mutationFn: async () => {
      if (diagnosis.trim()) {
        await api.patch(`/api/branches/${branchId}/jobs/${selectedJobId}`, { diagnosis })
      }
      await api.post(`/api/branches/${branchId}/jobs/${selectedJobId}/complete`)
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Job completed.' })
      await refresh()
    },
    onError: reportError,
  })

  const submit = (event: FormEvent, action: () => void) => {
    event.preventDefault()
    setFeedback(null)
    action()
  }

  if (meQuery.isLoading) return <AppShell><LoadingState text="Loading jobs..." /></AppShell>
  if (meQuery.isError) return <AppShell><ErrorState description={apiErrorMessage(meQuery.error)} /></AppShell>
  if (!canView) return <AppShell><PermissionDeniedState description="You do not have permission to view workshop jobs." /></AppShell>

  const jobs = jobsQuery.data ?? []
  const job = jobQuery.data ?? null
  const customers: Customer[] = customersQuery.data ?? []
  const users = usersQuery.data ?? []
  const services = servicesQuery.data ?? []
  const products: Product[] = productsQuery.data ?? []
  const invoices = invoicesQuery.data ?? []
  const selectedProduct = products.find((product) => product.id === partForm.product_id)
  const unitOptions = selectedProduct
    ? [
        ...(selectedProduct.base_unit ? [{ id: selectedProduct.base_unit.id, code: selectedProduct.base_unit.code }] : []),
        ...(selectedProduct.alternate_units ?? []).flatMap((productUnit) =>
          productUnit.unit ? [{ id: productUnit.unit.id, code: productUnit.unit.code }] : [],
        ),
      ]
    : []

  const runAction = (action: ActionName, payload?: Record<string, unknown>) => {
    setFeedback(null)
    actionMutation.mutate({ action, payload })
  }

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Workshop jobs</h1>
          <p className="text-sm text-muted-foreground">Job cards, estimates, approvals, parts consumption and completion</p>
        </div>

        {feedback && (
          <div
            aria-live="polite"
            className={`rounded-md border px-3 py-2 text-sm ${feedback.type === 'error' ? 'border-destructive text-destructive' : 'border-green-600 text-green-700'}`}
          >
            {feedback.text}
          </div>
        )}

        <div className="grid gap-6 lg:grid-cols-3">
          <Card className="lg:col-span-2">
            <CardHeader><CardTitle>Job queue</CardTitle></CardHeader>
            <CardContent>
              {jobsQuery.isLoading && <LoadingState text="Loading job queue..." />}
              {jobsQuery.isError && <ErrorState description={apiErrorMessage(jobsQuery.error)} />}
              {jobs.length === 0 && !jobsQuery.isLoading && (
                <EmptyState title="No jobs" description="Create the first workshop job card." />
              )}
              <div className="space-y-2">
                {jobs.map((item: JobRecord) => (
                  <button
                    key={item.id}
                    type="button"
                    aria-label={`Open job ${item.job_number}`}
                    onClick={() => {
                      setSelectedJobId(item.id)
                      setDiagnosis(item.diagnosis ?? '')
                      setInvoiceId(item.sale_id ?? '')
                    }}
                    className="flex w-full items-center justify-between rounded-md border p-3 text-left hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    <div>
                      <div className="font-medium">{item.job_number}</div>
                      <div className="text-xs text-muted-foreground">
                        {(item.customer as JobCustomer | null)?.name ?? 'Unknown customer'} · {item.vehicle_make} {item.vehicle_model} · {item.line_counts.labour} labour / {item.line_counts.parts} parts
                      </div>
                    </div>
                    <div className="text-right">
                      <span className="rounded-full border px-2 py-0.5 text-xs">{item.status}</span>
                      <div className="text-xs text-muted-foreground">{money(item.estimate_minor, currency)}</div>
                    </div>
                  </button>
                ))}
              </div>
            </CardContent>
          </Card>

          {canManage && (
            <Card>
              <CardHeader><CardTitle>New job card</CardTitle></CardHeader>
              <CardContent>
                <form className="space-y-3" onSubmit={(event) => submit(event, () => createMutation.mutate())}>
                  <div>
                    <Label htmlFor="job-customer">Customer</Label>
                    <select id="job-customer" className={selectClass} required value={jobForm.customer_id} onChange={(event) => setJobForm({ ...jobForm, customer_id: event.target.value })}>
                      <option value="">Select customer</option>
                      {customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}</option>)}
                    </select>
                  </div>
                  <div>
                    <Label htmlFor="job-technician">Technician</Label>
                    <select id="job-technician" className={selectClass} value={jobForm.technician_id} onChange={(event) => setJobForm({ ...jobForm, technician_id: event.target.value })}>
                      <option value="">Unassigned</option>
                      {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                    </select>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div><Label htmlFor="job-make">Make</Label><Input id="job-make" required value={jobForm.vehicle_make} onChange={(event) => setJobForm({ ...jobForm, vehicle_make: event.target.value })} /></div>
                    <div><Label htmlFor="job-model">Model</Label><Input id="job-model" required value={jobForm.vehicle_model} onChange={(event) => setJobForm({ ...jobForm, vehicle_model: event.target.value })} /></div>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div><Label htmlFor="job-registration">Registration</Label><Input id="job-registration" value={jobForm.vehicle_registration} onChange={(event) => setJobForm({ ...jobForm, vehicle_registration: event.target.value })} /></div>
                    <div><Label htmlFor="job-year">Year</Label><Input id="job-year" value={jobForm.vehicle_year} onChange={(event) => setJobForm({ ...jobForm, vehicle_year: event.target.value })} /></div>
                  </div>
                  <div><Label htmlFor="job-complaint">Complaint</Label><Input id="job-complaint" required value={jobForm.complaint} onChange={(event) => setJobForm({ ...jobForm, complaint: event.target.value })} /></div>
                  <Button type="submit" disabled={createMutation.isPending}>Create job</Button>
                </form>
              </CardContent>
            </Card>
          )}
        </div>

        {selectedJobId && (
          <Card>
            <CardHeader>
              <CardTitle>Job detail</CardTitle>
            </CardHeader>
            <CardContent className="space-y-5">
              {jobQuery.isLoading && <LoadingState text="Loading job detail..." />}
              {jobQuery.isError && <ErrorState description={apiErrorMessage(jobQuery.error)} />}
              {job && (
                <>
                  <div className="grid gap-3 text-sm md:grid-cols-3">
                    <div><strong>Job:</strong> {job.job_number} · {job.status}</div>
                    <div><strong>Customer:</strong> {job.customer?.name ?? 'Unknown'}</div>
                    <div><strong>Technician:</strong> {job.technician?.name ?? 'Unassigned'}</div>
                    <div><strong>Vehicle:</strong> {job.vehicle_make} {job.vehicle_model} {job.vehicle_registration}</div>
                    <div><strong>Estimate:</strong> {money(job.estimate_minor, currency)}</div>
                    <div><strong>Invoice:</strong> {job.invoice?.sale_number ?? 'Not linked'}</div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div>
                      <h3 className="font-medium">Labour</h3>
                      <div className="space-y-1 text-sm">
                        {job.labour_lines.length === 0 && <p className="text-muted-foreground">No labour lines.</p>}
                        {job.labour_lines.map((line) => (
                          <div key={line.id} className="flex justify-between rounded border px-2 py-1">
                            <span>{line.description} ({line.hours}h)</span>
                            <span>{money(line.amount_minor, currency)}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                    <div>
                      <h3 className="font-medium">Parts</h3>
                      <div className="space-y-1 text-sm">
                        {job.part_lines.length === 0 && <p className="text-muted-foreground">No part lines.</p>}
                        {job.part_lines.map((line) => (
                          <div key={line.id} className="flex justify-between rounded border px-2 py-1">
                            <span>{line.product?.name ?? 'Product'} × {line.quantity} {line.unit?.code ?? ''}</span>
                            <span>{money(line.amount_minor, currency)}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>

                  {canManage && job.status === 'draft' && (
                    <div className="grid gap-6 md:grid-cols-2">
                      <form className="space-y-3 rounded-md border p-3" onSubmit={(event) => submit(event, () => addLabourMutation.mutate())}>
                        <h3 className="font-medium">Add labour</h3>
                        <div>
                          <Label htmlFor="labour-service">Catalogue service</Label>
                          <select id="labour-service" className={selectClass} value={labourForm.workshop_service_id} onChange={(event) => setLabourForm({ ...labourForm, workshop_service_id: event.target.value })}>
                            <option value="">Custom labour</option>
                            {services.map((service) => <option key={service.id} value={service.id}>{service.code} · {service.name}</option>)}
                          </select>
                        </div>
                        {!labourForm.workshop_service_id && (
                          <>
                            <div><Label htmlFor="labour-description">Description</Label><Input id="labour-description" required value={labourForm.description} onChange={(event) => setLabourForm({ ...labourForm, description: event.target.value })} /></div>
                            <div className="grid grid-cols-2 gap-2">
                              <div><Label htmlFor="labour-hours">Hours</Label><Input id="labour-hours" required min="0.01" step="0.01" value={labourForm.hours} onChange={(event) => setLabourForm({ ...labourForm, hours: event.target.value })} /></div>
                              <div><Label htmlFor="labour-rate">Rate (minor)</Label><Input id="labour-rate" required min="0" step="1" value={labourForm.rate_minor} onChange={(event) => setLabourForm({ ...labourForm, rate_minor: event.target.value })} /></div>
                            </div>
                          </>
                        )}
                        <Button type="submit" disabled={addLabourMutation.isPending}>Add labour</Button>
                      </form>

                      <form className="space-y-3 rounded-md border p-3" onSubmit={(event) => submit(event, () => addPartMutation.mutate())}>
                        <h3 className="font-medium">Add part</h3>
                        <div>
                          <Label htmlFor="part-product">Product</Label>
                          <select
                            id="part-product"
                            className={selectClass}
                            required
                            value={partForm.product_id}
                            onChange={(event) => setPartForm({ ...partForm, product_id: event.target.value, unit_id: '' })}
                          >
                            <option value="">Select product</option>
                            {products.map((product) => <option key={product.id} value={product.id}>{product.sku} · {product.name}</option>)}
                          </select>
                        </div>
                        <div>
                          <Label htmlFor="part-unit">Unit</Label>
                          <select id="part-unit" className={selectClass} required value={partForm.unit_id} onChange={(event) => setPartForm({ ...partForm, unit_id: event.target.value })}>
                            <option value="">Select unit</option>
                            {unitOptions.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}
                          </select>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                          <div><Label htmlFor="part-quantity">Quantity</Label><Input id="part-quantity" required min="0.0001" step="0.0001" value={partForm.quantity} onChange={(event) => setPartForm({ ...partForm, quantity: event.target.value })} /></div>
                          <div><Label htmlFor="part-price">Unit price (minor)</Label><Input id="part-price" min="0" step="1" value={partForm.unit_price_minor} onChange={(event) => setPartForm({ ...partForm, unit_price_minor: event.target.value })} placeholder="Catalogue price" /></div>
                        </div>
                        <Button type="submit" disabled={addPartMutation.isPending || !partForm.unit_id}>Add part</Button>
                      </form>
                    </div>
                  )}

                  {canManage && (
                    <div className="flex flex-wrap gap-2 border-t pt-4">
                      {job.status === 'draft' && <Button onClick={() => runAction('estimate')} disabled={actionMutation.isPending}>Create estimate</Button>}
                      {job.status === 'estimated' && (
                        <>
                          <Input aria-label="Approval reference" value={approvalReference} onChange={(event) => setApprovalReference(event.target.value)} placeholder="Customer approval reference" />
                          <Input aria-label="Approval channel" value={approvalChannel} onChange={(event) => setApprovalChannel(event.target.value)} placeholder="phone / email / in_person" />
                          <Button onClick={() => runAction('approve', { approval_reference: approvalReference || 'Frontend approval', approval_channel: approvalChannel })} disabled={actionMutation.isPending}>Record approval</Button>
                        </>
                      )}
                      {job.status === 'approved' && <Button onClick={() => runAction('start')} disabled={actionMutation.isPending}>Start work</Button>}
                      {job.status === 'in_progress' && (
                        <>
                          <Input aria-label="Diagnosis" value={diagnosis} onChange={(event) => setDiagnosis(event.target.value)} placeholder="Diagnosis / work performed" />
                          <Button onClick={() => completeMutation.mutate()} disabled={completeMutation.isPending}>Complete job</Button>
                        </>
                      )}
                      {['draft', 'estimated', 'approved', 'in_progress'].includes(job.status) && (
                        <>
                          <Input aria-label="Cancellation reason" value={cancelReason} onChange={(event) => setCancelReason(event.target.value)} placeholder="Cancellation reason" />
                          <Button variant="outline" onClick={() => runAction('cancel', { reason: cancelReason || 'Cancelled from job card' })} disabled={actionMutation.isPending}>Cancel job</Button>
                        </>
                      )}
                      {job.status === 'cancelled' && <Button variant="outline" onClick={() => runAction('reopen')} disabled={actionMutation.isPending}>Reopen job</Button>}
                      {job.sale_id === null && job.status !== 'cancelled' && canViewSales && (
                        <>
                          <select aria-label="Invoice to link" className={selectClass} value={invoiceId} onChange={(event) => setInvoiceId(event.target.value)}>
                            <option value="">Select invoice</option>
                            {invoices.map((invoice) => <option key={invoice.id} value={invoice.id}>{invoice.sale_number} · {money(invoice.total_minor, currency)}</option>)}
                          </select>
                          <Button variant="outline" onClick={() => runAction('invoice', { sale_id: invoiceId })} disabled={actionMutation.isPending || !invoiceId}>Link invoice</Button>
                        </>
                      )}
                    </div>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  )
}
