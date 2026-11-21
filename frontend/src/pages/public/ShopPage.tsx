import { useEffect, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/StateComponents'
import { ShieldIcon, TruckIcon } from '@/components/public/icons'
import { usePageMeta } from '@/hooks/usePageMeta'
import { PublicShell } from '@/components/layout/PublicShell'
import { ProductCard } from '@/components/public/ProductCard'
import { apiErrorMessage } from '@/lib/apiErrors'
import { getPublicProducts, getPublicSite } from '@/lib/publicApi'
import { cn } from '@/lib/utils'

export default function ShopPage() {
  const { categorySlug } = useParams()
  const [searchParams, setSearchParams] = useSearchParams()
  const query = searchParams.get('q') ?? ''
  const categoryParam = searchParams.get('category') ?? ''
  const category = categorySlug ?? categoryParam
  const parsedPage = Number(searchParams.get('page') ?? '1')
  const page = Number.isInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1
  const [searchInput, setSearchInput] = useState(query)

  useEffect(() => {
    setSearchInput(query)
  }, [query])

  useEffect(() => {
    if (!categorySlug || categoryParam === categorySlug) {
      return
    }
    setSearchParams((previous) => {
      const next = new URLSearchParams(previous)
      next.set('category', categorySlug)
      next.delete('page')
      return next
    }, { replace: true })
  }, [categoryParam, categorySlug, setSearchParams])

  const productsQuery = useQuery({
    queryKey: ['public', 'products', { query, category, page }],
    queryFn: () => getPublicProducts({ q: query, category, page }),
  })
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const text = (key: string, fallback: string) => siteQuery.data?.content.text[key] || fallback

  const hrefWithQuery = (path: string) => {
    const params = new URLSearchParams()
    if (query) {
      params.set('q', query)
    }
    const suffix = params.toString()
    return suffix ? `${path}?${suffix}` : path
  }

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const next = new URLSearchParams(searchParams)
    const value = searchInput.trim()
    if (value) {
      next.set('q', value)
    } else {
      next.delete('q')
    }
    next.delete('page')
    setSearchParams(next)
  }

  const goToPage = (nextPage: number) => {
    const next = new URLSearchParams(searchParams)
    if (nextPage > 1) {
      next.set('page', String(nextPage))
    } else {
      next.delete('page')
    }
    setSearchParams(next)
  }

  const meta = productsQuery.data?.meta
  const products = productsQuery.data?.data ?? []
  const categories = productsQuery.data?.categories ?? []
  const activeCategory = categories.find((item) => item.slug === category)?.name
  const lastPage = Math.max(1, meta?.last_page ?? 1)

  usePageMeta(
    activeCategory ? `${activeCategory} | ${text('brand_name', 'Taziva Motor Spares')}` : text('products_meta_title', 'Motor Spares Catalogue | Taziva Motor Spares'),
    text('products_meta_description', 'Browse motor spares with photos, descriptions and prices, then order online or reserve stock.'),
  )

  return (
    <PublicShell>
      <section className="border-b border-brand-ink/10 bg-white">
        <div className="mx-auto grid w-full max-w-7xl gap-6 px-5 py-8 sm:px-6 lg:grid-cols-[1fr_360px] lg:items-center lg:px-8 lg:py-10">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Taziva marketplace</p>
            <h1 className="mt-2 text-3xl font-black tracking-tight text-brand-ink sm:text-4xl">
              {activeCategory ?? text('products_heading', 'Motor spares in stock')}
            </h1>
            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-brand-ink/80 sm:text-base">
              {text('products_intro', 'Browse genuine parts with clear photos, descriptions and prices. Add what you need to your cart and collect from the branch.')}
            </p>
          </div>
          <form onSubmit={submitSearch} role="search" className="w-full">
            <label htmlFor="shop-search" className="sr-only">Search the catalogue</label>
            <div className="relative">
              <input
                id="shop-search"
                type="search"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Search by part name or keyword"
                maxLength={80}
                className="h-12 w-full rounded-lg border border-brand-ink/20 bg-white px-4 pr-24 text-sm text-brand-ink placeholder:text-brand-ink/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
              />
              <button
                type="submit"
                className="absolute right-1 top-1 inline-flex h-10 items-center rounded-md bg-brand-green-deep px-4 text-xs font-bold text-white transition-colors duration-200 hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
              >
                Search
              </button>
            </div>
          </form>
        </div>
      </section>

      <div className="mx-auto w-full max-w-7xl px-5 py-6 sm:px-6 lg:px-8 lg:py-8">
        <nav aria-label="Breadcrumb" className="text-xs text-brand-ink/70">
          <ol className="flex flex-wrap items-center gap-2">
            <li><Link to="/" className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Home</Link></li>
            <li aria-hidden="true">/</li>
            <li><Link to={hrefWithQuery('/shop')} className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Shop</Link></li>
            {activeCategory && (
              <>
                <li aria-hidden="true">/</li>
                <li className="font-bold text-brand-ink" aria-current="page">{activeCategory}</li>
              </>
            )}
          </ol>
        </nav>

        <div className="mt-5 grid gap-6 lg:grid-cols-[240px_1fr]">
          <aside aria-label="Product categories" className="h-fit rounded-lg border border-brand-ink/10 bg-white p-4 shadow-sm lg:sticky lg:top-36">
            <h2 className="text-sm font-black text-brand-ink">Shop by category</h2>
            <div className="mt-3 flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible lg:pb-0">
              <Link
                to={hrefWithQuery('/shop')}
                className={cn(
                  'inline-flex min-h-11 shrink-0 items-center rounded-lg border px-4 text-sm font-semibold transition-colors duration-200 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
                  !category
                    ? 'border-brand-green-deep bg-brand-green-deep text-white'
                    : 'border-brand-ink/10 bg-white text-brand-ink/80 hover:border-brand-green hover:bg-brand-green/10',
                )}
              >
                All products
              </Link>
              {categories.map((item) => (
                <Link
                  key={item.slug}
                  to={hrefWithQuery(`/shop/category/${item.slug}`)}
                  className={cn(
                    'inline-flex min-h-11 shrink-0 items-center rounded-lg border px-4 text-sm font-semibold transition-colors duration-200 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
                    item.slug === category
                      ? 'border-brand-green-deep bg-brand-green-deep text-white'
                      : 'border-brand-ink/10 bg-white text-brand-ink/80 hover:border-brand-green hover:bg-brand-green/10',
                  )}
                >
                  {item.name}
                </Link>
              ))}
            </div>

            <div className="mt-6 hidden border-t border-brand-ink/10 pt-5 lg:block">
              <h3 className="text-xs font-bold uppercase tracking-wider text-brand-ink/60">Why shop online</h3>
              <ul className="mt-3 space-y-3 text-xs text-brand-ink/80">
                <li className="flex items-start gap-2"><TruckIcon className="h-4 w-4 shrink-0 text-brand-green-deep" /> Collect from our Rusape branch</li>
                <li className="flex items-start gap-2"><ShieldIcon className="h-4 w-4 shrink-0 text-brand-green-deep" /> Stock comes from the TMS system</li>
              </ul>
            </div>
          </aside>

          <section aria-label="Products" className="min-w-0">
            {productsQuery.isPending && <LoadingState text="Loading products..." />}

            {productsQuery.isError && (
              <ErrorState
                title="Products could not be loaded"
                description={apiErrorMessage(productsQuery.error, 'Please try again in a moment.')}
                retry={() => void productsQuery.refetch()}
              />
            )}

            {productsQuery.isSuccess && (
              <>
                <div className="flex flex-wrap items-end justify-between gap-3 rounded-lg border border-brand-ink/10 bg-white px-4 py-3">
                  <p className="text-sm font-bold text-brand-ink" role="status">
                    {meta?.total ?? 0} {meta?.total === 1 ? 'result' : 'results'}
                    {query && <> for “{query}”</>}
                  </p>
                  <p className="text-xs font-semibold text-brand-ink/60">Sorted by catalogue order</p>
                </div>

                {products.length === 0 ? (
                  <div className="mt-4 rounded-lg border border-brand-ink/10 bg-white">
                    <EmptyState
                      title="No products found"
                      description="Try a different search term or browse the full catalogue."
                      action={<Link to="/shop" className="inline-flex min-h-11 items-center rounded-lg bg-brand-ink px-5 text-sm font-bold text-white hover:bg-brand-ink/85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">Browse all products</Link>}
                    />
                  </div>
                ) : (
                  <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {products.map((product) => (
                      <ProductCard key={product.id} product={product} />
                    ))}
                  </div>
                )}

                {lastPage > 1 && (
                  <nav className="mt-8 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-brand-ink/10 bg-white px-4 py-3" aria-label="Pagination">
                    <button
                      type="button"
                      disabled={page <= 1}
                      onClick={() => goToPage(page - 1)}
                      className="inline-flex min-h-11 items-center rounded-lg border border-brand-ink/20 bg-white px-4 text-sm font-bold text-brand-ink hover:bg-brand-surface disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
                    >
                      Previous
                    </button>
                    <span className="text-sm font-semibold text-brand-ink/80">Page {page} of {lastPage}</span>
                    <button
                      type="button"
                      disabled={page >= lastPage}
                      onClick={() => goToPage(page + 1)}
                      className="inline-flex min-h-11 items-center rounded-lg border border-brand-ink/20 bg-white px-4 text-sm font-bold text-brand-ink hover:bg-brand-surface disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
                    >
                      Next
                    </button>
                  </nav>
                )}
              </>
            )}
          </section>
        </div>
      </div>
    </PublicShell>
  )
}
