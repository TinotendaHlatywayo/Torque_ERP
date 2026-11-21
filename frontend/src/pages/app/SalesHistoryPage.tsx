import { useState } from 'react'
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
import type { SaleDetail, SaleReturn, SaleSummary } from '@/lib/sales'

export default function SalesHistoryPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.sales.view') ?? false
  const canVoid = branch?.permissions.includes('branch.sales.void') ?? false

  const [selectedSaleId, setSelectedSaleId] = useState<string | null>(null)
  const [returnReason, setReturnReason] = useState('')
  const [returnQuantity, setReturnQuantity] = useState('')
  const [feedback, setFeedback] = useState<string | null>(null)

  const salesQuery = useQuery({
    queryKey: ['sales', branchId],
    queryFn: async () => {
      const response = await api.get<{ data: SaleSummary[] }>(`/api/branches/${branchId}/sales`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const saleDetailQuery = useQuery({
    queryKey: ['sale', branchId, selectedSaleId],
    queryFn: async () => {
      const response = await api.get<{ data: SaleDetail }>(`/api/branches/${branchId}/sales/${selectedSaleId}`)
      return response.data.data
    },
    enabled: Boolean(branchId && selectedSaleId),
  })

  const returnsQuery = useQuery({
    queryKey: ['returns', branchId],
    queryFn: async () => {
      const response = await api.get<{ data: SaleReturn[] }>(`/api/branches/${branchId}/returns`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const voidMutation = useMutation({
    mutationFn: async ({ saleId, reason }: { saleId: string; reason: string }) => {
      await api.post(
        `/api/branches/${branchId}/sales/${saleId}/void`,
        { reason },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
    },
    onSuccess: async () => {
      setFeedback('Sale voided.')
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['sales', branchId] }),
        queryClient.invalidateQueries({ queryKey: ['sale', branchId, selectedSaleId] }),
      ])
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const returnMutation = useMutation({
    mutationFn: async () => {
      if (!selectedSaleId || !saleDetailQuery.data) throw new Error('Select a sale first.')
      const firstItem = saleDetailQuery.data.items[0]
      await api.post(
        `/api/branches/${branchId}/sales/${selectedSaleId}/returns`,
        {
          reason: returnReason,
          items: [{ sale_item_id: firstItem.id, quantity: Number(returnQuantity || 0) }],
        },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
    },
    onSuccess: async () => {
      setFeedback('Return requested.')
      setReturnReason('')
      setReturnQuantity('')
      await queryClient.invalidateQueries({ queryKey: ['returns', branchId] })
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const handleVoid = (saleId: string) => {
    const reason = window.prompt('Reason for voiding this sale')
    if (reason) voidMutation.mutate({ saleId, reason })
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
        <PermissionDeniedState description="Your role does not include permission to view sales." />
      </AppShell>
    )
  }

  const sales = salesQuery.data ?? []

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Sales history</h1>
          <p className="text-muted-foreground">
            {branch.name} ({branch.code}) — posted and voided sales
          </p>
        </div>

        {feedback && (
          <div role="status" className="rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-700">
            {feedback}
          </div>
        )}

        <div className="grid gap-6 lg:grid-cols-[1fr_1fr]">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Sales</CardTitle>
            </CardHeader>
            <CardContent>
              {salesQuery.isLoading && <LoadingState text="Loading sales..." />}
              {salesQuery.isError && (
                <ErrorState
                  title="Sales could not be loaded"
                  description={apiErrorMessage(salesQuery.error)}
                  retry={() => void salesQuery.refetch()}
                />
              )}
              {sales.length === 0 && !salesQuery.isLoading && (
                <EmptyState title="No sales found" description="Completed checkouts will appear here." />
              )}
              <div className="space-y-2">
                {sales.map((sale) => (
                  <button
                    key={sale.id}
                    type="button"
                    className="w-full rounded-md border p-3 text-left hover:bg-accent"
                    onClick={() => setSelectedSaleId(sale.id)}
                  >
                    <div className="flex items-center justify-between">
                      <span className="font-medium">{sale.sale_number}</span>
                      <span className="text-xs text-muted-foreground">{sale.status}</span>
                    </div>
                    <div className="text-sm text-muted-foreground">
                      {sale.total_minor} {sale.currency_code} · {sale.payment_status}
                    </div>
                  </button>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Sale detail</CardTitle>
            </CardHeader>
            <CardContent>
              {!selectedSaleId && <EmptyState title="Select a sale" description="Choose a sale to view details." />}
              {selectedSaleId && saleDetailQuery.isLoading && <LoadingState text="Loading sale..." />}
              {selectedSaleId && saleDetailQuery.data && (
                <div className="space-y-4 text-sm">
                  <div>
                    <div className="font-medium">{saleDetailQuery.data.sale_number}</div>
                    <div className="text-muted-foreground">
                      {saleDetailQuery.data.status} · {saleDetailQuery.data.payment_status}
                    </div>
                  </div>
                  <div className="space-y-1">
                    {saleDetailQuery.data.items.map((item) => (
                      <div key={item.id} className="flex justify-between">
                        <span>{item.product_name} × {item.quantity}</span>
                        <span>{item.total_minor}</span>
                      </div>
                    ))}
                  </div>
                  <div className="flex justify-between font-semibold">
                    <span>Total</span>
                    <span>{saleDetailQuery.data.total_minor}</span>
                  </div>

                  {canVoid && saleDetailQuery.data.status === 'posted' && (
                    <Button variant="destructive" onClick={() => handleVoid(selectedSaleId)}>
                      Void sale
                    </Button>
                  )}

                  {canVoid && saleDetailQuery.data.status === 'posted' && (
                    <div className="space-y-2 border-t pt-4">
                      <div className="font-medium">Request return</div>
                      <div className="space-y-2">
                        <Label htmlFor="return-quantity">Quantity (first item)</Label>
                        <Input
                          id="return-quantity"
                          value={returnQuantity}
                          onChange={(event) => setReturnQuantity(event.target.value)}
                        />
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="return-reason">Reason</Label>
                        <Input
                          id="return-reason"
                          value={returnReason}
                          onChange={(event) => setReturnReason(event.target.value)}
                        />
                      </div>
                      <Button onClick={() => returnMutation.mutate()} disabled={returnMutation.isPending}>
                        {returnMutation.isPending ? 'Requesting...' : 'Request return'}
                      </Button>
                    </div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Return requests</CardTitle>
          </CardHeader>
          <CardContent>
            {returnsQuery.isLoading && <LoadingState text="Loading returns..." />}
            {returnsQuery.data && returnsQuery.data.length === 0 && (
              <EmptyState title="No returns requested" description="Return requests will appear here." />
            )}
            <div className="space-y-2">
              {returnsQuery.data?.map((saleReturn) => (
                <div key={saleReturn.id} className="rounded-md border p-3 text-sm">
                  <div className="font-medium">{saleReturn.return_number}</div>
                  <div className="text-muted-foreground">
                    {saleReturn.status} · {saleReturn.restock_decision} · {saleReturn.refund_status} · {saleReturn.total_minor}
                  </div>
                  <div className="text-muted-foreground">{saleReturn.reason}</div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AppShell>
  )
}
