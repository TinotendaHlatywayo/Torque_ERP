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
import { PaginatedProducts, Product } from '@/lib/catalogue'
import {
  ListResponse,
  StocktakeActionResponse,
  StocktakeRecord,
  StockTransferRecord,
  TransferActionResponse,
} from '@/lib/transfers'

type Feedback = { type: 'error' | 'success'; text: string }
type Tab = 'transfers' | 'stocktakes'
type TransferAction = 'dispatch' | 'in-transit' | 'receive' | 'cancel'
type StocktakeAction = 'review' | 'approve'
type StocktakeLineForm = { product_id: string; counted_quantity: string; reason: string }

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
const emptyTransferForm = { destination_branch_id: '', product_id: '', quantity: '1', reason: '' }
const emptyStocktakeForm = (): { reason: string; lines: StocktakeLineForm[] } => ({
  reason: '',
  lines: [{ product_id: '', counted_quantity: '', reason: '' }],
})

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleString() : 'Not recorded'
}

export default function TransfersPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const [selectedSourceId, setSelectedSourceId] = useState('')
  const [tab, setTab] = useState<Tab>('transfers')
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [selectedTransferId, setSelectedTransferId] = useState<string | null>(null)
  const [selectedStocktakeId, setSelectedStocktakeId] = useState<string | null>(null)
  const [transferForm, setTransferForm] = useState(emptyTransferForm)
  const [destinationProductId, setDestinationProductId] = useState('')
  const [stocktakeForm, setStocktakeForm] = useState(emptyStocktakeForm)
  const [cancelReason, setCancelReason] = useState('')
  const [approvalReference, setApprovalReference] = useState('')

  const sourceBranchId = selectedSourceId || meQuery.data?.branches[0]?.id || ''
  const sourceBranch = meQuery.data?.branches.find((branch) => branch.id === sourceBranchId)
  const canViewTransfers = sourceBranch?.permissions.includes('branch.transfers.view') ?? false
  const canManageTransfers = sourceBranch?.permissions.includes('branch.transfers.manage') ?? false
  const canViewStocktakes = sourceBranch?.permissions.includes('branch.stocktakes.view') ?? false
  const canManageStocktakes = sourceBranch?.permissions.includes('branch.stocktakes.manage') ?? false
  const canApproveStocktakes = sourceBranch?.permissions.includes('branch.stocktakes.approve') ?? false
  const canViewAny = canViewTransfers || canViewStocktakes

  const destinationBranches = (meQuery.data?.branches ?? []).filter(
    (branch) => branch.id !== sourceBranchId && branch.permissions.includes('branch.transfers.manage'),
  )

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['stock-transfers', sourceBranchId] }),
      queryClient.invalidateQueries({ queryKey: ['stock-transfer', sourceBranchId, selectedTransferId ?? ''] }),
      queryClient.invalidateQueries({ queryKey: ['stocktakes', sourceBranchId] }),
      queryClient.invalidateQueries({ queryKey: ['stocktake', sourceBranchId, selectedStocktakeId ?? ''] }),
    ])
  }

  const reportError = (error: unknown) => setFeedback({ type: 'error', text: apiErrorMessage(error) })

  const productsQuery = useQuery({
    queryKey: ['catalogue', sourceBranchId, 'products', 'transfers'],
    queryFn: async () => {
      const response = await api.get<PaginatedProducts>(`/api/branches/${sourceBranchId}/products`, {
        params: { status: 'active', per_page: 100 },
      })
      return response.data.data
    },
    enabled: Boolean(sourceBranchId && (canManageTransfers || canManageStocktakes)),
  })

  const destinationProductsQuery = useQuery({
    queryKey: ['catalogue', transferForm.destination_branch_id, 'products', 'transfers'],
    queryFn: async () => {
      const response = await api.get<PaginatedProducts>(`/api/branches/${transferForm.destination_branch_id}/products`, {
        params: { status: 'active', per_page: 100 },
      })
      return response.data.data
    },
    enabled: Boolean(tab === 'transfers' && canManageTransfers && transferForm.destination_branch_id),
  })

  const transfersQuery = useQuery({
    queryKey: ['stock-transfers', sourceBranchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<StockTransferRecord>>(`/api/branches/${sourceBranchId}/stock-transfers`)
      return response.data.data
    },
    enabled: Boolean(sourceBranchId && canViewTransfers),
  })

  const stocktakesQuery = useQuery({
    queryKey: ['stocktakes', sourceBranchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<StocktakeRecord>>(`/api/branches/${sourceBranchId}/stocktakes`)
      return response.data.data
    },
    enabled: Boolean(sourceBranchId && canViewStocktakes),
  })

  const transferQuery = useQuery({
    queryKey: ['stock-transfer', sourceBranchId, selectedTransferId],
    queryFn: async () => {
      const response = await api.get<TransferActionResponse>(
        `/api/branches/${sourceBranchId}/stock-transfers/${selectedTransferId!}`,
      )
      return response.data.data
    },
    enabled: Boolean(sourceBranchId && canViewTransfers && selectedTransferId),
  })

  const stocktakeQuery = useQuery({
    queryKey: ['stocktake', sourceBranchId, selectedStocktakeId],
    queryFn: async () => {
      const response = await api.get<StocktakeActionResponse>(
        `/api/branches/${sourceBranchId}/stocktakes/${selectedStocktakeId!}`,
      )
      return response.data.data
    },
    enabled: Boolean(sourceBranchId && canViewStocktakes && selectedStocktakeId),
  })

  const createTransferMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post<TransferActionResponse>(`/api/branches/${sourceBranchId}/stock-transfers`, {
        destination_branch_id: transferForm.destination_branch_id,
        product_id: transferForm.product_id,
        unit_id: sourceProduct?.base_unit?.id ?? '',
        destination_product_id: destinationProductId,
        destination_unit_id: destinationProduct?.base_unit?.id ?? '',
        quantity: transferForm.quantity,
        reason: transferForm.reason,
      })
      return response.data.data
    },
    onSuccess: async (transfer) => {
      setFeedback({ type: 'success', text: `Transfer ${transfer.transfer_number} created as a draft.` })
      setTransferForm(emptyTransferForm)
      setDestinationProductId('')
      setSelectedTransferId(transfer.id)
      await refresh()
    },
    onError: reportError,
  })

  const transferActionMutation = useMutation({
    mutationFn: async ({ action, payload }: { action: TransferAction; payload?: Record<string, unknown> }) => {
      const response = await api.post<TransferActionResponse>(
        `/api/branches/${sourceBranchId}/stock-transfers/${selectedTransferId}/${action}`,
        payload ?? {},
      )
      return response.data.data
    },
    onSuccess: async (transfer, variables) => {
      setFeedback({ type: 'success', text: `${transfer.transfer_number} ${variables.action.replace('-', ' ')} completed.` })
      setCancelReason('')
      await refresh()
    },
    onError: reportError,
  })

  const createStocktakeMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post<StocktakeActionResponse>(`/api/branches/${sourceBranchId}/stocktakes`, {
        reason: stocktakeForm.reason,
        lines: stocktakeForm.lines.map((line) => {
          const product = sourceProducts.find((item) => item.id === line.product_id)
          return {
            product_id: line.product_id,
            unit_id: product?.base_unit?.id ?? '',
            counted_quantity: line.counted_quantity,
            reason: line.reason.trim() || null,
          }
        }),
      })
      return response.data.data
    },
    onSuccess: async (stocktake) => {
      setFeedback({ type: 'success', text: `Stocktake ${stocktake.stocktake_number} created as a draft.` })
      setStocktakeForm(emptyStocktakeForm())
      setSelectedStocktakeId(stocktake.id)
      await refresh()
    },
    onError: reportError,
  })

  const stocktakeActionMutation = useMutation({
    mutationFn: async ({ action, payload }: { action: StocktakeAction; payload?: Record<string, unknown> }) => {
      const response = await api.post<StocktakeActionResponse>(
        `/api/branches/${sourceBranchId}/stocktakes/${selectedStocktakeId}/${action}`,
        payload ?? {},
      )
      return response.data.data
    },
    onSuccess: async (stocktake, variables) => {
      setFeedback({ type: 'success', text: `${stocktake.stocktake_number} ${variables.action} completed.` })
      setApprovalReference('')
      await refresh()
    },
    onError: reportError,
  })

  const sourceProducts = productsQuery.data ?? []
  const destinationProducts = destinationProductsQuery.data ?? []
  const sourceProduct = sourceProducts.find((product) => product.id === transferForm.product_id)
  const destinationProduct = destinationProducts.find((product) => product.id === destinationProductId)

  const submitTransfer = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)
    createTransferMutation.mutate()
  }

  const submitStocktake = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)
    createStocktakeMutation.mutate()
  }

  const updateStocktakeLine = (index: number, patch: Partial<StocktakeLineForm>) => {
    setStocktakeForm((current) => ({
      ...current,
      lines: current.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)),
    }))
  }

  if (meQuery.isLoading) {
    return <AppShell><LoadingState text="Loading branch access..." /></AppShell>
  }

  if (meQuery.isError) {
    return (
      <AppShell>
        <ErrorState
          title="Unable to load branch access"
          description={apiErrorMessage(meQuery.error)}
          retry={() => void meQuery.refetch()}
        />
      </AppShell>
    )
  }

  if (!sourceBranch) {
    return <AppShell><EmptyState title="No branch selected" description="You need an active branch membership to use stock transfers." /></AppShell>
  }

  if (!canViewAny) {
    return (
      <AppShell>
        <PermissionDeniedState
          title="Stock operations are not available"
          description="You need a stock transfer or stocktake permission for this branch."
        />
      </AppShell>
    )
  }

  const transfer = transferQuery.data
  const stocktake = stocktakeQuery.data

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Stock transfers and stocktakes</h1>
            <p className="text-sm text-muted-foreground">
              Transfer stock between branches with explicit authorization, and approve counted variances through the canonical ledger.
            </p>
          </div>
          {(meQuery.data?.branches.length ?? 0) > 1 && (
            <div className="w-full md:w-64 space-y-1">
              <Label htmlFor="source-branch">Source branch</Label>
              <select
                id="source-branch"
                className={selectClass}
                value={sourceBranchId}
                onChange={(event) => {
                  setSelectedSourceId(event.target.value)
                  setTransferForm(emptyTransferForm)
                  setDestinationProductId('')
                  setStocktakeForm(emptyStocktakeForm())
                  setSelectedTransferId(null)
                  setSelectedStocktakeId(null)
                  setCancelReason('')
                  setApprovalReference('')
                  setFeedback(null)
                }}
              >
                {meQuery.data?.branches.map((branch) => (
                  <option key={branch.id} value={branch.id}>{branch.name}</option>
                ))}
              </select>
            </div>
          )}
        </div>

        <div className="flex gap-2" aria-label="Stock operation sections">
          <Button type="button" variant={tab === 'transfers' ? 'default' : 'outline'} aria-pressed={tab === 'transfers'} onClick={() => setTab('transfers')}>
            Stock transfers
          </Button>
          <Button type="button" variant={tab === 'stocktakes' ? 'default' : 'outline'} aria-pressed={tab === 'stocktakes'} onClick={() => setTab('stocktakes')}>
            Stocktakes
          </Button>
        </div>

        {feedback && (
          <div
            role={feedback.type === 'error' ? 'alert' : 'status'}
            aria-live="polite"
            className={`rounded-md border px-4 py-2 text-sm ${feedback.type === 'error' ? 'border-destructive text-destructive' : 'border-green-600 text-green-700'}`}
          >
            {feedback.text}
          </div>
        )}

        {tab === 'transfers' && !canViewTransfers && (
          <PermissionDeniedState description="You do not have permission to view stock transfers for this branch." />
        )}

        {tab === 'transfers' && canViewTransfers && (
          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>Transfers</CardTitle></CardHeader>
              <CardContent>
                {transfersQuery.isLoading && <LoadingState text="Loading transfers..." />}
                {transfersQuery.isError && (
                  <ErrorState description={apiErrorMessage(transfersQuery.error)} retry={() => void transfersQuery.refetch()} />
                )}
                {transfersQuery.data?.length === 0 && <EmptyState title="No transfers yet" description="Create the first draft transfer." />}
                {transfersQuery.data && transfersQuery.data.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b text-left">
                          <th className="py-2" scope="col">Number</th>
                          <th className="py-2" scope="col">Destination</th>
                          <th className="py-2" scope="col">Quantity</th>
                          <th className="py-2" scope="col">Status</th>
                          <th className="py-2" scope="col"><span className="sr-only">Actions</span></th>
                        </tr>
                      </thead>
                      <tbody>
                        {transfersQuery.data.map((item) => (
                          <tr key={item.id} className="border-b">
                            <td className="py-2 font-medium">{item.transfer_number}</td>
                            <td className="py-2">{item.destination_branch?.name ?? 'Unknown'}</td>
                            <td className="py-2">{item.quantity}</td>
                            <td className="py-2">{item.status.replace('_', ' ')}</td>
                            <td className="py-2 text-right">
                              <Button type="button" variant="ghost" onClick={() => setSelectedTransferId(item.id)}>View</Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>

            <div className="space-y-6">
              {canManageTransfers && (
                <Card>
                  <CardHeader><CardTitle>Create transfer draft</CardTitle></CardHeader>
                  <CardContent>
                    <form className="space-y-4" onSubmit={submitTransfer}>
                      <div className="space-y-1">
                        <Label htmlFor="transfer-destination">Destination branch</Label>
                        <select
                          id="transfer-destination"
                          className={selectClass}
                          value={transferForm.destination_branch_id}
                          required
                          onChange={(event) => {
                            setTransferForm({ ...transferForm, destination_branch_id: event.target.value })
                            setDestinationProductId('')
                          }}
                        >
                          <option value="">Select an authorized branch</option>
                          {destinationBranches.map((branch) => (
                            <option key={branch.id} value={branch.id}>{branch.name}</option>
                          ))}
                        </select>
                      </div>
                      <div className="space-y-1">
                        <Label htmlFor="transfer-product">Source product</Label>
                        <select
                          id="transfer-product"
                          className={selectClass}
                          value={transferForm.product_id}
                          required
                          onChange={(event) => setTransferForm({ ...transferForm, product_id: event.target.value })}
                        >
                          <option value="">Select a product</option>
                          {sourceProducts.map((product: Product) => (
                            <option key={product.id} value={product.id}>{product.name} ({product.sku})</option>
                          ))}
                        </select>
                        {sourceProduct?.base_unit && <p className="text-xs text-muted-foreground">Source base unit: {sourceProduct.base_unit.code}</p>}
                      </div>
                      <div className="space-y-1">
                        <Label htmlFor="destination-product">Destination product</Label>
                        <select
                          id="destination-product"
                          className={selectClass}
                          value={destinationProductId}
                          required
                          disabled={!transferForm.destination_branch_id}
                          onChange={(event) => setDestinationProductId(event.target.value)}
                        >
                          <option value="">Select a destination product</option>
                          {destinationProducts.map((product: Product) => (
                            <option key={product.id} value={product.id}>{product.name} ({product.sku})</option>
                          ))}
                        </select>
                        {destinationProduct?.base_unit && <p className="text-xs text-muted-foreground">Destination base unit: {destinationProduct.base_unit.code}</p>}
                      </div>
                      <div className="space-y-1">
                        <Label htmlFor="transfer-quantity">Quantity</Label>
                        <Input
                          id="transfer-quantity"
                          value={transferForm.quantity}
                          inputMode="decimal"
                          required
                          onChange={(event) => setTransferForm({ ...transferForm, quantity: event.target.value })}
                        />
                      </div>
                      <div className="space-y-1">
                        <Label htmlFor="transfer-reason">Reason</Label>
                        <Input
                          id="transfer-reason"
                          value={transferForm.reason}
                          required
                          maxLength={500}
                          onChange={(event) => setTransferForm({ ...transferForm, reason: event.target.value })}
                        />
                      </div>
                      <Button type="submit" disabled={createTransferMutation.isPending}>Create draft</Button>
                    </form>
                  </CardContent>
                </Card>
              )}

              {selectedTransferId && (
                <Card>
                  <CardHeader><CardTitle>Transfer detail</CardTitle></CardHeader>
                  <CardContent>
                    {transferQuery.isLoading && <LoadingState text="Loading transfer..." />}
                    {transferQuery.isError && <ErrorState description={apiErrorMessage(transferQuery.error)} />}
                    {transfer && (
                      <div className="space-y-4 text-sm">
                        <dl className="grid grid-cols-2 gap-2">
                          <dt className="text-muted-foreground">Number</dt><dd className="font-medium">{transfer.transfer_number}</dd>
                          <dt className="text-muted-foreground">Status</dt><dd>{transfer.status.replace('_', ' ')}</dd>
                          <dt className="text-muted-foreground">Product</dt><dd>{transfer.product?.name ?? 'Unknown'}</dd>
                          <dt className="text-muted-foreground">Destination</dt><dd>{transfer.destination_product?.name ?? 'Unknown'}</dd>
                          <dt className="text-muted-foreground">Quantity</dt><dd>{transfer.quantity}</dd>
                          <dt className="text-muted-foreground">Dispatched</dt><dd>{formatDate(transfer.dispatched_at)}</dd>
                          <dt className="text-muted-foreground">Received</dt><dd>{formatDate(transfer.received_at)}</dd>
                        </dl>
                        <p><span className="text-muted-foreground">Reason: </span>{transfer.reason}</p>
                        {transfer.cancellation_reason && <p><span className="text-muted-foreground">Cancellation: </span>{transfer.cancellation_reason}</p>}
                        <div className="flex flex-wrap gap-2">
                          {canManageTransfers && transfer.status === 'draft' && (
                            <Button type="button" disabled={transferActionMutation.isPending} onClick={() => transferActionMutation.mutate({ action: 'dispatch' })}>Dispatch</Button>
                          )}
                          {canManageTransfers && transfer.status === 'dispatched' && (
                            <Button type="button" disabled={transferActionMutation.isPending} onClick={() => transferActionMutation.mutate({ action: 'in-transit' })}>Mark in transit</Button>
                          )}
                          {canManageTransfers && ['dispatched', 'in_transit'].includes(transfer.status) && (
                            <Button type="button" disabled={transferActionMutation.isPending} onClick={() => transferActionMutation.mutate({ action: 'receive' })}>Receive</Button>
                          )}
                        </div>
                        {canManageTransfers && ['draft', 'dispatched', 'in_transit'].includes(transfer.status) && (
                          <div className="space-y-2 border-t pt-3">
                            <Label htmlFor="transfer-cancel-reason">Cancellation reason</Label>
                            <Input
                              id="transfer-cancel-reason"
                              value={cancelReason}
                              maxLength={500}
                              onChange={(event) => setCancelReason(event.target.value)}
                            />
                            <Button
                              type="button"
                              variant="destructive"
                              disabled={transferActionMutation.isPending || cancelReason.trim() === ''}
                              onClick={() => transferActionMutation.mutate({ action: 'cancel', payload: { reason: cancelReason } })}
                            >
                              Cancel transfer
                            </Button>
                          </div>
                        )}
                      </div>
                    )}
                  </CardContent>
                </Card>
              )}
            </div>
          </div>
        )}

        {tab === 'stocktakes' && !canViewStocktakes && (
          <PermissionDeniedState description="You do not have permission to view stocktakes for this branch." />
        )}

        {tab === 'stocktakes' && canViewStocktakes && (
          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>Stocktakes</CardTitle></CardHeader>
              <CardContent>
                {stocktakesQuery.isLoading && <LoadingState text="Loading stocktakes..." />}
                {stocktakesQuery.isError && (
                  <ErrorState description={apiErrorMessage(stocktakesQuery.error)} retry={() => void stocktakesQuery.refetch()} />
                )}
                {stocktakesQuery.data?.length === 0 && <EmptyState title="No stocktakes yet" description="Create the first counted draft." />}
                {stocktakesQuery.data && stocktakesQuery.data.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b text-left">
                          <th className="py-2" scope="col">Number</th>
                          <th className="py-2" scope="col">Lines</th>
                          <th className="py-2" scope="col">Status</th>
                          <th className="py-2" scope="col"><span className="sr-only">Actions</span></th>
                        </tr>
                      </thead>
                      <tbody>
                        {stocktakesQuery.data.map((item) => (
                          <tr key={item.id} className="border-b">
                            <td className="py-2 font-medium">{item.stocktake_number}</td>
                            <td className="py-2">{item.line_count}</td>
                            <td className="py-2">{item.status}</td>
                            <td className="py-2 text-right">
                              <Button type="button" variant="ghost" onClick={() => setSelectedStocktakeId(item.id)}>View</Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>

            <div className="space-y-6">
              {canManageStocktakes && (
                <Card>
                  <CardHeader><CardTitle>Create stocktake draft</CardTitle></CardHeader>
                  <CardContent>
                    <form className="space-y-4" onSubmit={submitStocktake}>
                      <div className="space-y-1">
                        <Label htmlFor="stocktake-reason">Reason</Label>
                        <Input
                          id="stocktake-reason"
                          value={stocktakeForm.reason}
                          required
                          maxLength={500}
                          onChange={(event) => setStocktakeForm({ ...stocktakeForm, reason: event.target.value })}
                        />
                      </div>
                      {stocktakeForm.lines.map((line, index) => (
                        <div key={index} className="space-y-2 rounded-md border p-3">
                          <div className="space-y-1">
                            <Label htmlFor={`stocktake-product-${index}`}>Product</Label>
                            <select
                              id={`stocktake-product-${index}`}
                              className={selectClass}
                              value={line.product_id}
                              required
                              onChange={(event) => updateStocktakeLine(index, { product_id: event.target.value })}
                            >
                              <option value="">Select a product</option>
                              {sourceProducts.map((product: Product) => (
                                <option key={product.id} value={product.id}>{product.name} ({product.sku})</option>
                              ))}
                            </select>
                          </div>
                          <div className="space-y-1">
                            <Label htmlFor={`stocktake-count-${index}`}>Counted quantity</Label>
                            <Input
                              id={`stocktake-count-${index}`}
                              value={line.counted_quantity}
                              inputMode="decimal"
                              required
                              onChange={(event) => updateStocktakeLine(index, { counted_quantity: event.target.value })}
                            />
                          </div>
                          <div className="space-y-1">
                            <Label htmlFor={`stocktake-line-reason-${index}`}>Variance reason</Label>
                            <Input
                              id={`stocktake-line-reason-${index}`}
                              value={line.reason}
                              maxLength={500}
                              onChange={(event) => updateStocktakeLine(index, { reason: event.target.value })}
                            />
                          </div>
                          {stocktakeForm.lines.length > 1 && (
                            <Button
                              type="button"
                              variant="ghost"
                              onClick={() => setStocktakeForm((current) => ({
                                ...current,
                                lines: current.lines.filter((_, lineIndex) => lineIndex !== index),
                              }))}
                            >
                              Remove line
                            </Button>
                          )}
                        </div>
                      ))}
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => setStocktakeForm((current) => ({
                          ...current,
                          lines: [...current.lines, { product_id: '', counted_quantity: '', reason: '' }],
                        }))}
                      >
                        Add line
                      </Button>
                      <div>
                        <Button type="submit" disabled={createStocktakeMutation.isPending}>Create stocktake</Button>
                      </div>
                    </form>
                  </CardContent>
                </Card>
              )}

              {selectedStocktakeId && (
                <Card>
                  <CardHeader><CardTitle>Stocktake detail</CardTitle></CardHeader>
                  <CardContent>
                    {stocktakeQuery.isLoading && <LoadingState text="Loading stocktake..." />}
                    {stocktakeQuery.isError && <ErrorState description={apiErrorMessage(stocktakeQuery.error)} />}
                    {stocktake && (
                      <div className="space-y-4 text-sm">
                        <dl className="grid grid-cols-2 gap-2">
                          <dt className="text-muted-foreground">Number</dt><dd className="font-medium">{stocktake.stocktake_number}</dd>
                          <dt className="text-muted-foreground">Status</dt><dd>{stocktake.status}</dd>
                          <dt className="text-muted-foreground">Reason</dt><dd>{stocktake.reason}</dd>
                          <dt className="text-muted-foreground">Reviewed</dt><dd>{formatDate(stocktake.reviewed_at)}</dd>
                          <dt className="text-muted-foreground">Approved</dt><dd>{formatDate(stocktake.approved_at)}</dd>
                        </dl>
                        <div className="overflow-x-auto">
                          <table className="w-full text-sm">
                            <thead>
                              <tr className="border-b text-left">
                                <th className="py-2" scope="col">Product</th>
                                <th className="py-2" scope="col">Expected</th>
                                <th className="py-2" scope="col">Counted</th>
                                <th className="py-2" scope="col">Variance</th>
                                <th className="py-2" scope="col">Reason</th>
                              </tr>
                            </thead>
                            <tbody>
                              {stocktake.lines.map((line) => (
                                <tr key={line.id} className="border-b">
                                  <td className="py-2">{line.product?.name ?? 'Unknown'}</td>
                                  <td className="py-2">{line.expected_quantity}</td>
                                  <td className="py-2">{line.counted_quantity}</td>
                                  <td className="py-2">{line.variance ?? '—'}</td>
                                  <td className="py-2">{line.reason || '—'}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                        <div className="flex flex-wrap gap-2">
                          {canManageStocktakes && stocktake.status === 'draft' && (
                            <Button type="button" disabled={stocktakeActionMutation.isPending} onClick={() => stocktakeActionMutation.mutate({ action: 'review' })}>Review counts</Button>
                          )}
                        </div>
                        {canApproveStocktakes && stocktake.status === 'reviewed' && (
                          <div className="space-y-2 border-t pt-3">
                            <Label htmlFor="stocktake-approval-reference">Approval reference</Label>
                            <Input
                              id="stocktake-approval-reference"
                              value={approvalReference}
                              maxLength={120}
                              onChange={(event) => setApprovalReference(event.target.value)}
                            />
                            <Button
                              type="button"
                              disabled={stocktakeActionMutation.isPending}
                              onClick={() => stocktakeActionMutation.mutate({
                                action: 'approve',
                                payload: { approval_reference: approvalReference.trim() || null },
                              })}
                            >
                              Approve adjustments
                            </Button>
                          </div>
                        )}
                      </div>
                    )}
                  </CardContent>
                </Card>
              )}
            </div>
          </div>
        )}
      </div>
    </AppShell>
  )
}
