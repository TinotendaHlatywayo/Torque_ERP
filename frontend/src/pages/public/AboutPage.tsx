import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { PublicShell } from '@/components/layout/PublicShell'
import { ArrowRightIcon, CheckIcon, ShieldIcon, TruckIcon, WrenchIcon } from '@/components/public/icons'
import { Reveal } from '@/components/ui/Reveal'
import { usePageMeta } from '@/hooks/usePageMeta'
import { getPublicSite } from '@/lib/publicApi'

export default function AboutPage() {
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const content = siteQuery.data?.content
  const text = (key: string, fallback: string) => content?.text[key] || fallback
  const heroImage = content?.images.about_media_id

  usePageMeta(
    text('about_meta_title', 'About Us | Taziva Motor Spares'),
    text('about_meta_description', 'Learn how Taziva Motor Spares supports drivers and workshops with motor spares and local service in Rusape.'),
  )

  return (
    <PublicShell>
      <section className="relative overflow-hidden bg-brand-ink text-white">
        <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
        <div className="relative mx-auto w-full max-w-7xl px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-brand-mint">{text('about_eyebrow', 'About TMS')}</p>
          <h1 className="mt-5 max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-5xl">
            {text('about_heading', 'Parts and workshop support you can count on.')}
          </h1>
          <p className="mt-6 max-w-2xl text-base leading-relaxed text-brand-surface/70 sm:text-lg">
            {text('about_intro', 'Taziva Motor Spares brings parts enquiries, online ordering and workshop support together in one place for drivers and fleets in Rusape and beyond.')}
          </p>
          {heroImage && <img src={heroImage.url} alt={heroImage.alt} className="mt-8 h-52 w-full max-w-2xl rounded-xl border border-white/15 object-cover" />}
        </div>
      </section>

      <section className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8 lg:py-16">
        <Reveal>
          <div className="grid gap-8 lg:grid-cols-2 lg:items-start">
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">{text('about_approach_eyebrow', 'Our approach')}</p>
              <h2 className="mt-2 text-2xl font-black tracking-tight text-brand-ink sm:text-3xl">{text('about_approach_heading', 'Fitment first, price clearly.')}</h2>
              <p className="mt-4 text-sm leading-7 text-brand-ink/80">
                {text('about_approach_body_one', 'Tell us what vehicle you drive and what it needs. We help confirm the right part, show the current catalogue price, and give you a simple way to order or reserve.')}
              </p>
              <p className="mt-4 text-sm leading-7 text-brand-ink/80">
                {text('about_approach_body_two', 'Our online store is connected to the same TMS stock system used by the branch, so customers see live product information instead of a static catalogue.')}
              </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <ShieldIcon className="h-7 w-7 text-brand-green-deep" />
                <h3 className="mt-4 text-base font-black text-brand-ink">Quality-focused sourcing</h3>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">Parts selected with reliability and fitment in mind.</p>
              </div>
              <div className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <WrenchIcon className="h-7 w-7 text-brand-green-deep" />
                <h3 className="mt-4 text-base font-black text-brand-ink">Local guidance</h3>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">A real team to help with vehicle and part details.</p>
              </div>
              <div className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <TruckIcon className="h-7 w-7 text-brand-green-deep" />
                <h3 className="mt-4 text-base font-black text-brand-ink">Easy collection</h3>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">Order online and collect from the branch.</p>
              </div>
              <div className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <CheckIcon className="h-7 w-7 text-brand-green-deep" />
                <h3 className="mt-4 text-base font-black text-brand-ink">Transparent buying</h3>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">Clear names, photos, descriptions and prices.</p>
              </div>
            </div>
          </div>
        </Reveal>
      </section>

      <section className="border-y border-brand-ink/10 bg-white py-12 lg:py-16">
        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Next step</p>
              <h2 className="mt-2 text-2xl font-black tracking-tight text-brand-ink sm:text-3xl">{text('about_next_heading', 'Find the part you need.')}</h2>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link to="/products" className="inline-flex min-h-11 items-center gap-2 rounded-full bg-brand-green-deep px-5 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                Browse products <ArrowRightIcon className="h-4 w-4" />
              </Link>
              <Link to="/contact" className="inline-flex min-h-11 items-center rounded-full border border-brand-ink/20 px-5 text-sm font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                Contact us
              </Link>
            </div>
          </div>
        </div>
      </section>
    </PublicShell>
  )
}
