import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'

type Reservation = {
  id: string
  reservation_number: string
  product_name: string
  quantity: string
  amount: string
  currency_code: string
  customer_name: string
  customer_email: string | null
  customer_phone: string | null
  status: string
  payment_status: string | null
  payment_provider: string | null
  payment_reference: string | null
  expires_at: string
  created_at: string
}

export default function ReservationsPage() {
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.reservations.view') ?? false
  const [status, setStatus] = useState('all')

  const reservationsQuery = useQuery({
    queryKey: ['reservations', branchId, status],
    queryFn: async () => {
      const params = status === 'all' ? {} : { status }
      const response = await api.get<{ data: Reservation[] }>(`/api/branches/${branchId}/reservations`, { params })
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  if (!meQuery.data) return <AppShell><LoadingState text="Loading branch access…" /></AppShell>
  if (!branchId || !canView) return <AppShell><PermissionDeniedState title="Reservation access required" description="Ask a branch administrator to grant online reservation access." /></AppShell>

  const reservations = reservationsQuery.data ?? []

  return (
    <AppShell>
      <div className="space-y-6">
        <header className="flex flex-wrap items-end justify-between gap-4">
          <div><p className="text-sm font-semibold uppercase tracking-[.15em] text-primary">Storefront</p><h1 className="mt-2 text-3xl font-bold tracking-tight">Online reservations</h1><p className="mt-2 text-muted-foreground">Review customer requests and their temporary stock holds.</p></div>
          <div className="w-full max-w-52 space-y-2"><Label htmlFor="reservation-status">Show status</Label><select id="reservation-status" value={status} onChange={(event) => setStatus(event.target.value)} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="all">All reservations</option><option value="awaiting_payment">Awaiting payment</option><option value="confirmed">Confirmed</option><option value="ready">Ready</option><option value="picked_up">Picked up</option><option value="expired">Expired</option><option value="cancelled">Cancelled</option><option value="refunded">Refunded</option></select></div>
        </header>
        {reservationsQuery.isLoading && <LoadingState text="Loading reservations…" />}
        {reservationsQuery.isError && <ErrorState title="Reservations could not be loaded" description={apiErrorMessage(reservationsQuery.error)} retry={() => void reservationsQuery.refetch()} />}
        {!reservationsQuery.isLoading && !reservationsQuery.isError && reservations.length === 0 && <EmptyState title="No reservations yet" description="Public customer reservations will appear here." />}
        {!reservationsQuery.isLoading && !reservationsQuery.isError && reservations.length > 0 && <Card><CardHeader><CardTitle className="text-base">{reservations.length} recent reservations</CardTitle></CardHeader><CardContent><div className="overflow-x-auto"><table className="w-full min-w-[860px] text-left text-sm"><thead className="border-b text-xs uppercase tracking-wide text-muted-foreground"><tr><th className="py-3 pr-4" scope="col">Reservation</th><th className="py-3 pr-4" scope="col">Customer</th><th className="py-3 pr-4" scope="col">Item</th><th className="py-3 pr-4" scope="col">Amount</th><th className="py-3 pr-4" scope="col">Reservation status</th><th className="py-3 pr-4" scope="col">Paynow</th><th className="py-3" scope="col">Hold expires</th></tr></thead><tbody>{reservations.map((reservation) => <tr key={reservation.id} className="border-b last:border-0"><td className="py-4 pr-4"><div className="font-semibold">{reservation.reservation_number}</div><div className="mt-1 text-xs text-muted-foreground">{new Date(reservation.created_at).toLocaleString()}</div></td><td className="py-4 pr-4"><div className="font-medium">{reservation.customer_name}</div>{reservation.customer_phone && <a className="mt-1 block text-xs text-primary underline-offset-2 hover:underline" href={`tel:${reservation.customer_phone}`}>{reservation.customer_phone}</a>}{reservation.customer_email && <a className="mt-1 block text-xs text-primary underline-offset-2 hover:underline" href={`mailto:${reservation.customer_email}`}>{reservation.customer_email}</a>}</td><td className="py-4 pr-4"><div>{reservation.product_name}</div><div className="text-xs text-muted-foreground">Quantity {reservation.quantity}</div></td><td className="py-4 pr-4 font-semibold">{reservation.amount} {reservation.currency_code}</td><td className="py-4 pr-4"><span className="rounded-full bg-secondary px-2.5 py-1 text-xs capitalize">{reservation.status.replace(/_/g, ' ')}</span></td><td className="py-4 pr-4"><span className="text-xs capitalize">{reservation.payment_status?.replace(/_/g, ' ') ?? 'not started'}</span>{reservation.payment_reference && <div className="mt-1 text-xs text-muted-foreground">Ref {reservation.payment_reference}</div>}</td><td className="py-4 text-xs text-muted-foreground">{new Date(reservation.expires_at).toLocaleString()}</td></tr>)}</tbody></table></div></CardContent></Card>}
      </div>
    </AppShell>
  )
}
