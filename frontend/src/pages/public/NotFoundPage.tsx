import { Link } from "react-router-dom"
import { usePageMeta } from "@/hooks/usePageMeta"
import { PublicShell } from "@/components/layout/PublicShell"
import { ArrowRightIcon } from "@/components/public/icons"
import { Reveal } from "@/components/ui/Reveal"

export default function NotFoundPage() {
  usePageMeta("Page Not Found | Taziva Motor Spares", "The page you requested could not be found on the Taziva Motor Spares website.")

  return (
    <PublicShell>
      <div className="relative mx-auto flex w-full max-w-3xl flex-col items-center px-5 py-20 text-center sm:px-6 lg:px-8 lg:py-28">
        <div className="animate-glow pointer-events-none absolute left-1/2 top-10 h-64 w-64 -translate-x-1/2 rounded-full bg-brand-mint/20 blur-3xl" aria-hidden="true" />
        <Reveal className="relative flex flex-col items-center">
        <p className="text-sm font-extrabold uppercase tracking-[0.24em] text-brand-green-deep">404</p>
        <h1 className="mt-4 text-4xl font-black tracking-tight text-brand-ink sm:text-5xl">We couldn&apos;t find that page</h1>
        <p className="mt-4 max-w-xl text-base leading-relaxed text-brand-ink/80">
          The link may be out of date. Head back to the homepage or browse the current catalogue.
        </p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
          <Link to="/" className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-green-deep px-5 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
            Go home
            <ArrowRightIcon className="h-4 w-4" />
          </Link>
          <Link to="/shop" className="inline-flex min-h-11 items-center justify-center rounded-lg border border-brand-ink/20 bg-white px-5 text-sm font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
            Browse products
          </Link>
        </div>
        </Reveal>
      </div>
    </PublicShell>
  )
}
