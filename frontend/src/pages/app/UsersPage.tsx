import { FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { Membership, MembershipListResponse, RolesResponse } from '@/lib/access'

type Feedback = { type: 'error' | 'success'; text: string }

const emptyForm = { name: '', email: '', password: '', role: 'cashier' }

export default function UsersPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const canView = branch?.permissions.includes('branch.users.view') ?? false
  const canManage = branch?.permissions.includes('branch.users.manage') ?? false
  const canViewRoles = branch?.permissions.includes('branch.roles.view') ?? false

  const [form, setForm] = useState(emptyForm)
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [drafts, setDrafts] = useState<Record<string, { role: string; is_active: boolean }>>({})

  const usersQuery = useQuery({
    queryKey: ['branch-users', branch?.id],
    queryFn: async () => {
      const response = await api.get<MembershipListResponse>(`/api/branches/${branch?.id}/users`)
      return response.data
    },
    enabled: Boolean(branch && canView),
  })

  const rolesQuery = useQuery({
    queryKey: ['branch-roles', branch?.id],
    queryFn: async () => {
      const response = await api.get<RolesResponse>(`/api/branches/${branch?.id}/roles`)
      return response.data
    },
    enabled: Boolean(branch && canViewRoles),
  })

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['branch-users', branch?.id] }),
      queryClient.invalidateQueries({ queryKey: ['me'] }),
    ])
  }

  const createMutation = useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const response = await api.post(`/api/branches/${branch?.id}/users`, payload)
      return response.data
    },
    onSuccess: async () => {
      setForm(emptyForm)
      setFeedback({ type: 'success', text: 'User added to this branch.' })
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const updateMutation = useMutation({
    mutationFn: async ({ id, role, is_active }: { id: string; role: string; is_active: boolean }) => {
      const response = await api.patch(`/api/branches/${branch?.id}/users/${id}`, { role, is_active })
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Membership updated.' })
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const removeMutation = useMutation({
    mutationFn: async (id: string) => {
      await api.delete(`/api/branches/${branch?.id}/users/${id}`)
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Membership removed.' })
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const roles = rolesQuery.data?.roles ?? []

  const handleCreate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)

    const payload: Record<string, unknown> = {
      email: form.email,
      role: form.role,
    }

    if (form.name.trim()) payload.name = form.name.trim()
    if (form.password) payload.password = form.password

    createMutation.mutate(payload)
  }

  const draftFor = (membership: Membership) => drafts[membership.id] ?? {
    role: membership.role,
    is_active: membership.is_active,
  }

  const setDraft = (membership: Membership, patch: Partial<{ role: string; is_active: boolean }>) => {
    setDrafts((current) => ({
      ...current,
      [membership.id]: { ...draftFor(membership), ...patch },
    }))
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
        <PermissionDeniedState description="Your role does not include permission to view branch users." />
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Users and roles</h1>
          <p className="text-muted-foreground">
            {branch.name} ({branch.code}) — your role: {branch.role_label}
          </p>
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

        {canManage && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Add a branch member</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="grid gap-4 md:grid-cols-2" onSubmit={handleCreate}>
                <div className="space-y-2">
                  <Label htmlFor="new-name">Full name</Label>
                  <Input
                    id="new-name"
                    value={form.name}
                    onChange={(event) => setForm({ ...form, name: event.target.value })}
                    placeholder="Required for a new account"
                    autoComplete="off"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="new-email">Email</Label>
                  <Input
                    id="new-email"
                    type="email"
                    value={form.email}
                    onChange={(event) => setForm({ ...form, email: event.target.value })}
                    placeholder="Existing users can be attached by email"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="new-password">Temporary password</Label>
                  <Input
                    id="new-password"
                    type="password"
                    value={form.password}
                    onChange={(event) => setForm({ ...form, password: event.target.value })}
                    placeholder="Required for a new account"
                    autoComplete="new-password"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="new-role">Role</Label>
                  <select
                    id="new-role"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={form.role}
                    onChange={(event) => setForm({ ...form, role: event.target.value })}
                  >
                    {roles.map((role) => (
                      <option key={role.name} value={role.name}>{role.label}</option>
                    ))}
                  </select>
                </div>
                <div className="md:col-span-2">
                  <Button type="submit" disabled={createMutation.isPending}>
                    {createMutation.isPending ? 'Adding...' : 'Add member'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Branch members</CardTitle>
          </CardHeader>
          <CardContent>
            {usersQuery.isLoading && <LoadingState text="Loading members..." />}

            {usersQuery.isError && (
              <ErrorState
                title="Members could not be loaded"
                description={apiErrorMessage(usersQuery.error)}
                retry={() => void usersQuery.refetch()}
              />
            )}

            {usersQuery.data && usersQuery.data.data.length === 0 && (
              <EmptyState title="No branch members yet" description="Add the first user to start operating this branch." />
            )}

            {usersQuery.data && usersQuery.data.data.length > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="border-b text-muted-foreground">
                    <tr>
                      <th className="py-3 pr-4 font-medium" scope="col">User</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Role</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Status</th>
                      {canManage && <th className="py-3 font-medium text-right" scope="col">Actions</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {usersQuery.data.data.map((membership) => {
                      const draft = draftFor(membership)
                      const busy = updateMutation.isPending || removeMutation.isPending

                      return (
                        <tr key={membership.id} className="border-b last:border-0">
                          <td className="py-3 pr-4">
                            <div className="font-medium">{membership.user.name}</div>
                            <div className="text-muted-foreground">{membership.user.email}</div>
                          </td>
                          <td className="py-3 pr-4">
                            {canManage ? (
                              <select
                                aria-label={`Role for ${membership.user.name}`}
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={draft.role}
                                onChange={(event) => setDraft(membership, { role: event.target.value })}
                              >
                                {roles.map((role) => (
                                  <option key={role.name} value={role.name}>{role.label}</option>
                                ))}
                              </select>
                            ) : (
                              membership.role_label
                            )}
                          </td>
                          <td className="py-3 pr-4">
                            {canManage ? (
                              <label className="inline-flex items-center gap-2">
                                <input
                                  type="checkbox"
                                  checked={draft.is_active}
                                  onChange={(event) => setDraft(membership, { is_active: event.target.checked })}
                                />
                                <span>{draft.is_active ? 'Active' : 'Inactive'}</span>
                              </label>
                            ) : (
                              <span>{membership.is_active ? 'Active' : 'Inactive'}</span>
                            )}
                          </td>
                          {canManage && (
                            <td className="py-3 text-right space-x-2 whitespace-nowrap">
                              <Button
                                size="sm"
                                variant="outline"
                                disabled={busy}
                                onClick={() => updateMutation.mutate({
                                  id: membership.id,
                                  role: draft.role,
                                  is_active: draft.is_active,
                                })}
                              >
                                Save
                              </Button>
                              <Button
                                size="sm"
                                variant="destructive"
                                disabled={busy}
                                onClick={() => {
                                  if (window.confirm(`Remove ${membership.user.name} from this branch?`)) {
                                    removeMutation.mutate(membership.id)
                                  }
                                }}
                              >
                                Remove
                              </Button>
                            </td>
                          )}
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        {rolesQuery.data && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Role catalogue</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid gap-3 md:grid-cols-2">
                {rolesQuery.data.roles.map((role) => (
                  <div key={role.name} className="rounded-md border p-3">
                    <div className="font-medium">{role.label}</div>
                    <p className="text-xs text-muted-foreground">{role.permissions.length} branch permissions</p>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  )
}
