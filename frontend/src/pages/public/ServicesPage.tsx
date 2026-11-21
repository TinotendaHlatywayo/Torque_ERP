import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { PublicShell } from '@/components/layout/PublicShell'
import { ArrowRightIcon, ClockIcon, ShieldIcon, TruckIcon, WrenchIcon } from '@/components/public/icons'
import { Reveal } from '@/components/ui/Reveal'
import { usePageMeta } from '@/hooks/usePageMeta'
import { getPublicSite } from '@/lib/publicApi'

const services = [
  { key: 'one', title: 'Brake checks', description: 'Ask about pad condition, disc wear and the right replacement set for your vehicle.', icon: ShieldIcon },
  { key: 'two', title: 'Routine servicing', description: 'Plan oil, filter and basic maintenance needs with guidance from the TMS team.', icon: WrenchIcon },
  { key: 'three', title: 'Diagnostics enquiries', description: 'Share symptoms or part numbers and get help narrowing down the likely cause.', icon: ClockIcon },
  { key: 'four', title: 'Suspension and steering', description: 'Source shocks, mounts and related parts for stable handling on local roads.', icon: TruckIcon },
  { key: 'five', title: 'Fitment support', description: 'Confirm vehicle details before you buy so you order the correct part the first time.', icon: ShieldIcon },
  { key: 'six', title: 'Online parts ordering', description: 'Browse published stock, add parts to your cart, and collect from the branch.', icon: TruckIcon },
]

export default function ServicesPage() {
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const content = siteQuery.data?.content
  const text = (key: string, fallback: string) => content?.text[key] || fallback
  const heroImage = content?.images.services_media_id

  usePageMeta(
    text('services_meta_title', 'Services | Taziva Motor Spares'),
    text('services_meta_description', 'Explore parts guidance, workshop support and online ordering services from Taziva Motor Spares in Rusape.'),
  )

  return (
    <PublicShell>
      <section className="relative overflow-hidden bg-brand-ink text-white">
        <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
        <div className="relative mx-auto w-full max-w-7xl px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-brand-mint">{text('services_eyebrow', 'Our services')}</p>
          <h1 className="mt-5 max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-5xl">
            {text('services_heading', 'Keep your vehicle moving.')}
          </h1>
          <p className="mt-6 max-w-2xl text-base leading-relaxed text-brand-surface/70 sm:text-lg">
            {text('services_intro', 'From a quick part enquiry to workshop planning, TMS helps you choose the right next step for your vehicle.')}
          </p>
          {heroImage && <img src={heroImage.url} alt={heroImage.alt} className="mt-8 h-52 w-full max-w-2xl rounded-xl border border-white/15 object-cover" />}
        </div>
      </section>

      <section className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8 lg:py-16">
        <Reveal>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {services.map((service) => {
              const Icon = service.icon
              return (
                <article key={service.key} className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                  <span className="flex h-11 w-11 items-center justify-center rounded-full bg-brand-pale text-brand-green-deep">
                    <Icon className="h-5 w-5" />
                  </span>
                  <h2 className="mt-4 text-base font-black text-brand-ink">{text(`service_${service.key}_title`, service.title)}</h2>
                  <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">{text(`service_${service.key}_body`, service.description)}</p>
                </article>
              )
            })}
          </div>
        </Reveal>
      </section>

      <section className="border-y border-brand-ink/10 bg-white py-12 lg:py-16">
        <div className="mx-auto grid w-full max-w-7xl gap-8 px-5 sm:px-6 lg:grid-cols-[1fr_360px] lg:items-center lg:px-8">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">{text('services_enquiry_eyebrow', 'Workshop enquiries')}</p>
            <h2 className="mt-2 text-2xl font-black tracking-tight text-brand-ink sm:text-3xl">{text('services_enquiry_heading', 'Need help choosing a part or planning a visit?')}</h2>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-brand-ink/80">
              {text('services_enquiry_body', 'Send your vehicle details, the part number or a description of the issue. The team can help confirm availability and the best way to proceed.')}
            </p>
          </div>
          <div className="flex flex-col gap-3">
            <Link to="/contact" className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-brand-green-deep px-5 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
              Request assistance <ArrowRightIcon className="h-4 w-4" />
            </Link>
            <Link to="/products" className="inline-flex min-h-12 items-center justify-center rounded-full border border-brand-ink/20 px-5 text-sm font-bold text-brand-ink hover:bg-brand-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
              Browse products
            </Link>
          </div>
        </div>
      </section>
    </PublicShell>
  )
}
