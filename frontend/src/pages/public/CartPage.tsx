import { useState } from "react"
import { Link } from "react-router-dom"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import { PublicShell } from "@/components/layout/PublicShell"
import { ProductPhoto } from "@/components/public/ProductPhoto"
import { ShieldIcon } from "@/components/public/icons"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useCart } from "@/hooks/useCart"
import { useStorefrontAccount, storefrontAccountQueryKey } from "@/hooks/useStorefrontAccount"
import { usePageMeta } from "@/hooks/usePageMeta"
import { apiErrorMessage } from "@/lib/apiErrors"
import { formatStorefrontMoney } from "@/lib/storefront"
import { getPublicSite, placeStorefrontOrder, validationFieldErrors, type StorefrontOrder } from "@/lib/publicApi"

function newIdempotencyKey(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID()
  }

  return `cart-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export default function CartPage() {
  const cart = useCart()
  const accountQuery = useStorefrontAccount()
  const queryClient = useQueryClient()
  const [phone, setPhone] = useState("")
  const [address, setAddress] = useState("")
  const [notes, setNotes] = useState("")
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)
  const [orderKey, setOrderKey] = useState(newIdempotencyKey)
  const [createdOrder, setCreatedOrder] = useState<StorefrontOrder | null>(null)
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const text = (key: string, fallback: string) => siteQuery.data?.content.text[key] || fallback

  usePageMeta(
    text('cart_meta_title', 'Your Cart | Taziva Motor Spares'),
    text('cart_meta_description', 'Review your parts and place an order with Taziva Motor Spares.'),
  )

  const currency = cart.lines[0]?.currencyCode ?? "USD"

  const handleCheckout = async (event: React.FormEvent) => {
    event.preventDefault()
    setError("")
    setLoading(true)

    try {
      const response = await placeStorefrontOrder({
        lines: cart.lines.map((line) => ({ product_slug: line.productSlug, quantity: String(line.quantity) })),
        phone: phone.trim() || undefined,
        address: address.trim() || undefined,
        notes: notes.trim() || undefined,
      }, orderKey)

      setCreatedOrder(response.order)
      cart.clearCart()
      setOrderKey(newIdempotencyKey())
      await queryClient.invalidateQueries({ queryKey: storefrontAccountQueryKey })
    } catch (err: unknown) {
      const errors = validationFieldErrors(err)
      setError(errors.lines ?? errors.idempotency_key ?? apiErrorMessage(err, "Unable to place your order. Please try again."))
      setOrderKey(newIdempotencyKey())
    } finally {
      setLoading(false)
    }
  }

  return (
    <PublicShell>
      <div className="mx-auto w-full max-w-6xl px-5 py-10 sm:px-6 lg:px-8 lg:py-14">
        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Online store</p>
        <h1 className="mt-2 text-3xl font-black tracking-tight text-brand-ink sm:text-4xl">{text('cart_heading', 'Shopping cart')}</h1>
        <p className="mt-3 max-w-2xl text-sm text-brand-ink/80">
          Review your parts and place an order in a few seconds. Payments are made on collection at the branch.
        </p>

        {createdOrder && (
          <section className="mt-8 rounded-2xl border border-brand-green/40 bg-white p-6 shadow-sm" aria-live="polite">
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Order placed</p>
            <h2 className="mt-2 text-2xl font-black text-brand-ink">Order {createdOrder.sale_number}</h2>
            <p className="mt-2 text-sm text-brand-ink/80">
              We have reserved your items for collection. Your order total is {createdOrder.total}.
            </p>
            <div className="mt-5 flex flex-wrap gap-3">
              <Link
                to="/account"
                className="inline-flex min-h-11 items-center rounded-lg bg-brand-green-deep px-5 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
              >
                View my orders
              </Link>
              <Link
                to="/shop"
                className="inline-flex min-h-11 items-center rounded-lg border border-brand-ink/10 px-5 text-sm font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
              >
                Continue shopping
              </Link>
            </div>
          </section>
        )}

        {cart.lines.length === 0 && !createdOrder && (
          <div className="mt-8 rounded-2xl border border-brand-ink/10 bg-white p-10 text-center shadow-sm">
            <p className="text-lg font-bold text-brand-ink">Your cart is empty.</p>
            <p className="mt-2 text-sm text-brand-ink/80">Browse the catalogue to add parts to your order.</p>
            <Link
              to="/shop"
              className="mt-6 inline-flex min-h-11 items-center rounded-lg bg-brand-green-deep px-5 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
            >
              Browse parts
            </Link>
          </div>
        )}

        {cart.lines.length > 0 && (
          <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
            <section aria-label="Cart items" className="space-y-4">
              {cart.lines.map((line) => (
                <article key={line.productSlug} className="flex flex-col gap-4 rounded-xl border border-brand-ink/10 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                  <ProductPhoto
                    src={line.imageUrl}
                    alt={line.name}
                    caption="Photo"
                    className="h-24 w-24 shrink-0 rounded-lg border border-brand-ink/10"
                    imageClassName="p-2"
                  />
                  <div className="min-w-0 flex-1">
                    <Link to={`/shop/${line.productSlug}`} className="text-base font-bold text-brand-ink hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
                      {line.name}
                    </Link>
                    <p className="mt-1 text-xs text-brand-ink/60">{line.sku}</p>
                    <p className="mt-2 text-sm font-bold text-brand-green-deep">{formatStorefrontMoney(line.priceValue, line.currencyCode)}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <div className="inline-flex items-center rounded-lg border border-brand-ink/10">
                      <button
                        type="button"
                        onClick={() => cart.updateQuantity(line.productSlug, line.quantity - 1)}
                        className="h-11 w-11 rounded-l-lg text-lg font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                        aria-label={`Decrease quantity of ${line.name}`}
                      >
                        -
                      </button>
                      <span className="w-10 text-center text-sm font-bold" aria-live="polite">{line.quantity}</span>
                      <button
                        type="button"
                        onClick={() => cart.updateQuantity(line.productSlug, line.quantity + 1)}
                        className="h-11 w-11 rounded-r-lg text-lg font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                        aria-label={`Increase quantity of ${line.name}`}
                      >
                        +
                      </button>
                    </div>
                    <button
                      type="button"
                      onClick={() => cart.removeItem(line.productSlug)}
                      className="min-h-11 rounded-md px-3 text-sm font-semibold text-destructive hover:bg-destructive/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                    >
                      Remove
                    </button>
                  </div>
                </article>
              ))}
            </section>

            <aside className="h-fit rounded-2xl border border-brand-ink/10 bg-white p-6 shadow-sm lg:sticky lg:top-36">
              <h2 className="text-lg font-black text-brand-ink">Order summary</h2>
              <div className="mt-4 space-y-2 border-b border-brand-ink/10 pb-4 text-sm text-brand-ink/80">
                <div className="flex items-center justify-between">
                  <span>Subtotal ({cart.itemCount} item(s))</span>
                  <span>{formatStorefrontMoney(cart.totalValue, currency)}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span>Collection</span>
                  <span className="font-bold text-brand-success">Free</span>
                </div>
                <div className="flex items-center justify-between text-base font-black text-brand-ink">
                  <span>Order total</span>
                  <span>{formatStorefrontMoney(cart.totalValue, currency)}</span>
                </div>
              </div>
              <p className="mt-4 flex items-start gap-2 text-xs font-semibold text-brand-green-deep">
                <ShieldIcon className="h-4 w-4 shrink-0" />
                Pay on collection. Prices come from the live TMS catalogue.
              </p>

              {accountQuery.isPending && <p className="mt-6 text-sm text-brand-ink/70">Checking your account...</p>}

              {accountQuery.isError && (
                <div className="mt-6 rounded-lg bg-brand-surface p-4">
                  <p className="text-sm font-bold text-brand-ink">Sign in to place your order.</p>
                  <div className="mt-3 flex flex-col gap-2">
                    <Link
                      to="/login?redirect=/cart"
                      className="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-green-deep px-4 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                    >
                      Sign in
                    </Link>
                    <Link
                      to="/signup"
                      className="inline-flex min-h-11 items-center justify-center rounded-lg border border-brand-ink/10 px-4 text-sm font-bold text-brand-ink hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                    >
                      Create account
                    </Link>
                  </div>
                </div>
              )}

              {accountQuery.data && (
                <form className="mt-6 space-y-4" onSubmit={handleCheckout} noValidate>
                  {error && (
                    <div role="alert" aria-live="assertive" className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                      {error}
                    </div>
                  )}
                  <div className="space-y-2">
                    <Label htmlFor="checkout-phone">Phone number</Label>
                    <Input id="checkout-phone" value={phone} onChange={(event) => setPhone(event.target.value)} placeholder="+263 77 123 4567" autoComplete="tel" />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="checkout-address">Collection notes or address</Label>
                    <Input id="checkout-address" value={address} onChange={(event) => setAddress(event.target.value)} placeholder="Optional" autoComplete="street-address" />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="checkout-notes">Order notes</Label>
                    <Input id="checkout-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Optional" />
                  </div>
                  <Button type="submit" className="min-h-12 w-full bg-brand-green-deep text-white hover:bg-brand-green-deep/90 focus-visible:ring-brand-green" disabled={loading}>
                    {loading ? "Placing order..." : `Place order - ${formatStorefrontMoney(cart.totalValue, currency)}`}
                  </Button>
                  <p className="text-xs text-brand-ink/60">Signed in as {accountQuery.data.user.email}. You will pay when you collect.</p>
                </form>
              )}
            </aside>
          </div>
        )}
      </div>
    </PublicShell>
  )
}
