import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ErrorState, LoadingState } from '@/components/feedback/StateComponents'
import { PublicShell } from '@/components/layout/PublicShell'
import { ProductCard } from '@/components/public/ProductCard'
import { ProductPhoto } from '@/components/public/ProductPhoto'
import { ArrowRightIcon, CheckIcon, ClockIcon, ShieldIcon, TruckIcon } from '@/components/public/icons'
import { useCart } from '@/hooks/useCart'
import { usePageMeta } from '@/hooks/usePageMeta'
import { apiErrorMessage } from '@/lib/apiErrors'
import { getPublicProduct, getPublicSite } from '@/lib/publicApi'

export default function ProductPage() {
  const { slug = '' } = useParams()
  const [quantity, setQuantity] = useState(1)
  const navigate = useNavigate()
  const { addItem } = useCart()
  const productQuery = useQuery({
    queryKey: ['public', 'product', slug],
    queryFn: () => getPublicProduct(slug),
    enabled: Boolean(slug),
  })
  const siteQuery = useQuery({
    queryKey: ['public', 'site'],
    queryFn: getPublicSite,
    staleTime: 1000 * 60 * 5,
  })

  useEffect(() => {
    setQuantity(1)
  }, [slug])

  const data = productQuery.data
  const branchName = siteQuery.data?.branch.name ?? 'Taziva Motor Spares'
  const cartLine = data
    ? {
        productSlug: data.product.slug,
        name: data.product.name,
        sku: data.product.slug,
        priceValue: data.product.price_value,
        currencyCode: data.product.currency_code,
        imageUrl: data.product.image_url,
        quantity,
        maxStock: null,
      }
    : null

  const addToCart = () => {
    if (cartLine) {
      addItem(cartLine)
    }
  }

  const buyNow = () => {
    if (cartLine) {
      addItem(cartLine)
      navigate('/cart')
    }
  }

  usePageMeta(
    data ? `${data.product.name} | Taziva Motor Spares` : 'Motor Spare | Taziva Motor Spares',
    data?.product.short_description ?? 'View motor spares with photos, descriptions and prices from Taziva Motor Spares.',
  )

  return (
    <PublicShell>
      <div className="mx-auto w-full max-w-7xl px-5 py-6 sm:px-6 lg:px-8 lg:py-8">
        <nav aria-label="Breadcrumb" className="text-xs text-brand-ink/70">
          <ol className="flex flex-wrap items-center gap-2">
            <li><Link to="/" className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Home</Link></li>
            <li aria-hidden="true">/</li>
            <li><Link to="/shop" className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Shop</Link></li>
            {data?.product.category_slug && (
              <>
                <li aria-hidden="true">/</li>
                <li>
                  <Link
                    to={`/shop/category/${data.product.category_slug}`}
                    className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                  >
                    {data.product.category_name ?? 'Category'}
                  </Link>
                </li>
              </>
            )}
            {data && (
              <>
                <li aria-hidden="true">/</li>
                <li className="font-bold text-brand-ink" aria-current="page">{data.product.name}</li>
              </>
            )}
          </ol>
        </nav>

        {productQuery.isPending && <div className="mt-8"><LoadingState text="Loading product details..." /></div>}

        {productQuery.isError && (
          <div className="mt-8">
            <ErrorState
              title="This product is unavailable"
              description={apiErrorMessage(productQuery.error, 'It may have been removed from the public catalogue.')}
              retry={() => void productQuery.refetch()}
            />
            <div className="flex justify-center">
              <Link to="/shop" className="inline-flex min-h-11 items-center rounded-lg bg-brand-ink px-5 text-sm font-bold text-white hover:bg-brand-ink/85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                Back to shop
              </Link>
            </div>
          </div>
        )}

        {data && (
          <>
            <section className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start xl:grid-cols-[76px_minmax(0,420px)_minmax(0,1fr)_320px]">
              <div className="hidden xl:block" aria-label="Product image thumbnails">
                <div className="rounded-md border-2 border-brand-green-deep bg-white p-1">
                  <ProductPhoto
                    src={data.product.image_url}
                    alt={data.product.name}
                    caption="Photo"
                    className="aspect-square w-full rounded"
                    imageClassName="p-2"
                    eager
                  />
                </div>
              </div>

              <div className="overflow-hidden rounded-lg border border-brand-ink/10 bg-white lg:col-start-1 lg:row-start-1 xl:col-start-2 xl:row-start-1">
                <ProductPhoto
                  src={data.product.image_url}
                  alt={data.product.name}
                  caption="Product photo"
                  className="aspect-square w-full"
                  imageClassName="p-8"
                  eager
                />
              </div>

              <div className="min-w-0 lg:col-start-1 lg:row-start-2 xl:col-start-3 xl:row-start-1">
                {data.product.category_name && (
                  <Link
                    to={data.product.category_slug ? `/shop/category/${data.product.category_slug}` : '/shop'}
                    className="inline-flex min-h-11 items-center rounded text-xs font-bold uppercase tracking-[0.16em] text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
                  >
                    {data.product.category_name}
                  </Link>
                )}

                <h1 className="text-2xl font-black leading-tight tracking-tight text-brand-ink sm:text-3xl">{data.product.name}</h1>
                <p className="mt-3 text-sm leading-relaxed text-brand-ink/80">{data.product.short_description}</p>

                <div className="mt-6 border-t border-brand-ink/10 pt-5">
                  <h2 className="text-sm font-black text-brand-ink">Product details</h2>
                  <dl className="mt-3 divide-y divide-brand-ink/10 text-sm">
                    <div className="flex justify-between gap-4 py-2">
                      <dt className="text-brand-ink/60">Category</dt>
                      <dd className="text-right font-semibold text-brand-ink">{data.product.category_name ?? 'Uncategorised'}</dd>
                    </div>
                    <div className="flex justify-between gap-4 py-2">
                      <dt className="text-brand-ink/60">Sold by</dt>
                      <dd className="text-right font-semibold text-brand-ink">{branchName}</dd>
                    </div>
                    <div className="flex justify-between gap-4 py-2">
                      <dt className="text-brand-ink/60">Collection</dt>
                      <dd className="text-right font-semibold text-brand-ink">Pay on collection</dd>
                    </div>
                  </dl>
                </div>

                <div className="mt-6 border-t border-brand-ink/10 pt-5">
                  <h2 className="text-sm font-black text-brand-ink">Description</h2>
                  <p className="mt-3 whitespace-pre-line text-sm leading-7 text-brand-ink/80">
                    {data.product.public_description || data.product.short_description}
                  </p>
                </div>

                <div className="mt-6 grid gap-3 sm:grid-cols-3">
                  <div className="rounded-lg border border-brand-ink/10 bg-white p-3">
                    <TruckIcon className="h-5 w-5 text-brand-green-deep" />
                    <p className="mt-2 text-xs font-bold text-brand-ink">Branch collection</p>
                    <p className="mt-1 text-[11px] text-brand-ink/70">Ready when you arrive</p>
                  </div>
                  <div className="rounded-lg border border-brand-ink/10 bg-white p-3">
                    <ShieldIcon className="h-5 w-5 text-brand-green-deep" />
                    <p className="mt-2 text-xs font-bold text-brand-ink">Genuine stock</p>
                    <p className="mt-1 text-[11px] text-brand-ink/70">Managed in the TMS system</p>
                  </div>
                  <div className="rounded-lg border border-brand-ink/10 bg-white p-3">
                    <ClockIcon className="h-5 w-5 text-brand-green-deep" />
                    <p className="mt-2 text-xs font-bold text-brand-ink">Reserve option</p>
                    <p className="mt-1 text-[11px] text-brand-ink/70">Short holds on selected parts</p>
                  </div>
                </div>
              </div>

              <aside className="h-fit rounded-lg border border-brand-ink/10 bg-white p-5 shadow-sm lg:sticky lg:top-36 lg:col-start-2 lg:row-span-2 lg:row-start-1 xl:col-start-4 xl:row-span-1 xl:row-start-1">
                <p className="text-xs font-bold uppercase tracking-wider text-brand-ink/60">Online price</p>
                <p className="mt-1 text-3xl font-black tracking-tight text-brand-ink">{data.product.price}</p>
                <p className="mt-2 flex items-center gap-1.5 text-xs font-bold text-brand-success">
                  <CheckIcon className="h-4 w-4" />
                  Available to order online
                </p>

                <div className="mt-5 border-t border-brand-ink/10 pt-5">
                  <label className="text-xs font-bold text-brand-ink" htmlFor="product-quantity">Quantity</label>
                  <div className="mt-2 inline-flex items-center rounded-lg border border-brand-ink/20">
                    <button
                      type="button"
                      onClick={() => setQuantity((current) => Math.max(1, current - 1))}
                      className="h-11 w-11 rounded-l-lg text-lg font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                      aria-label="Decrease quantity"
                    >
                      -
                    </button>
                    <span id="product-quantity" className="w-12 text-center text-sm font-bold" aria-live="polite">{quantity}</span>
                    <button
                      type="button"
                      onClick={() => setQuantity((current) => Math.min(999, current + 1))}
                      className="h-11 w-11 rounded-r-lg text-lg font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                      aria-label="Increase quantity"
                    >
                      +
                    </button>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={addToCart}
                  className="mt-5 inline-flex min-h-12 w-full items-center justify-center rounded-full bg-brand-green-deep px-5 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
                >
                  Add to cart
                </button>
                <button
                  type="button"
                  onClick={buyNow}
                  className="mt-3 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full border border-brand-green-deep bg-white px-5 text-sm font-bold text-brand-green-deep transition-colors duration-200 hover:bg-brand-pale focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
                >
                  Buy now
                  <ArrowRightIcon className="h-4 w-4" />
                </button>

                {data.can_reserve && data.product.can_reserve && (
                  <Link
                    to={`/reserve/${data.product.slug}`}
                    className="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg text-sm font-bold text-brand-ink underline-offset-4 hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                  >
                    Reserve this part instead
                  </Link>
                )}

                <div className="mt-5 space-y-3 border-t border-brand-ink/10 pt-5 text-xs text-brand-ink/80">
                  <p className="flex items-start gap-2"><CheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-brand-green-deep" /> Secure checkout with your TMS account</p>
                  <p className="flex items-start gap-2"><TruckIcon className="mt-0.5 h-4 w-4 shrink-0 text-brand-green-deep" /> Collect from {branchName}</p>
                  <p className="flex items-start gap-2"><ShieldIcon className="mt-0.5 h-4 w-4 shrink-0 text-brand-green-deep" /> Pay when you collect your order</p>
                </div>
              </aside>
            </section>

            {data.related.length > 0 && (
              <section className="mt-12 border-t border-brand-ink/10 pt-8" aria-labelledby="related-products-heading">
                <div className="flex flex-wrap items-end justify-between gap-3">
                  <h2 id="related-products-heading" className="text-2xl font-black tracking-tight text-brand-ink">Related spares</h2>
                  <Link to="/shop" className="inline-flex min-h-11 items-center gap-1 rounded-md text-sm font-bold text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
                    See all products <ArrowRightIcon className="h-4 w-4" />
                  </Link>
                </div>
                <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  {data.related.map((product) => (
                    <ProductCard key={product.id} product={product} />
                  ))}
                </div>
              </section>
            )}
          </>
        )}
      </div>
    </PublicShell>
  )
}
