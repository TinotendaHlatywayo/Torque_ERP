import { FormEvent, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, LoadingState } from '@/components/feedback/StateComponents'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { Category } from '@/lib/catalogue'

type Draft = {
  name: string
  parent_id: string
  description: string
  is_active: boolean
}

const emptyDraft: Draft = { name: '', parent_id: '', description: '', is_active: true }

function draftFrom(category: Category): Draft {
  return {
    name: category.name,
    parent_id: category.parent_id ?? '',
    description: category.description ?? '',
    is_active: category.is_active,
  }
}

export function CategoryManager({
  branchId,
  categories,
  loading,
  canManage,
  onChanged,
}: {
  branchId: string
  categories: Category[]
  loading: boolean
  canManage: boolean
  onChanged: () => Promise<void>
}) {
  const [editing, setEditing] = useState<Category | null>(null)
  const [draft, setDraft] = useState<Draft>(emptyDraft)
  const [feedback, setFeedback] = useState<string | null>(null)

  const reset = () => {
    setEditing(null)
    setDraft(emptyDraft)
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: draft.name.trim(),
        parent_id: draft.parent_id || null,
        description: draft.description.trim() || null,
        is_active: draft.is_active,
      }

      if (editing) {
        const response = await api.patch(`/api/branches/${branchId}/categories/${editing.id}`, payload)
        return response.data
      }

      const response = await api.post(`/api/branches/${branchId}/categories`, payload)
      return response.data
    },
    onSuccess: async () => {
      setFeedback(editing ? 'Category updated.' : 'Category created.')
      reset()
      await onChanged()
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const statusMutation = useMutation({
    mutationFn: async ({ category, active }: { category: Category; active: boolean }) => {
      if (active) {
        await api.patch(`/api/branches/${branchId}/categories/${category.id}`, { is_active: true })
      } else {
        await api.delete(`/api/branches/${branchId}/categories/${category.id}`)
      }
    },
    onSuccess: async () => {
      setFeedback('Category status updated.')
      await onChanged()
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)
    saveMutation.mutate()
  }

  const parents = categories.filter((category) => category.id !== editing?.id)

  return (
    <div className="space-y-4">
      {feedback && <div role="status" className="rounded-md border bg-muted p-3 text-sm">{feedback}</div>}

      {canManage && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{editing ? `Edit ${editing.name}` : 'Add category'}</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
              <div className="space-y-2">
                <Label htmlFor="category-name">Name</Label>
                <Input
                  id="category-name"
                  value={draft.name}
                  onChange={(event) => setDraft({ ...draft, name: event.target.value })}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="category-parent">Parent category</Label>
                <select
                  id="category-parent"
                  className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  value={draft.parent_id}
                  onChange={(event) => setDraft({ ...draft, parent_id: event.target.value })}
                >
                  <option value="">No parent</option>
                  {parents.map((category) => (
                    <option key={category.id} value={category.id}>{category.name}</option>
                  ))}
                </select>
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="category-description">Description</Label>
                <Input
                  id="category-description"
                  value={draft.description}
                  onChange={(event) => setDraft({ ...draft, description: event.target.value })}
                  placeholder="Optional"
                />
              </div>
              <label className="inline-flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={draft.is_active}
                  onChange={(event) => setDraft({ ...draft, is_active: event.target.checked })}
                />
                Active
              </label>
              <div className="flex items-end gap-2 md:justify-end">
                {editing && <Button type="button" variant="outline" onClick={reset}>Cancel</Button>}
                <Button type="submit" disabled={saveMutation.isPending}>
                  {saveMutation.isPending ? 'Saving...' : editing ? 'Save category' : 'Create category'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Categories</CardTitle>
        </CardHeader>
        <CardContent>
          {loading && <LoadingState text="Loading categories..." />}
          {!loading && categories.length === 0 && (
            <EmptyState title="No categories yet" description="Create a category to organise products." />
          )}
          {!loading && categories.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b text-muted-foreground">
                  <tr>
                    <th className="py-3 pr-4 font-medium" scope="col">Category</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Parent</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Products</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Status</th>
                    {canManage && <th className="py-3 text-right font-medium" scope="col">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {categories.map((category) => {
                    const parent = categories.find((item) => item.id === category.parent_id)
                    return (
                      <tr key={category.id} className="border-b last:border-0">
                        <td className="py-3 pr-4">
                          <div className="font-medium">{category.name}</div>
                          <div className="text-xs text-muted-foreground">{category.slug}</div>
                        </td>
                        <td className="py-3 pr-4 text-muted-foreground">{parent?.name ?? '—'}</td>
                        <td className="py-3 pr-4">{category.product_count ?? 0}</td>
                        <td className="py-3 pr-4">{category.is_active ? 'Active' : 'Archived'}</td>
                        {canManage && (
                          <td className="space-x-2 whitespace-nowrap py-3 text-right">
                            <Button size="sm" variant="outline" onClick={() => {
                              setEditing(category)
                              setDraft(draftFrom(category))
                              setFeedback(null)
                            }}>
                              Edit
                            </Button>
                            <Button
                              size="sm"
                              variant={category.is_active ? 'destructive' : 'secondary'}
                              disabled={statusMutation.isPending}
                              onClick={() => {
                                if (category.is_active && !window.confirm(`Archive ${category.name}?`)) return
                                statusMutation.mutate({ category, active: !category.is_active })
                              }}
                            >
                              {category.is_active ? 'Archive' : 'Activate'}
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
    </div>
  )
}
