import { Link, useNavigate } from "react-router-dom"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import { PublicShell } from "@/components/layout/PublicShell"
import { Reveal } from "@/components/ui/Reveal"
import { Button } from "@/components/ui/button"
import { useStorefrontAccount } from "@/hooks/useStorefrontAccount"
import { usePageMeta } from "@/hooks/usePageMeta"
import { getPublicSite, logoutStorefrontAccount } from "@/lib/publicApi"

function formatDate(value: string | null): string {
  if (!value) {
    return "-"
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

export default function AccountPage() {
  const accountQuery = useStorefrontAccount()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const text = (key: string, fallback: string) => siteQuery.data?.content.text[key] || fallback

  usePageMeta(
    text('account_meta_title', 'My Account | Taziva Motor Spares'),
    text('account_meta_description', 'View your Taziva Motor Spares orders and reservations.'),
  )

  const handleLogout = async () => {
    try {
      await logoutStorefrontAccount()
    } finally {
      queryClient.clear()
      navigate("/", { replace: true })
    }
  }

  return (
    <PublicShell>
      <div className="mx-auto w-full max-w-6xl px-5 py-10 sm:px-6 lg:px-8 lg:py-14">
        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Customer account</p>
        <h1 className="mt-2 text-3xl font-black tracking-tight text-brand-ink sm:text-4xl">{text('account_heading', 'My account')}</h1>

        {accountQuery.isPending && <p className="mt-8 text-sm text-brand-ink/70">Loading your account...</p>}

        {accountQuery.isError && (
          <div className="mt-8 rounded-2xl border border-brand-ink/10 bg-white p-10 text-center shadow-sm">
            <p className="text-lg font-bold text-brand-ink">Sign in to view your account.</p>
            <p className="mt-2 text-sm text-brand-ink/80">Create an account to order parts online and track your reservations.</p>
            <div className="mt-6 flex flex-wrap justify-center gap-3">
              <Link
                to="/login?redirect=/account"
                className="inline-flex min-h-11 items-center rounded-lg bg-brand-green-deep px-5 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
              >
                Sign in
              </Link>
              <Link
                to="/signup"
                className="inline-flex min-h-11 items-center rounded-lg border border-brand-ink/10 px-5 text-sm font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
              >
                Create account
              </Link>
            </div>
          </div>
        )}

        {accountQuery.data && (
          <div className="mt-8 space-y-10">
            <Reveal className="flex flex-col gap-4 rounded-2xl border border-brand-ink/10 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-xl font-black text-brand-ink">{accountQuery.data.user.name}</p>
                <p className="mt-1 text-sm text-brand-ink/70">{accountQuery.data.user.email}</p>
              </div>
              <div className="flex flex-wrap gap-3">
                <Link
                  to="/shop"
                  className="inline-flex min-h-11 items-center rounded-lg bg-brand-green-deep px-5 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
                >
                  Shop parts
                </Link>
                <Button type="button" variant="outline" className="min-h-11" onClick={() => void handleLogout()}>
                  Sign out
                </Button>
              </div>
            </Reveal>

            <Reveal>
              <div className="flex items-center justify-between gap-4">
                <h2 className="text-2xl font-black text-brand-ink">Recent orders</h2>
                <Link to="/shop" className="text-sm font-bold text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
                  Order more parts
                </Link>
              </div>
              {accountQuery.data.orders.length === 0 ? (
                <p className="mt-4 rounded-xl border border-brand-ink/10 bg-white p-6 text-sm text-brand-ink/70">You have not placed any orders yet.</p>
              ) : (
                <div className="mt-4 space-y-3">
                  {accountQuery.data.orders.map((order) => (
                    <article key={order.id} className="rounded-xl border border-brand-ink/10 bg-white p-5 shadow-sm">
                      <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                          <p className="font-bold text-brand-ink">Order {order.sale_number}</p>
                          <p className="mt-1 text-xs text-brand-ink/60">{formatDate(order.created_at)}</p>
                        </div>
                        <div className="text-right">
                          <p className="text-lg font-extrabold text-brand-ink">{order.total}</p>
                          <p className="text-xs font-semibold uppercase tracking-wider text-brand-green-deep">{order.payment_status}</p>
                        </div>
                      </div>
                      <ul className="mt-4 space-y-1 border-t border-brand-ink/10 pt-3 text-sm text-brand-ink/80">
                        {order.items.map((item, index) => (
                          <li key={`${item.name}-${index}`} className="flex justify-between gap-4">
                            <span>{item.quantity} x {item.name}</span>
                            <span>{item.total}</span>
                          </li>
                        ))}
                      </ul>
                    </article>
                  ))}
                </div>
              )}
            </Reveal>

            <Reveal>
              <h2 className="text-2xl font-black text-brand-ink">Reservations</h2>
              {accountQuery.data.reservations.length === 0 ? (
                <p className="mt-4 rounded-xl border border-brand-ink/10 bg-white p-6 text-sm text-brand-ink/70">You have no online reservations yet.</p>
              ) : (
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                  {accountQuery.data.reservations.map((reservation) => (
                    <article key={reservation.id} className="rounded-xl border border-brand-ink/10 bg-white p-5 shadow-sm">
                      <p className="font-bold text-brand-ink">{reservation.product_name}</p>
                      <p className="mt-1 text-xs text-brand-ink/60">Reservation {reservation.number}</p>
                      <div className="mt-3 flex items-center justify-between text-sm">
                        <span className="text-brand-ink/80">{reservation.quantity} held</span>
                        <span className="font-bold text-brand-green-deep">{reservation.status}</span>
                      </div>
                    </article>
                  ))}
                </div>
              )}
            </Reveal>
          </div>
        )}
      </div>
    </PublicShell>
  )
}
