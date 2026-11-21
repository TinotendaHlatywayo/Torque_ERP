import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { Link, useParams } from "react-router-dom"
import { ErrorState, LoadingState } from "@/components/feedback/StateComponents"
import { usePageMeta } from "@/hooks/usePageMeta"
import { PublicShell } from "@/components/layout/PublicShell"
import { Button } from "@/components/ui/button"
import { apiErrorMessage } from "@/lib/apiErrors"
import { cancelReservation, getReservationStatus } from "@/lib/publicApi"

function formatDate(value: string | null) {
  if (!value) {
    return "Not available"
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? "Not available" : date.toLocaleString()
}

function formatStatus(value: string) {
  return value.replace(/_/g, " ")
}

export default function ReservationStatusPage() {
  const { token = "" } = useParams()
  const queryClient = useQueryClient()
  usePageMeta(
    "Reservation Status | Taziva Motor Spares",
    "Check the latest status of your Taziva Motor Spares stock reservation.",
  )

  const statusQuery = useQuery({
    queryKey: ["public", "reservation", "status", token],
    queryFn: () => getReservationStatus(token),
    enabled: Boolean(token),
    refetchInterval: (query) => query.state.data?.reservation.status === "awaiting_payment" ? 10_000 : false,
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelReservation(token),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["public", "reservation", "status", token] })
    },
  })

  const reservation = statusQuery.data?.reservation

  return (
    <PublicShell>
      <div className="mx-auto w-full max-w-3xl px-5 py-10 sm:px-6 lg:px-8 lg:py-14">
        <header className="mt-2">
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Reservation status</p>
          <h1 className="mt-2 text-3xl font-black tracking-tight text-brand-ink sm:text-4xl">Your reservation</h1>
          <p className="mt-2 text-sm leading-relaxed text-brand-ink/80 sm:text-base">
            Keep this page open to see payment updates. No customer details are shown here.
          </p>
        </header>

        {statusQuery.isPending && <LoadingState text="Loading your reservation..." />}

        {statusQuery.isError && (
          <div className="mt-8">
            <ErrorState
              title="Reservation not found"
              description={apiErrorMessage(statusQuery.error, "Check your reservation link and try again.")}
              retry={() => void statusQuery.refetch()}
            />
          </div>
        )}

        {reservation && (
          <>
            <section className="mt-8 rounded-xl border border-brand-ink/10 bg-white p-5 shadow-sm sm:p-8" aria-live="polite">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-ink/70">Reservation number</p>
                  <p className="mt-1 text-xl font-extrabold text-brand-ink">{reservation.number}</p>
                </div>
                <span className="rounded-full bg-brand-green/10 px-4 py-2 text-sm font-bold capitalize text-brand-green-deep">
                  {formatStatus(reservation.status)}
                </span>
              </div>

              <dl className="mt-7 grid gap-5 border-t border-brand-ink/10 pt-6 sm:grid-cols-2">
                <div>
                  <dt className="text-xs font-bold uppercase tracking-[0.14em] text-brand-ink/70">Product</dt>
                  <dd className="mt-1 text-sm font-semibold text-brand-ink">{reservation.product_name}</dd>
                </div>
                <div>
                  <dt className="text-xs font-bold uppercase tracking-[0.14em] text-brand-ink/70">Quantity</dt>
                  <dd className="mt-1 text-sm font-semibold text-brand-ink">{reservation.quantity}</dd>
                </div>
                <div>
                  <dt className="text-xs font-bold uppercase tracking-[0.14em] text-brand-ink/70">Amount</dt>
                  <dd className="mt-1 text-lg font-extrabold text-brand-ink">{reservation.amount} {reservation.currency_code}</dd>
                </div>
                <div>
                  <dt className="text-xs font-bold uppercase tracking-[0.14em] text-brand-ink/70">Payment status</dt>
                  <dd className="mt-1 text-sm font-semibold capitalize text-brand-ink">
                    {reservation.payment_status ? formatStatus(reservation.payment_status) : "Not started"}
                  </dd>
                </div>
                <div className="sm:col-span-2">
                  <dt className="text-xs font-bold uppercase tracking-[0.14em] text-brand-ink/70">Hold expires</dt>
                  <dd className="mt-1 text-sm font-semibold text-brand-ink">{formatDate(reservation.expires_at)}</dd>
                </div>
              </dl>

              {reservation.status === "awaiting_payment" && (
                <p role="status" className="mt-6 rounded-lg bg-brand-surface p-4 text-sm text-brand-ink/80">
                  We are checking for payment updates every 10 seconds while this reservation awaits payment.
                </p>
              )}

              {cancelMutation.isSuccess && cancelMutation.data && (
                <p role="status" className="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800">
                  {cancelMutation.data.notice}
                </p>
              )}

              {cancelMutation.isError && (
                <p role="alert" className="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">
                  {apiErrorMessage(cancelMutation.error, "The reservation could not be cancelled.")}
                </p>
              )}

              {reservation.can_cancel && (
                <Button
                  type="button"
                  variant="outline"
                  disabled={cancelMutation.isPending}
                  onClick={() => cancelMutation.mutate()}
                  className="mt-6 min-h-11 border-brand-green/30 text-brand-green-deep hover:bg-brand-green/10 focus-visible:ring-brand-green"
                >
                  {cancelMutation.isPending ? "Cancelling..." : "Cancel reservation"}
                </Button>
              )}
            </section>

            <div className="mt-8 flex flex-wrap gap-4">
              <Link to="/shop" className="inline-flex min-h-11 items-center rounded-lg bg-brand-ink px-5 text-sm font-bold text-white hover:bg-brand-ink/85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                Continue shopping
              </Link>
              <Link to="/" className="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-bold text-brand-ink hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                Back to home
              </Link>
            </div>
          </>
        )}
      </div>
    </PublicShell>
  )
}
