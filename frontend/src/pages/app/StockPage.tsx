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
import type { PaginatedProducts } from '@/lib/catalogue'
import type { StockLevelListResponse, StockMovementListResponse } from '@/lib/stock'

type Feedback = { type: 'error' | 'success'; text: string }

export default function StockPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.inventory.view') ?? false
  const canManage = branch?.permissions.includes('branch.inventory.manage') ?? false

  const [productFilter, setProductFilter] = useState('')
  const deferredProductFilter = useDeferredValue(productFilter)
  const [movementType, setMovementType] = useState('')
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [selectedProductId, setSelectedProductId] = useState('')
  const [postingType, setPostingType] = useState<'opening' | 'correction'>('opening')
  const [quantity, setQuantity] = useState('')
  const [unitId, setUnitId] = useState('')
  const [reason, setReason] = useState('')

  const productsQuery = useQuery({
    queryKey: ['stock-products', branchId],
    queryFn: async () => {
      const response = await api.get<PaginatedProducts>(`/api/branches/${branchId}/products`, {
        params: { status: 'all', per_page: 100 },
      })
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const levelsQuery = useQuery({
    queryKey: ['stock-levels', branchId, deferredProductFilter],
    queryFn: async () => {
      const response = await api.get<StockLevelListResponse>(`/api/branches/${branchId}/stock/levels`, {
        params: { q: deferredProductFilter || undefined, per_page: 100 },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const movementsQuery = useQuery({
    queryKey: ['stock-movements', branchId, { productFilter: deferredProductFilter, movementType }],
    queryFn: async () => {
      const response = await api.get<StockMovementListResponse>(`/api/branches/${branchId}/stock/movements`, {
        params: {
          product_id: deferredProductFilter || undefined,
          type: movementType || undefined,
          per_page: 50,
        },
      })
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const selectedProduct = productsQuery.data?.find((product) => product.id === selectedProductId) ?? null

  const availableUnits = selectedProduct
    ? [
        selectedProduct.base_unit ? { id: selectedProduct.base_unit.id, code: selectedProduct.base_unit.code, name: selectedProduct.base_unit.name } : null,
        ...(selectedProduct.alternate_units ?? [])
          .filter((unit) => unit.is_active && unit.unit)
          .map((unit) => ({ id: unit.unit!.id, code: unit.unit!.code, name: unit.unit!.name })),
      ].filter(Boolean) as { id: string; code: string; name: string }[]
    : []

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['stock-levels', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['stock-movements', branchId] }),
    ])
  }

  const postMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post(
        `/api/branches/${branchId}/products/${selectedProductId}/stock/movements`,
        {
          type: postingType,
          quantity,
          unit_id: unitId,
          reason: reason || undefined,
        },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      return response.data
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Stock movement posted.' })
      setQuantity('')
      setReason('')
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const reverseMutation = useMutation({
    mutationFn: async ({ id, reversalReason }: { id: string; reversalReason: string }) => {
      await api.post(
        `/api/branches/${branchId}/stock/movements/${id}/reverse`,
        { reason: reversalReason },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Movement reversed.' })
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!selectedProductId || !unitId) return
    postMutation.mutate()
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
        <PermissionDeniedState description="Your role does not include permission to view stock." />
      </AppShell>
    )
  }

  const levels = levelsQuery.data?.data ?? []
  const movements = movementsQuery.data?.data ?? []

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Stock ledger</h1>
          <p className="text-muted-foreground">
            {branch.name} ({branch.code}) — balances and movements
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
              <CardTitle className="text-base">Post stock movement</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="grid gap-4 md:grid-cols-2" onSubmit={handleSubmit}>
                <div className="space-y-2">
                  <Label htmlFor="stock-product">Product</Label>
                  <select
                    id="stock-product"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={selectedProductId}
                    onChange={(event) => {
                      setSelectedProductId(event.target.value)
                      setUnitId('')
                    }}
                  >
                    <option value="">Select product</option>
                    {productsQuery.data?.map((product) => (
                      <option key={product.id} value={product.id}>
                        {product.sku} — {product.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="stock-type">Type</Label>
                  <select
                    id="stock-type"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={postingType}
                    onChange={(event) => setPostingType(event.target.value as 'opening' | 'correction')}
                  >
                    <option value="opening">Opening</option>
                    <option value="correction">Correction</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="stock-quantity">Quantity</Label>
                  <Input
                    id="stock-quantity"
                    value={quantity}
                    onChange={(event) => setQuantity(event.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="stock-unit">Unit</Label>
                  <select
                    id="stock-unit"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={unitId}
                    onChange={(event) => setUnitId(event.target.value)}
                    disabled={!selectedProduct}
                  >
                    <option value="">Select unit</option>
                    {availableUnits.map((unit) => (
                      <option key={unit.id} value={unit.id}>
                        {unit.code} — {unit.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="stock-reason">Reason</Label>
                  <Input
                    id="stock-reason"
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                    placeholder={postingType === 'correction' ? 'Required for corrections' : 'Optional'}
                  />
                </div>
                <div className="md:col-span-2">
                  <Button type="submit" disabled={postMutation.isPending || !selectedProductId || !unitId}>
                    {postMutation.isPending ? 'Posting...' : 'Post movement'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Balances</CardTitle>
          </CardHeader>
          <CardContent>
            <Input
              placeholder="Filter product"
              value={productFilter}
              onChange={(event) => setProductFilter(event.target.value)}
              className="mb-4"
            />
            {levelsQuery.isLoading && <LoadingState text="Loading balances..." />}
            {levelsQuery.isError && (
              <ErrorState
                title="Balances could not be loaded"
                description={apiErrorMessage(levelsQuery.error)}
                retry={() => void levelsQuery.refetch()}
              />
            )}
            {levels.length === 0 && !levelsQuery.isLoading && (
              <EmptyState title="No balances found" description="Post opening stock to create a balance." />
            )}
            {levels.length > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="border-b text-muted-foreground">
                    <tr>
                      <th className="py-3 pr-4 font-medium" scope="col">Product</th>
                      <th className="py-3 pr-4 font-medium" scope="col">On hand</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Reserved</th>
                      <th className="py-3 font-medium" scope="col">Low</th>
                    </tr>
                  </thead>
                  <tbody>
                    {levels.map((level, index) => (
                      <tr key={`${level.product?.id ?? index}`} className="border-b last:border-0">
                        <td className="py-3 pr-4">
                          <div className="font-medium">{level.product?.name ?? 'Unknown product'}</div>
                          <div className="text-xs text-muted-foreground">{level.product?.sku}</div>
                        </td>
                        <td className="py-3 pr-4">{level.quantity_on_hand}</td>
                        <td className="py-3 pr-4">{level.quantity_reserved}</td>
                        <td className="py-3">{level.is_low ? 'Yes' : 'No'}</td>
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
            <CardTitle className="text-base">Movements</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mb-4 flex flex-col gap-3 md:flex-row">
              <select
                className="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={movementType}
                onChange={(event) => setMovementType(event.target.value)}
                aria-label="Movement type"
              >
                <option value="">All types</option>
                <option value="opening">Opening</option>
                <option value="sale">Sale</option>
                <option value="correction">Correction</option>
                <option value="reversal">Reversal</option>
              </select>
            </div>
            {movementsQuery.isLoading && <LoadingState text="Loading movements..." />}
            {movementsQuery.isError && (
              <ErrorState
                title="Movements could not be loaded"
                description={apiErrorMessage(movementsQuery.error)}
                retry={() => void movementsQuery.refetch()}
              />
            )}
            {movements.length === 0 && !movementsQuery.isLoading && (
              <EmptyState title="No movements found" description="Posted stock movements will appear here." />
            )}
            {movements.length > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="border-b text-muted-foreground">
                    <tr>
                      <th className="py-3 pr-4 font-medium" scope="col">Type</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Product</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Quantity</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Balance</th>
                      <th className="py-3 pr-4 font-medium" scope="col">Reason</th>
                      <th className="py-3 font-medium text-right" scope="col">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {movements.map((movement) => (
                      <tr key={movement.id} className="border-b last:border-0">
                        <td className="py-3 pr-4">{movement.movement_type}</td>
                        <td className="py-3 pr-4">{movement.product?.name ?? 'Unknown'}</td>
                        <td className="py-3 pr-4">{movement.quantity}</td>
                        <td className="py-3 pr-4">{movement.balance_after}</td>
                        <td className="py-3 pr-4">{movement.reason}</td>
                        <td className="py-3 text-right">
                          {!movement.is_reversed && movement.movement_type !== 'reversal' && canManage && (
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => {
                                const reversalReason = window.prompt('Reason for reversal')
                                if (reversalReason) {
                                  reverseMutation.mutate({ id: movement.id, reversalReason })
                                }
                              }}
                            >
                              Reverse
                            </Button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  )
}
