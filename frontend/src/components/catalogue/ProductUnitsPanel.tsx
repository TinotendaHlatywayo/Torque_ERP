import { FormEvent, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState } from '@/components/feedback/StateComponents'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { ConversionResponse, Product, ProductUnit, Unit } from '@/lib/catalogue'

type UnitDraft = {
  unit_id: string
  conversion_factor: string
  selling_price: string
  is_active: boolean
}

const emptyDraft: UnitDraft = {
  unit_id: '',
  conversion_factor: '',
  selling_price: '0.00',
  is_active: true,
}

export function ProductUnitsPanel({
  branchId,
  product,
  units,
  canManage,
  onChanged,
}: {
  branchId: string
  product: Product
  units: Unit[]
  canManage: boolean
  onChanged: () => Promise<void>
}) {
  const alternatives = product.alternate_units ?? []
  const baseUnit = product.base_unit
  const [editing, setEditing] = useState<ProductUnit | null>(null)
  const [draft, setDraft] = useState<UnitDraft>(emptyDraft)
  const [feedback, setFeedback] = useState<string | null>(null)
  const [fromUnitId, setFromUnitId] = useState(baseUnit?.id ?? '')
  const [toUnitId, setToUnitId] = useState(baseUnit?.id ?? '')
  const [quantity, setQuantity] = useState('1')
  const [conversionResult, setConversionResult] = useState<string | null>(null)

  const configuredUnitIds = new Set([baseUnit?.id, ...alternatives.map((item) => item.unit?.id)])
  const unitOptions = units.filter((unit) => unit.is_active && !configuredUnitIds.has(unit.id))

  const resetDraft = () => {
    setEditing(null)
    setDraft(emptyDraft)
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        unit_id: draft.unit_id,
        conversion_factor: draft.conversion_factor,
        selling_price: draft.selling_price,
        is_active: draft.is_active,
      }

      if (editing) {
        const response = await api.patch(
          `/api/branches/${branchId}/products/${product.id}/units/${editing.id}`,
          {
            conversion_factor: payload.conversion_factor,
            selling_price: payload.selling_price,
            is_active: payload.is_active,
          },
        )
        return response.data
      }

      const response = await api.post(`/api/branches/${branchId}/products/${product.id}/units`, payload)
      return response.data
    },
    onSuccess: async () => {
      setFeedback(editing ? 'Alternate unit updated.' : 'Alternate unit added.')
      resetDraft()
      await onChanged()
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const statusMutation = useMutation({
    mutationFn: async ({ item, active }: { item: ProductUnit; active: boolean }) => {
      if (active) {
        await api.patch(`/api/branches/${branchId}/products/${product.id}/units/${item.id}`, { is_active: true })
      } else {
        await api.delete(`/api/branches/${branchId}/products/${product.id}/units/${item.id}`)
      }
    },
    onSuccess: async () => {
      setFeedback('Alternate unit status updated.')
      await onChanged()
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const conversionMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post<ConversionResponse>(
        `/api/branches/${branchId}/products/${product.id}/convert`,
        {
          from_unit_id: effectiveFrom,
          to_unit_id: effectiveTo,
          quantity,
        },
      )
      return response.data.data
    },
    onSuccess: (data) => {
      setConversionResult(`${data.quantity} ${data.to_unit.code}`)
      setFeedback(null)
    },
    onError: (error) => {
      setConversionResult(null)
      setFeedback(apiErrorMessage(error))
    },
  })

  const save = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)
    saveMutation.mutate()
  }

  const activeConversionUnits = baseUnit?.is_active
    ? [
        { id: baseUnit.id, label: `${baseUnit.code} — ${baseUnit.name} (base)` },
        ...alternatives
          .filter((item) => item.is_active && item.unit?.is_active)
          .map((item) => ({ id: item.unit!.id, label: `${item.unit!.code} — ${item.unit!.name}` })),
      ]
    : []

  const effectiveFrom = activeConversionUnits.some((unit) => unit.id === fromUnitId)
    ? fromUnitId
    : baseUnit?.id ?? ''
  const effectiveTo = activeConversionUnits.some((unit) => unit.id === toUnitId)
    ? toUnitId
    : baseUnit?.id ?? ''

  return (
    <div className="space-y-4">
      {feedback && <div role="status" className="rounded-md border bg-muted p-3 text-sm">{feedback}</div>}

      {canManage && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              {editing ? 'Edit alternate unit' : 'Add alternate unit'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={save}>
              <div className="space-y-2">
                <Label htmlFor="alternate-unit">Unit</Label>
                {editing ? (
                  <div className="flex h-10 items-center rounded-md border bg-muted px-3 text-sm">
                    {editing.unit?.code} — {editing.unit?.name}
                  </div>
                ) : (
                  <select
                    id="alternate-unit"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={draft.unit_id}
                    onChange={(event) => setDraft({ ...draft, unit_id: event.target.value })}
                    required
                  >
                    <option value="">Select an active unit</option>
                    {unitOptions.map((unit) => (
                      <option key={unit.id} value={unit.id}>{unit.code} — {unit.name}</option>
                    ))}
                  </select>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="alternate-factor">
                  Conversion factor {baseUnit ? `(1 unit = ? ${baseUnit.code})` : ''}
                </Label>
                <Input
                  id="alternate-factor"
                  value={draft.conversion_factor}
                  onChange={(event) => setDraft({ ...draft, conversion_factor: event.target.value })}
                  inputMode="decimal"
                  placeholder="10"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="alternate-price">Selling price for this unit</Label>
                <Input
                  id="alternate-price"
                  value={draft.selling_price}
                  onChange={(event) => setDraft({ ...draft, selling_price: event.target.value })}
                  inputMode="decimal"
                  required
                />
              </div>
              <div className="flex flex-wrap items-center gap-4 pt-6 text-sm">
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
                {editing && <Button type="button" variant="outline" onClick={resetDraft}>Cancel</Button>}
                <Button type="submit" disabled={saveMutation.isPending || (!editing && unitOptions.length === 0)}>
                  {saveMutation.isPending ? 'Saving...' : editing ? 'Save unit' : 'Add unit'}
                </Button>
              </div>
              {!editing && unitOptions.length === 0 && (
                <p className="text-sm text-muted-foreground md:col-span-2">
                  No unconfigured active units are available. Create another unit in the Units tab first.
                </p>
              )}
            </form>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Alternate units</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="mb-4 text-sm text-muted-foreground">
            Base unit: {baseUnit ? `${baseUnit.code} — ${baseUnit.name}` : 'Not available'}
          </p>
          {alternatives.length === 0 && (
            <EmptyState
              title="No alternate units"
              description="Add a conversion factor when this product is bought or sold in packs."
            />
          )}
          {alternatives.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b text-muted-foreground">
                  <tr>
                    <th className="py-3 pr-4 font-medium" scope="col">Unit</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Equals</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Price</th>
                    <th className="py-3 pr-4 font-medium" scope="col">Status</th>
                    {canManage && <th className="py-3 text-right font-medium" scope="col">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {alternatives.map((item) => (
                    <tr key={item.id} className="border-b last:border-0">
                      <td className="py-3 pr-4 font-medium">{item.unit?.code ?? '—'}</td>
                      <td className="py-3 pr-4">
                        {item.conversion_factor} {baseUnit?.code ?? 'base units'}
                      </td>
                      <td className="py-3 pr-4">{item.selling_price} {product.currency_code}</td>
                      <td className="py-3 pr-4">{item.is_active ? 'Active' : 'Archived'}</td>
                      {canManage && (
                        <td className="space-x-2 whitespace-nowrap py-3 text-right">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              setEditing(item)
                              setDraft({
                                unit_id: item.unit?.id ?? '',
                                conversion_factor: item.conversion_factor,
                                selling_price: item.selling_price,
                                is_active: item.is_active,
                              })
                              setFeedback(null)
                            }}
                          >
                            Edit
                          </Button>
                          <Button
                            size="sm"
                            variant={item.is_active ? 'destructive' : 'secondary'}
                            disabled={statusMutation.isPending}
                            onClick={() => {
                              if (item.is_active && !window.confirm(`Archive ${item.unit?.code ?? 'this unit'}?`)) return
                              statusMutation.mutate({ item, active: !item.is_active })
                            }}
                          >
                            {item.is_active ? 'Archive' : 'Activate'}
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

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Unit conversion</CardTitle>
        </CardHeader>
        <CardContent>
          {activeConversionUnits.length === 0 && (
            <p className="mb-4 text-sm text-muted-foreground">
              Conversion requires an active base unit for this product.
            </p>
          )}
          <form
            className="grid gap-4 md:grid-cols-4"
            onSubmit={(event) => {
              event.preventDefault()
              setConversionResult(null)
              conversionMutation.mutate()
            }}
          >
            <div className="space-y-2">
              <Label htmlFor="convert-from">From</Label>
              <select
                id="convert-from"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={effectiveFrom}
                onChange={(event) => {
                  setFromUnitId(event.target.value)
                  setConversionResult(null)
                }}
              >
                {activeConversionUnits.map((unit) => (
                  <option key={unit.id} value={unit.id}>{unit.label}</option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="convert-quantity">Quantity</Label>
              <Input
                id="convert-quantity"
                value={quantity}
                onChange={(event) => {
                  setQuantity(event.target.value)
                  setConversionResult(null)
                }}
                inputMode="decimal"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="convert-to">To</Label>
              <select
                id="convert-to"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={effectiveTo}
                onChange={(event) => {
                  setToUnitId(event.target.value)
                  setConversionResult(null)
                }}
              >
                {activeConversionUnits.map((unit) => (
                  <option key={unit.id} value={unit.id}>{unit.label}</option>
                ))}
              </select>
            </div>
            <div className="flex items-end">
              <Button
                type="submit"
                className="w-full"
                disabled={conversionMutation.isPending || !effectiveFrom || !effectiveTo}
              >
                {conversionMutation.isPending ? 'Converting...' : 'Convert'}
              </Button>
            </div>
          </form>
          {conversionResult && (
            <p className="mt-4 rounded-md border bg-muted p-3 text-sm">
              Result: <strong>{conversionResult}</strong>
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
