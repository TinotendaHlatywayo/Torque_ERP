import { FormEvent, useDeferredValue, useState } from 'react'
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
import type { Customer, CustomerListResponse, CustomerMutationResponse } from '@/lib/customers'

type Feedback = { type: 'error' | 'success'; text: string }

const emptyForm = {
  name: '',
  phone: '',
  email: '',
  address: '',
  notes: '',
  is_active: true,
}

export default function CustomersPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.customers.view') ?? false
  const canManage = branch?.permissions.includes('branch.customers.manage') ?? false

  const [search, setSearch] = useState('')
  const deferredSearch = useDeferredValue(search)
  const [status, setStatus] = useState('active')
  const [page, setPage] = useState(1)
  const perPage = 25
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<Customer | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [duplicateCandidates, setDuplicateCandidates] = useState<Customer[]>([])

  const customersQuery = useQuery({
    queryKey: ['customers', branchId, { search: deferredSearch, status, page, perPage }],
    queryFn: async () => {
      const response = await api.get<CustomerListResponse>(`/api/branches/${branchId}/customers`, {
        params: {
          q: deferredSearch || undefined,
          status,
          page,
          per_page: perPage,
        },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['customers', branchId] })
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name,
        phone: form.phone || null,
        email: form.email || null,
        address: form.address || null,
        notes: form.notes || null,
        is_active: form.is_active,
      }

      if (editing) {
        const response = await api.patch<CustomerMutationResponse>(`/api/branches/${branchId}/customers/${editing.id}`, payload)
        return response.data
      }

      const response = await api.post<CustomerMutationResponse>(`/api/branches/${branchId}/customers`, payload)
      return response.data
    },
    onSuccess: async (data) => {
      setDuplicateCandidates(data.duplicate_candidates ?? [])
      setFeedback({
        type: data.duplicate_warning ? 'error' : 'success',
        text: data.duplicate_warning
          ? 'Customer created, but similar records already exist. Review the duplicates below.'
          : editing
            ? 'Customer updated.'
            : 'Customer created.',
      })
      if (!data.duplicate_warning) {
        setForm(emptyForm)
        setEditing(null)
        setShowForm(false)
      }
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const archiveMutation = useMutation({
    mutationFn: async (customer: Customer) => {
      await api.delete(`/api/branches/${branchId}/customers/${customer.id}`)
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Customer archived.' })
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm)
    setDuplicateCandidates([])
    setFeedback(null)
    setShowForm(true)
  }

  const openEdit = (customer: Customer) => {
    setEditing(customer)
    setForm({
      name: customer.name,
      phone: customer.phone ?? '',
      email: customer.email ?? '',
      address: customer.address ?? '',
      notes: customer.notes ?? '',
      is_active: customer.is_active,
    })
    setDuplicateCandidates([])
    setFeedback(null)
    setShowForm(true)
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    saveMutation.mutate()
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
        <PermissionDeniedState description="Your role does not include permission to view customers." />
      </AppShell>
    )
  }

  const customers = customersQuery.data?.data ?? []
  const meta = customersQuery.data?.meta

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Customers</h1>
            <p className="text-muted-foreground">
              {branch.name} ({branch.code}) — branch customer records
            </p>
          </div>
          {canManage && (
            <Button onClick={openCreate}>Add customer</Button>
          )}
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

        {duplicateCandidates.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Possible duplicate customers</CardTitle>
            </CardHeader>
            <CardContent>
              <ul className="space-y-2 text-sm">
                {duplicateCandidates.map((customer) => (
                  <li key={customer.id} className="rounded-md border p-3">
                    <div className="font-medium">{customer.name}</div>
                    <div className="text-muted-foreground">
                      {[customer.phone, customer.email].filter(Boolean).join(' · ') || 'No contact details'}
                    </div>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        )}

        <div className="flex flex-col gap-3 md:flex-row">
          <Input
            placeholder="Search name, phone or email"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
          />
          <select
            className="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={status}
            onChange={(event) => {
              setStatus(event.target.value)
              setPage(1)
            }}
            aria-label="Customer status"
          >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="all">All</option>
          </select>
        </div>

        {showForm && canManage && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{editing ? 'Edit customer' : 'Add customer'}</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="grid gap-4 md:grid-cols-2" onSubmit={handleSubmit}>
                <div className="space-y-2">
                  <Label htmlFor="customer-name">Name</Label>
                  <Input
                    id="customer-name"
                    value={form.name}
                    onChange={(event) => setForm({ ...form, name: event.target.value })}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="customer-phone">Phone</Label>
                  <Input
                    id="customer-phone"
                    value={form.phone}
                    onChange={(event) => setForm({ ...form, phone: event.target.value })}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="customer-email">Email</Label>
                  <Input
                    id="customer-email"
                    type="email"
                    value={form.email}
                    onChange={(event) => setForm({ ...form, email: event.target.value })}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="customer-address">Address</Label>
                  <Input
                    id="customer-address"
                    value={form.address}
                    onChange={(event) => setForm({ ...form, address: event.target.value })}
                  />
                </div>
                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="customer-notes">Notes</Label>
                  <Input
                    id="customer-notes"
                    value={form.notes}
                    onChange={(event) => setForm({ ...form, notes: event.target.value })}
                  />
                </div>
                {editing && (
                  <div className="space-y-2">
                    <Label htmlFor="customer-status">Status</Label>
                    <select
                      id="customer-status"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={form.is_active ? 'active' : 'inactive'}
                      onChange={(event) => setForm({ ...form, is_active: event.target.value === 'active' })}
                    >
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                )}
                <div className="md:col-span-2 flex gap-2">
                  <Button type="submit" disabled={saveMutation.isPending}>
                    {saveMutation.isPending ? 'Saving...' : editing ? 'Update customer' : 'Create customer'}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={() => {
                      setShowForm(false)
                      setEditing(null)
                      setForm(emptyForm)
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        {customersQuery.isLoading && <LoadingState text="Loading customers..." />}

        {customersQuery.isError && (
          <ErrorState
            title="Customers could not be loaded"
            description={apiErrorMessage(customersQuery.error)}
            retry={() => void customersQuery.refetch()}
          />
        )}

        {customersQuery.data && customers.length === 0 && (
          <EmptyState title="No customers found" description="Create the first customer for this branch." />
        )}

        {customers.length > 0 && (
          <Card>
            <CardContent className="pt-6">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="border-b text-muted-foreground">
                    <tr>
                      <th className="py-3 pr-4 font-medium" scope="col">Name</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Contact</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Status</th>
                      {canManage && <th className="py-3 font-medium text-right" scope="col">Actions</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {customers.map((customer) => (
                      <tr key={customer.id} className="border-b last:border-0">
                        <td className="py-3 pr-4">
                          <div className="font-medium">{customer.name}</div>
                          {customer.address && <div className="text-xs text-muted-foreground">{customer.address}</div>}
                        </td>
                        <td className="py-3 pr-4 text-muted-foreground">
                          {[customer.phone, customer.email].filter(Boolean).join(' · ') || 'No contact details'}
                        </td>
                        <td className="py-3 pr-4">
                          <span className={customer.is_active ? 'text-green-700' : 'text-muted-foreground'}>
                            {customer.is_active ? 'Active' : 'Archived'}
                          </span>
                        </td>
                        {canManage && (
                          <td className="py-3 text-right">
                            <div className="flex justify-end gap-2">
                              <Button size="sm" variant="ghost" onClick={() => openEdit(customer)}>
                                Edit
                              </Button>
                              {customer.is_active && (
                                <Button size="sm" variant="ghost" onClick={() => archiveMutation.mutate(customer)}>
                                  Archive
                                </Button>
                              )}
                            </div>
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {meta && meta.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">
                    Page {meta.current_page} of {meta.last_page} ({meta.total} customers)
                  </span>
                  <div className="flex gap-2">
                    <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
                      Previous
                    </Button>
                    <Button size="sm" variant="outline" disabled={page >= meta.last_page} onClick={() => setPage((value) => value + 1)}>
                      Next
                    </Button>
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
