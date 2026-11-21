import { type ReactNode } from "react"
import { Link } from "react-router-dom"
import { useQuery } from "@tanstack/react-query"
import { ArrowRightIcon, ClockIcon, ShieldIcon } from "@/components/public/icons"
import { getPublicSite } from "@/lib/publicApi"

export function AuthShell({ children }: { children: ReactNode }) {
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const logo = siteQuery.data?.content.images.logo_media_id
  const logoUrl = logo?.url ?? "/tms_logo_transparent.png"
  const logoAlt = logo?.alt ?? siteQuery.data?.content.text.logo_alt ?? "Taziva Motor Spares"

  return (
    <div className="grid min-h-screen bg-brand-surface lg:grid-cols-[1.05fr_0.95fr]">
      <aside className="relative hidden overflow-hidden bg-brand-ink text-white lg:flex lg:flex-col lg:justify-between">
        <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
        <div className="animate-glow absolute -left-24 top-1/4 h-80 w-80 rounded-full bg-brand-green/40 blur-3xl" aria-hidden="true" />
        <div className="animate-float absolute -right-20 bottom-10 h-64 w-64 rounded-full bg-brand-mint/20 blur-3xl" aria-hidden="true" />

        <div className="relative z-10 p-12">
          <Link
            to="/"
            className="inline-flex min-h-11 items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint"
          >
            <span className="flex h-12 items-center rounded-xl bg-white px-2 shadow-lg">
              <img src={logoUrl} alt={logoAlt} className="h-9 w-auto max-w-[150px] object-contain" />
            </span>
          </Link>
        </div>

        <div className="relative z-10 max-w-xl px-12 pb-10">
          <p className="text-xs font-bold uppercase tracking-[0.24em] text-brand-mint">One connected workspace</p>
          <h1 className="mt-5 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
            Stock, sales and workshop — in one place.
          </h1>
          <p className="mt-5 max-w-lg text-base leading-relaxed text-brand-surface/70">
            Sign in to manage reservations, branch stock, invoices, payroll, workshop jobs and live ERP reporting.
          </p>
          <div className="mt-10 grid gap-3 sm:grid-cols-2">
            <div className="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
              <ClockIcon className="h-5 w-5 text-brand-mint" />
              <p className="mt-3 text-sm font-bold">Real-time operations</p>
              <p className="mt-1 text-xs leading-relaxed text-brand-surface/60">See branch activity as it happens.</p>
            </div>
            <div className="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
              <ShieldIcon className="h-5 w-5 text-brand-mint" />
              <p className="mt-3 text-sm font-bold">Role-based access</p>
              <p className="mt-1 text-xs leading-relaxed text-brand-surface/60">Every action is permission checked.</p>
            </div>
          </div>
        </div>

        <div className="relative z-10 p-12">
          <Link
            to="/"
            className="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-bold text-brand-surface/80 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint"
          >
            Back to public website
            <ArrowRightIcon className="h-4 w-4" />
          </Link>
        </div>
      </aside>

      <main className="flex items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div className="w-full max-w-md animate-fade-up">
          <div className="mb-8 text-center lg:hidden">
            <Link
              to="/"
              className="inline-flex min-h-11 items-center gap-2 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
            >
              <span className="flex h-10 items-center rounded-lg bg-brand-green-deep px-2">
                <img src={logoUrl} alt={logoAlt} className="h-8 w-auto max-w-[120px] object-contain" />
              </span>
            </Link>
          </div>
          {children}
        </div>
      </main>
    </div>
  )
}
