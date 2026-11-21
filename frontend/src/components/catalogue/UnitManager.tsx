import { FormEvent, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, LoadingState } from '@/components/feedback/StateComponents'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { Unit } from '@/lib/catalogue'

type Draft = {
  code: string
  name: string
  symbol: string
  allow_decimal: boolean
  is_active: boolean
}

const emptyDraft: Draft = { code: '', name: '', symbol: '', allow_decimal: false, is_active: true }

function draftFrom(unit: Unit): Draft {
  return {
    code: unit.code,
    name: unit.name,
    symbol: unit.symbol ?? '',
    allow_decimal: unit.allow_decimal,
    is_active: unit.is_active,
  }
}

export function UnitManager({
  branchId,
  units,
  loading,
  canManage,
  onChanged,
}: {
  branchId: string
  units: Unit[]
  loading: boolean
  canManage: boolean
  onChanged: () => Promise<void>
}) {
  const [editing, setEditing] = useState<Unit | null>(null)
  const [draft, setDraft] = useState<Draft>(emptyDraft)
  const [feedback, setFeedback] = useState<string | null>(null)

  const reset = () => {
    setEditing(null)
    setDraft(emptyDraft)
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        code: draft.code.trim(),
        name: draft.name.trim(),
        symbol: draft.symbol.trim() || null,
        allow_decimal: draft.allow_decimal,
        is_active: draft.is_active,
      }

      if (editing) {
        const response = await api.patch(`/api/branches/${branchId}/units/${editing.id}`, payload)
        return response.data
      }

      const response = await api.post(`/api/branches/${branchId}/units`, payload)
      return response.data
    },
    onSuccess: async () => {
      setFeedback(editing ? 'Unit updated.' : 'Unit created.')
      reset()
      await onChanged()
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const statusMutation = useMutation({
    mutationFn: async ({ unit, active }: { unit: Unit; active: boolean }) => {
      if (active) {
        await api.patch(`/api/branches/${branchId}/units/${unit.id}`, { is_active: true })
      } else {
        await api.delete(`/api/branches/${branchId}/units/${unit.id}`)
      }
    },
    onSuccess: async () => {
      setFeedback('Unit status updated.')
      await onChanged()
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)
    saveMutation.mutate()
  }

  return (
    <div className="space-y-4">
      {feedback && <div role="status" className="rounded-md border bg-muted p-3 text-sm">{feedback}</div>}

      {canManage && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{editing ? `Edit ${editing.code}` : 'Add unit of measure'}</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
              <div className="space-y-2">
                <Label htmlFor="unit-code">Code</Label>
                <Input
                  id="unit-code"
                  value={draft.code}
                  onChange={(event) => setDraft({ ...draft, code: event.target.value })}
                  placeholder="PCS"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="unit-name">Name</Label>
                <Input
                  id="unit-name"
                  value={draft.name}
                  onChange={(event) => setDraft({ ...draft, name: event.target.value })}
                  placeholder="Pieces"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="unit-symbol">Symbol</Label>
                <Input
                  id="unit-symbol"
                  value={draft.symbol}
                  onChange={(event) => setDraft({ ...draft, symbol: event.target.value })}
                  placeholder="Optional"
                />
              </div>
              <div className="flex flex-wrap items-center gap-4 pt-6 text-sm">
                <label className="inline-flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={draft.allow_decimal}
                    onChange={(event) => setDraft({ ...draft, allow_decimal: event.target.checked })}
                  />
                  Allows decimals
                </label>
                <label className="inline-flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={draft.is_active}
                    onChange={(event) => setDraft({ ...draft, is_active: event.target.checked })}
                  />
                  Active
                </label>
              </div>
              <div className="flex items-end gap-2 md:col-span-2 md:justify-end">
                {editing && <Button type="button" variant="outline" onClick={reset}>Cancel</Button>}
                <Button type="submit" disabled={saveMutation.isPending}>
                  {saveMutation.isPending ? 'Saving...' : editing ? 'Save unit' : 'Create unit'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Units of measure</CardTitle>
        </CardHeader>
        <CardContent>
          {loading && <LoadingState text="Loading units..." />}
          {!loading && units.length === 0 && (
            <EmptyState title="No units yet" description="Create a base unit before adding products." />
          )}
          {!loading && units.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b text-muted-foreground">
                  <tr>
                    <th className="py-3 pr-4 font-medium" scope="col">Code</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Name</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Decimals</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Status</th>
                    {canManage && <th className="py-3 text-right font-medium" scope="col">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {units.map((unit) => (
                    <tr key={unit.id} className="border-b last:border-0">
                      <td className="py-3 pr-4 font-medium">{unit.code}</td>
                      <td className="py-3 pr-4">
                        {unit.name}
                        {unit.symbol && <span className="ml-2 text-xs text-muted-foreground">{unit.symbol}</span>}
                      </td>
                      <td className="py-3 pr-4">{unit.allow_decimal ? 'Allowed' : 'Whole numbers'}</td>
                      <td className="py-3 pr-4">{unit.is_active ? 'Active' : 'Archived'}</td>
                      {canManage && (
                        <td className="space-x-2 whitespace-nowrap py-3 text-right">
                          <Button size="sm" variant="outline" onClick={() => {
                            setEditing(unit)
                            setDraft(draftFrom(unit))
                            setFeedback(null)
                          }}>
                            Edit
                          </Button>
                          <Button
                            size="sm"
                            variant={unit.is_active ? 'destructive' : 'secondary'}
                            disabled={statusMutation.isPending}
                            onClick={() => {
                              if (unit.is_active && !window.confirm(`Archive ${unit.code}?`)) return
                              statusMutation.mutate({ unit, active: !unit.is_active })
                            }}
                          >
                            {unit.is_active ? 'Archive' : 'Activate'}
                          </Button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
