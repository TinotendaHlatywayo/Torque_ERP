import { useEffect, useRef } from 'react'
import { useParams } from 'react-router-dom'
import QRCode from 'qrcode'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { SaleDetail } from '@/lib/sales'

export default function ReceiptPage() {
  const { saleId } = useParams()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canvasRef = useRef<HTMLCanvasElement | null>(null)

  const saleQuery = useQuery({
    queryKey: ['sale', branchId, saleId],
    queryFn: async () => {
      const response = await api.get<{ data: SaleDetail }>(`/api/branches/${branchId}/sales/${saleId}`)
      return response.data.data
    },
    enabled: Boolean(branchId && saleId),
  })

  useEffect(() => {
    const url = saleQuery.data ? `${window.location.origin}/api/verify/receipt/${saleQuery.data.id}` : ''
    if (canvasRef.current && url) {
      void QRCode.toCanvas(canvasRef.current, url, { width: 128 })
    }
  }, [saleQuery.data])

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

  if (saleQuery.isLoading) {
    return (
      <AppShell>
        <LoadingState text="Loading receipt..." />
      </AppShell>
    )
  }

  if (saleQuery.isError || !saleQuery.data) {
    return (
      <AppShell>
        <ErrorState
          title="Receipt could not be loaded"
          description={apiErrorMessage(saleQuery.error)}
          retry={() => void saleQuery.refetch()}
        />
      </AppShell>
    )
  }

  const sale = saleQuery.data
  const verificationUrl = sale ? `${window.location.origin}/api/verify/receipt/${sale.id}` : ''

  return (
    <AppShell>
      <div className="space-y-6 print:space-y-2">
        <div className="flex items-center justify-between">
          <h1 className="text-3xl font-bold tracking-tight">Receipt</h1>
          <Button onClick={() => window.print()}>Print</Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{sale.receipt?.receipt_number ?? sale.sale_number}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            <div className="grid gap-2 md:grid-cols-2">
              <div>Sale number: {sale.sale_number}</div>
              <div>Status: {sale.status} / {sale.payment_status}</div>
              <div>Currency: {sale.currency_code}</div>
              <div>Customer: {sale.customer?.name ?? 'Walk-in'}</div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b text-muted-foreground">
                  <tr>
                    <th className="py-2 pr-4 font-medium">Item</th>
                    <th className="py-2 pr-4 font-medium">Qty</th>
                    <th className="py-2 pr-4 font-medium">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {sale.items.map((item) => (
                    <tr key={item.id} className="border-b last:border-0">
                      <td className="py-2 pr-4">
                        {item.product_name}
                        <div className="text-xs text-muted-foreground">{item.sku} · {item.unit_code}</div>
                      </td>
                      <td className="py-2 pr-4">{item.quantity}</td>
                      <td className="py-2 pr-4">{item.total_minor}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="space-y-1">
              <div className="flex justify-between"><span>Subtotal</span><span>{sale.subtotal_minor}</span></div>
              <div className="flex justify-between"><span>Discount</span><span>{sale.discount_minor}</span></div>
              <div className="flex justify-between"><span>Tax</span><span>{sale.tax_minor}</span></div>
              <div className="flex justify-between font-semibold"><span>Total</span><span>{sale.total_minor}</span></div>
              <div className="flex justify-between"><span>Paid</span><span>{sale.paid_minor}</span></div>
            </div>

            <div>
              <h2 className="font-medium">Payments</h2>
              <ul className="mt-2 space-y-1">
                {sale.payments.map((payment) => (
                  <li key={payment.id}>
                    {payment.method}: {payment.amount_minor} {payment.currency_code}
                    {payment.reference ? ` (${payment.reference})` : ''}
                  </li>
                ))}
              </ul>
            </div>

            {verificationUrl && (
              <div className="space-y-2">
                <h2 className="font-medium">Verification</h2>
                <canvas ref={canvasRef} aria-label="Receipt verification QR code" />
                <p className="break-all text-xs text-muted-foreground">{verificationUrl}</p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  )
}
