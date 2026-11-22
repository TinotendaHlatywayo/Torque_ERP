import { useEffect, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { ErrorState, LoadingState } from '@/components/feedback/StateComponents'
import { usePageMeta } from '@/hooks/usePageMeta'
import { PublicShell } from '@/components/layout/PublicShell'
import { ProductCard } from '@/components/public/ProductCard'
import { Reveal } from '@/components/ui/Reveal'
import { ArrowRightIcon, CheckIcon, ClockIcon, SearchIcon, ShieldIcon, TruckIcon, WrenchIcon } from '@/components/public/icons'
import { apiErrorMessage } from '@/lib/apiErrors'
import { getPublicSite, type PublicSiteImage } from '@/lib/publicApi'

const heroSlideKeys = [
  'home_hero_media_id',
  'home_hero_slide_2_media_id',
  'home_hero_slide_3_media_id',
  'home_hero_slide_4_media_id',
  'home_hero_slide_5_media_id',
  'home_hero_slide_6_media_id',
  'home_hero_slide_7_media_id',
  'home_hero_slide_8_media_id',
  'home_hero_slide_9_media_id',
  'home_hero_slide_10_media_id',
]

const linkClass =
  'inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 text-sm font-bold transition-colors duration-200 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2'

export default function HomePage() {
  const [search, setSearch] = useState('')
  const [activeSlide, setActiveSlide] = useState(0)
  const navigate = useNavigate()
  const siteQuery = useQuery({
    queryKey: ['public', 'site'],
    queryFn: getPublicSite,
    staleTime: 1000 * 60 * 5,
  })

  const content = siteQuery.data?.content
  const text = (key: string, fallback: string) => content?.text[key] || fallback
  const heroSlides = heroSlideKeys
    .map((key) => content?.images[key])
    .filter((slide): slide is PublicSiteImage => Boolean(slide?.url))
  const currentSlideIndex = heroSlides.length > 0 ? activeSlide % heroSlides.length : -1

  useEffect(() => {
    if (heroSlides.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return
    }

    const timer = window.setInterval(() => setActiveSlide((current) => current + 1), 5500)
    return () => window.clearInterval(timer)
  }, [heroSlides.length])

  usePageMeta(
    text('home_meta_title', 'Taziva Motor Spares | Motor Spares & Workshop Support'),
    text('home_meta_description', 'Browse genuine motor spares with photos, descriptions and prices, then order online or reserve stock.'),
  )

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const term = search.trim()
    navigate(term ? `/shop?q=${encodeURIComponent(term)}` : '/shop')
  }

  return (
    <PublicShell>
      {siteQuery.isPending && <div className="mx-auto w-full max-w-7xl px-5 py-16 sm:px-6 lg:px-8"><LoadingState text="Loading the catalogue..." /></div>}

      {siteQuery.isError && (
        <div className="mx-auto w-full max-w-7xl px-5 py-16 sm:px-6 lg:px-8">
          <ErrorState
            title="The storefront could not be loaded"
            description={apiErrorMessage(siteQuery.error, 'Please try again in a moment.')}
            retry={() => void siteQuery.refetch()}
          />
        </div>
      )}

      {siteQuery.data && (
        <>
          <section className="relative overflow-hidden bg-brand-ink text-white">
            <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
            {heroSlides.map((slide, index) => {
              const isCurrent = index === currentSlideIndex
              return (
                <img
                  key={`${slide.url}-${index}`}
                  src={slide.url}
                  alt={isCurrent ? slide.alt : ''}
                  aria-hidden={!isCurrent}
                  loading={index === 0 ? 'eager' : 'lazy'}
                  className={`absolute inset-0 h-full w-full object-cover transition-opacity duration-1000 ease-out motion-reduce:transition-none ${isCurrent ? 'opacity-100' : 'opacity-0'}`}
                />
              )
            })}
            <div className="absolute inset-0 bg-gradient-to-r from-brand-ink/95 via-brand-ink/80 to-brand-ink/50" aria-hidden="true" />
            <div className="animate-glow pointer-events-none absolute -bottom-48 left-1/4 h-96 w-96 rounded-full bg-brand-green/30 blur-3xl" aria-hidden="true" />
            <div className="relative mx-auto grid w-full max-w-7xl gap-10 px-5 py-14 sm:px-6 lg:grid-cols-[1.2fr_0.8fr] lg:items-center lg:px-8 lg:py-20">
              <div className="animate-fade-up">
                <p className="text-xs font-bold uppercase tracking-[0.22em] text-brand-mint">
                  {text('home_eyebrow', `${siteQuery.data.branch.name} · ${siteQuery.data.branch.code}`)}
                </p>
                <h1 className="mt-5 text-4xl font-black leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl">
                  {text('home_heading', 'The right part.')} <span className="text-brand-mint">{text('home_heading_accent', 'The right price.')}</span>
                </h1>
                <p className="mt-6 max-w-2xl text-base leading-relaxed text-brand-surface/70 sm:text-lg">
                  {text('home_intro', 'Browse motor spares with clear photos, descriptions and prices. Order online for collection or reserve short-stock parts in minutes.')}
                </p>
                <div className="mt-8 flex flex-wrap items-center gap-4">
                  <Link to="/products" className={`${linkClass} bg-brand-green-deep text-white hover:bg-brand-green-deep/90`}>
                    {text('home_shop_button', 'Shop all spares')}
                    <ArrowRightIcon />
                  </Link>
                  <Link
                    to="/signup"
                    className="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-bold text-brand-surface/80 underline-offset-4 hover:text-white hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink"
                  >
                    {text('home_account_button', 'Create a customer account')}
                  </Link>
                </div>
              </div>

              <div className="animate-fade-up rounded-2xl border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur sm:p-6">
                <p className="text-sm font-black text-white">{text('home_search_title', 'What are you looking for?')}</p>
                <p className="mt-1 text-xs text-brand-surface/70">{text('home_search_subtitle', 'Search the live TMS branch catalogue.')}</p>
                <form role="search" onSubmit={submitSearch} className="mt-5">
                  <label htmlFor="hero-search" className="sr-only">Search motor spares</label>
                  <div className="relative">
                    <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-brand-ink/60" />
                    <input
                      id="hero-search"
                      type="search"
                      value={search}
                      onChange={(event) => setSearch(event.target.value)}
                      placeholder={text('home_search_placeholder', 'Try filters, brakes, spark plugs...')}
                      maxLength={80}
                      className="h-12 w-full rounded-lg border-0 bg-white pl-10 pr-4 text-sm text-brand-ink placeholder:text-brand-ink/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink"
                    />
                  </div>
                  <button type="submit" className="mt-3 inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-brand-green-deep text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">
                    Search catalogue
                  </button>
                </form>
                {siteQuery.data.categories.length > 0 && (
                  <div className="mt-5 border-t border-white/10 pt-4">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-brand-surface/60">Popular categories</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {siteQuery.data.categories.slice(0, 4).map((category) => (
                        <Link
                          key={category.slug}
                          to={`/shop/category/${category.slug}`}
                          className="inline-flex min-h-10 items-center rounded-full bg-white/10 px-3 text-xs font-semibold text-white hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink"
                        >
                          {category.name}
                        </Link>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
            {heroSlides.length > 1 && (
              <div className="absolute inset-x-0 bottom-2 z-20 flex items-center justify-center gap-2" aria-label="Hero image slideshow">
                <button
                  type="button"
                  aria-label="Previous hero image"
                  onClick={() => setActiveSlide((current) => (current - 1 + heroSlides.length) % heroSlides.length)}
                  className="flex min-h-11 min-w-11 items-center justify-center rounded-full bg-brand-ink/50 text-xl text-white backdrop-blur hover:bg-brand-ink/75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint"
                >
                  ‹
                </button>
                {heroSlides.map((slide, index) => (
                  <button
                    key={`${slide.url}-dot-${index}`}
                    type="button"
                    aria-label={`Show hero image ${index + 1}: ${slide.alt}`}
                    aria-current={index === currentSlideIndex ? 'true' : undefined}
                    onClick={() => setActiveSlide(index)}
                    className="flex h-11 w-7 items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint"
                  >
                    <span className={`h-2 w-2 rounded-full transition-all duration-300 motion-reduce:transition-none ${index === currentSlideIndex ? 'w-6 bg-brand-mint' : 'bg-white/60'}`} />
                  </button>
                ))}
                <button
                  type="button"
                  aria-label="Next hero image"
                  onClick={() => setActiveSlide((current) => (current + 1) % heroSlides.length)}
                  className="flex min-h-11 min-w-11 items-center justify-center rounded-full bg-brand-ink/50 text-xl text-white backdrop-blur hover:bg-brand-ink/75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint"
                >
                  ›
                </button>
              </div>
            )}
          </section>

          {!siteQuery.data.catalogue_ready && (
            <div className="border-b border-amber-200 bg-amber-50" role="status">
              <p className="mx-auto w-full max-w-7xl px-5 py-4 text-sm text-amber-900 sm:px-6 lg:px-8">
                The storefront is online, but no active branch has been configured yet. Catalogue browsing and orders will appear once a branch is active.
              </p>
            </div>
          )}

          <section className="border-b border-brand-ink/10 bg-white">
            <Reveal>
              <div className="mx-auto grid w-full max-w-7xl gap-4 px-5 py-6 sm:grid-cols-3 sm:px-6 lg:px-8">
                <div className="flex items-start gap-3">
                  <span className="mt-0.5 shrink-0 text-brand-green-deep"><ShieldIcon /></span>
                  <div>
                    <h2 className="text-sm font-black text-brand-ink">{text('home_benefit_one_title', 'Genuine branch stock')}</h2>
                    <p className="mt-1 text-sm leading-relaxed text-brand-ink/80">{text('home_benefit_one_body', 'Every listing comes from the active TMS catalogue.')}</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <span className="mt-0.5 shrink-0 text-brand-green-deep"><TruckIcon /></span>
                  <div>
                    <h2 className="text-sm font-black text-brand-ink">{text('home_benefit_two_title', 'Collect locally')}</h2>
                    <p className="mt-1 text-sm leading-relaxed text-brand-ink/80">{text('home_benefit_two_body', 'Order online and pay when you collect from Rusape.')}</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <span className="mt-0.5 shrink-0 text-brand-green-deep"><ClockIcon /></span>
                  <div>
                    <h2 className="text-sm font-black text-brand-ink">{text('home_benefit_three_title', 'Short-stock reservations')}</h2>
                    <p className="mt-1 text-sm leading-relaxed text-brand-ink/80">{text('home_benefit_three_body', 'Hold selected parts for up to {minutes} minutes.').replace('{minutes}', String(siteQuery.data.reservation_minutes))}</p>
                  </div>
                </div>
              </div>
            </Reveal>
          </section>

          {siteQuery.data.categories.length > 0 && (
            <section className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8 lg:py-16">
              <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                  <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">{text('home_browse_eyebrow', 'Browse the catalogue')}</p>
                  <h2 className="mt-2 text-2xl font-black tracking-tight text-brand-ink sm:text-3xl">{text('home_browse_heading', 'Shop by category')}</h2>
                </div>
                <Link to="/shop" className="inline-flex min-h-11 items-center gap-1 rounded-md text-sm font-bold text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                  All products <ArrowRightIcon className="h-4 w-4" />
                </Link>
              </div>
              <Reveal delay={80}>
                <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  {siteQuery.data.categories.slice(0, 8).map((category) => (
                    <Link
                      key={category.slug}
                      to={`/shop/category/${category.slug}`}
                      className="group inline-flex min-h-28 flex-col justify-between rounded-lg border border-brand-ink/10 bg-white p-5 shadow-sm transition-colors duration-200 hover:border-brand-green hover:bg-brand-pale/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
                    >
                      <span className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-pale text-brand-green-deep">
                        <WrenchIcon className="h-5 w-5" />
                      </span>
                      <span className="mt-3 flex items-center justify-between gap-2 text-sm font-bold text-brand-ink">
                        {category.name}
                        <ArrowRightIcon className="h-4 w-4 shrink-0 text-brand-green-deep transition-transform duration-200 motion-safe:group-hover:translate-x-1" />
                      </span>
                    </Link>
                  ))}
                </div>
              </Reveal>
            </section>
          )}

          {siteQuery.data.featured.length > 0 && (
            <section className="border-y border-brand-ink/10 bg-white py-12 lg:py-16">
              <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">{text('home_featured_eyebrow', 'Popular right now')}</p>
                    <h2 className="mt-2 text-2xl font-black tracking-tight text-brand-ink sm:text-3xl">{text('home_featured_heading', 'Featured spares')}</h2>
                  </div>
                  <Link to="/shop" className="inline-flex min-h-11 items-center gap-1 rounded-md text-sm font-bold text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                    See everything <ArrowRightIcon className="h-4 w-4" />
                  </Link>
                </div>
                <Reveal delay={100}>
                  <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {siteQuery.data.featured.map((product) => (
                      <ProductCard key={product.id} product={product} />
                    ))}
                  </div>
                </Reveal>
              </div>
            </section>
          )}

          <section className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8 lg:py-16">
            <Reveal>
              <div className="relative flex flex-col gap-6 overflow-hidden rounded-xl bg-brand-ink px-6 py-8 text-white sm:flex-row sm:items-center sm:justify-between sm:px-10 sm:py-10">
                <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
                <div className="relative z-10">
                  <h2 className="text-2xl font-black tracking-tight sm:text-3xl">Part of the TMS team?</h2>
                  <p className="mt-2 max-w-xl text-sm leading-relaxed text-brand-surface/70 sm:text-base">
                    Sign in to manage reservations, stock, sales, and the rest of your branch operations.
                  </p>
                </div>
                <div className="relative z-10 flex flex-wrap gap-3">
                  <Link to="/shop" className={`${linkClass} bg-brand-green-deep text-white hover:bg-brand-green-deep/90`}>
                    Continue shopping
                    <ArrowRightIcon />
                  </Link>
                  <Link to="/login" className={`${linkClass} border border-white/30 text-white hover:bg-white/10`}>
                    {text('team_login_label', 'Team sign in')}
                  </Link>
                </div>
              </div>
            </Reveal>
          </section>

          <section className="mx-auto w-full max-w-7xl px-5 pb-12 sm:px-6 lg:px-8 lg:pb-16">
            <div className="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-xs font-bold text-brand-ink/60">
              <span className="inline-flex items-center gap-2"><CheckIcon className="h-4 w-4 text-brand-green-deep" /> Clear prices</span>
              <span className="inline-flex items-center gap-2"><CheckIcon className="h-4 w-4 text-brand-green-deep" /> Local collection</span>
              <span className="inline-flex items-center gap-2"><CheckIcon className="h-4 w-4 text-brand-green-deep" /> TMS stock system</span>
            </div>
          </section>
        </>
      )}
    </PublicShell>
  )
}
