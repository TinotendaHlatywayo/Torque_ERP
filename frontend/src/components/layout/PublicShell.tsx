import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CartIcon, ChevronIcon, CloseIcon, MenuIcon, SearchIcon, UserIcon } from '@/components/public/icons'
import { useCart } from '@/hooks/useCart'
import { useStorefrontAccount } from '@/hooks/useStorefrontAccount'
import { getPublicSite } from '@/lib/publicApi'
import { cn } from '@/lib/utils'

function navClass({ isActive }: { isActive: boolean }) {
  return cn(
    'inline-flex min-h-11 items-center rounded-md px-3 text-sm font-semibold transition-colors duration-200 motion-reduce:transition-none',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2',
    isActive ? 'bg-brand-green/10 text-brand-green-deep' : 'text-brand-ink/80 hover:bg-brand-surface hover:text-brand-ink',
  )
}

function StorefrontSearch({ id, className }: { id: string; className?: string }) {
  const [value, setValue] = useState('')
  const navigate = useNavigate()

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const term = value.trim()
    navigate(term ? `/shop?q=${encodeURIComponent(term)}` : '/shop')
  }

  return (
    <form role="search" onSubmit={submit} className={cn('flex w-full', className)}>
      <label htmlFor={id} className="sr-only">Search products</label>
      <div className="relative min-w-0 flex-1">
        <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-brand-ink/60" />
        <input
          id={id}
          type="search"
          value={value}
          onChange={(event) => setValue(event.target.value)}
          placeholder="Search motor spares, filters, brakes..."
          maxLength={80}
          className="h-11 w-full rounded-l-lg border border-brand-ink/20 bg-white pl-10 pr-3 text-sm text-brand-ink placeholder:text-brand-ink/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-1 sm:h-12"
        />
      </div>
      <button
        type="submit"
        className="inline-flex min-h-11 items-center gap-2 rounded-r-lg bg-brand-green-deep px-4 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-green-deep/90 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2 sm:min-h-12 sm:px-6"
        aria-label="Search products"
      >
        <SearchIcon className="h-5 w-5" />
        <span className="hidden sm:inline">Search</span>
      </button>
    </form>
  )
}

export function PublicShell({ children }: { children: ReactNode }) {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [scrollProgress, setScrollProgress] = useState(0)
  const location = useLocation()
  const { itemCount } = useCart()
  const accountQuery = useStorefrontAccount()
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const signedIn = Boolean(accountQuery.data)
  const categories = siteQuery.data?.categories ?? []
  const siteText = siteQuery.data?.content.text
  const siteImages = siteQuery.data?.content.images
  const logoImage = siteImages?.logo_media_id
  const text = (key: string, fallback: string) => siteText?.[key] || fallback

  useEffect(() => {
    setMobileOpen(false)
  }, [location.pathname])

  useEffect(() => {
    const favicon = siteImages?.favicon_media_id?.url
    if (!favicon) return
    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }
    link.removeAttribute('type')
    link.href = favicon
  }, [siteImages?.favicon_media_id?.url])

  useEffect(() => {
    const updateProgress = () => {
      const scrollable = document.documentElement.scrollHeight - window.innerHeight
      setScrollProgress(scrollable > 0 ? Math.min(100, (window.scrollY / scrollable) * 100) : 0)
    }

    updateProgress()
    window.addEventListener('scroll', updateProgress, { passive: true })
    window.addEventListener('resize', updateProgress)
    return () => {
      window.removeEventListener('scroll', updateProgress)
      window.removeEventListener('resize', updateProgress)
    }
  }, [location.pathname])

  return (
    <div className="flex min-h-screen flex-col bg-brand-surface text-brand-ink">
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-white focus:px-4 focus:py-3 focus:text-sm focus:font-bold focus:text-brand-ink focus:shadow-lg focus:ring-2 focus:ring-brand-green"
      >
        Skip to content
      </a>

      <div
        className="fixed inset-x-0 top-0 z-[60] h-0.5 bg-brand-mint transition-[width] duration-150 motion-reduce:transition-none"
        style={{ width: `${scrollProgress}%` }}
        aria-hidden="true"
      />

      <div className="hidden bg-brand-ink text-brand-surface/70 sm:block">
        <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-5 py-1.5 text-[11px] font-semibold sm:px-6 lg:px-8">
          <p>{text('header_note', 'Taziva Motor Spares · Genuine parts from our Rusape branch')}</p>
          <p>{text('header_promo', 'Pay on collection · Order online')}</p>
        </div>
      </div>

      <header className="sticky top-0 z-50 border-b border-brand-ink/10 bg-white shadow-sm">
        <div className="mx-auto flex w-full max-w-7xl items-center gap-3 px-5 py-3 sm:px-6 lg:gap-5 lg:px-8">
          <Link
            to="/"
            className="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
            aria-label={`${text('brand_name', 'Taziva Motor Spares')} home`}
          >
            <span className="flex h-10 items-center rounded-lg bg-white px-2 shadow-sm ring-1 ring-brand-ink/10">
              <img src={logoImage?.url ?? '/tms_logo_transparent.png'} alt={logoImage?.alt ?? text('logo_alt', 'Taziva Motor Spares')} className="h-8 w-auto max-w-[120px] object-contain" />
            </span>
          </Link>

          <StorefrontSearch id="storefront-search-desktop" className="hidden flex-1 md:flex" />

          <Link
            to={signedIn ? '/account' : '/login?redirect=/account'}
            className="hidden min-h-11 items-center gap-2 rounded-md px-2 text-sm font-semibold text-brand-ink transition-colors duration-200 hover:text-brand-green-deep focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2 lg:inline-flex"
          >
            <UserIcon className="h-5 w-5" />
            <span className="flex flex-col leading-tight">
              <span className="text-[11px] text-brand-ink/60">{signedIn ? 'Hello' : 'Sign in'}</span>
              <span className="text-xs font-bold">{signedIn ? 'My account' : text('customer_login_label', 'Customer login')}</span>
            </span>
          </Link>

          <Link
            to="/cart"
            className="relative inline-flex min-h-11 items-center gap-2 rounded-md px-2 text-sm font-bold text-brand-ink transition-colors duration-200 hover:text-brand-green-deep focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2"
            aria-label={`Cart with ${itemCount} item${itemCount === 1 ? '' : 's'}`}
          >
            <span className="relative flex h-10 w-10 items-center justify-center rounded-full hover:bg-brand-surface">
              <CartIcon className="h-6 w-6" />
              {itemCount > 0 && (
                <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-green-deep px-1 text-[10px] font-black text-white" aria-hidden="true">
                  {itemCount > 99 ? '99+' : itemCount}
                </span>
              )}
            </span>
            <span className="hidden text-sm font-bold lg:inline">Cart</span>
          </Link>

          <button
            type="button"
            className="inline-flex h-11 w-11 items-center justify-center rounded-md border border-brand-ink/10 text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2 md:hidden"
            aria-label={mobileOpen ? 'Close navigation menu' : 'Open navigation menu'}
            aria-expanded={mobileOpen}
            aria-controls="public-mobile-nav"
            onClick={() => setMobileOpen((open) => !open)}
          >
            {mobileOpen ? <CloseIcon /> : <MenuIcon />}
          </button>
        </div>

        <nav className="hidden border-t border-brand-ink/5 bg-brand-surface md:block" aria-label="Store categories">
          <div className="mx-auto flex w-full max-w-7xl items-center gap-1 overflow-x-auto px-5 py-1.5 sm:px-6 lg:px-8">
            <NavLink to="/products" className={navClass}>Products</NavLink>
            <NavLink to="/services" className={navClass}>Services</NavLink>
            <NavLink to="/about" className={navClass}>About</NavLink>
            <NavLink to="/contact" className={navClass}>Contact</NavLink>
            <span className="mx-1 h-5 w-px shrink-0 bg-brand-ink/10" aria-hidden="true" />
            <Link to="/shop" className="inline-flex min-h-10 shrink-0 items-center gap-1 rounded px-3 text-xs font-bold text-brand-ink hover:bg-white hover:text-brand-green-deep focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
              All spares <ChevronIcon className="h-4 w-4" />
            </Link>
            {categories.map((category) => (
              <Link
                key={category.slug}
                to={`/shop/category/${category.slug}`}
                className="inline-flex min-h-10 shrink-0 items-center rounded px-3 text-xs font-semibold text-brand-ink/70 hover:bg-white hover:text-brand-green-deep focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
              >
                {category.name}
              </Link>
            ))}
            <Link to="/account" className="ml-auto inline-flex min-h-10 shrink-0 items-center rounded px-3 text-xs font-bold text-brand-green-deep hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
              Orders &amp; reservations
            </Link>
          </div>
        </nav>

        {mobileOpen && (
          <nav id="public-mobile-nav" className="border-t border-brand-ink/10 bg-white px-5 py-4 md:hidden" aria-label="Mobile navigation">
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
              <StorefrontSearch id="storefront-search-mobile" />
              <div className="flex flex-col gap-1">
                <NavLink to="/" end className={navClass}>Home</NavLink>
                <NavLink to="/products" className={navClass}>Products</NavLink>
                <NavLink to="/services" className={navClass}>Services</NavLink>
                <NavLink to="/about" className={navClass}>About us</NavLink>
                <NavLink to="/contact" className={navClass}>Contact us</NavLink>
                <NavLink to="/shop" className={navClass}>All spares</NavLink>
                <NavLink to="/cart" className={navClass}>Cart ({itemCount})</NavLink>
                <NavLink to={signedIn ? '/account' : '/signup'} className={navClass}>{signedIn ? 'My account' : 'Create account'}</NavLink>
                {categories.map((category) => (
                  <NavLink key={category.slug} to={`/shop/category/${category.slug}`} className={navClass}>{category.name}</NavLink>
                ))}
                <NavLink to="/login" className={navClass}>{text('team_login_label', 'Team sign in')}</NavLink>
              </div>
            </div>
          </nav>
        )}
      </header>

      <main id="main-content" tabIndex={-1} className="flex-1 outline-none">
        <div key={location.pathname} className="animate-fade-up">
          {children}
        </div>
      </main>

      <footer className="relative overflow-hidden border-t border-brand-ink/10 bg-brand-ink text-brand-surface/70">
        <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
        <div className="relative">
          <div className="mx-auto grid w-full max-w-7xl gap-8 px-5 py-12 sm:px-6 md:grid-cols-4 lg:px-8">
            <div>
              <p className="text-base font-extrabold text-white">{text('brand_name', 'Taziva Motor Spares')}</p>
              <p className="mt-2 max-w-md text-sm leading-relaxed text-brand-surface/60">
                {text('footer_about', 'Browse genuine motor spares, reserve short-stock parts, and order online for collection from our Rusape branch.')}
              </p>
            </div>
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-surface/60">Company</p>
              <div className="mt-3 flex flex-col gap-1">
                <Link to="/products" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">Products</Link>
                <Link to="/services" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">Services</Link>
                <Link to="/about" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">About us</Link>
                <Link to="/contact" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">Contact us</Link>
              </div>
            </div>
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-surface/60">Shop</p>
              <div className="mt-3 flex flex-col gap-1">
                <Link to="/shop" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">All spares</Link>
                <Link to="/cart" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">Your cart</Link>
                <Link to="/account" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">Orders &amp; reservations</Link>
              </div>
            </div>
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-surface/60">Support</p>
              <div className="mt-3 flex flex-col gap-1">
                <Link to="/signup" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">Create customer account</Link>
                <Link to="/login" className="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint focus-visible:ring-offset-2 focus-visible:ring-offset-brand-ink">{text('team_login_label', 'Team sign in')}</Link>
              </div>
            </div>
          </div>
          <div className="border-t border-white/10">
            <div className="mx-auto w-full max-w-7xl px-5 py-5 text-xs text-brand-surface/60 sm:px-6 lg:px-8">
              © {new Date().getFullYear()} {text('footer_copyright', 'Taziva Motor Spares')}. All rights reserved.
            </div>
          </div>
        </div>
      </footer>
    </div>
  )
}
