import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { PublicShell } from '@/components/layout/PublicShell'
import { ArrowRightIcon, ClockIcon, MailIcon, MapIcon, PhoneIcon } from '@/components/public/icons'
import { Reveal } from '@/components/ui/Reveal'
import { usePageMeta } from '@/hooks/usePageMeta'
import { getPublicSite } from '@/lib/publicApi'

const contactEmail = (import.meta.env.VITE_PUBLIC_CONTACT_EMAIL as string | undefined)?.trim()

export default function ContactPage() {
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [vehicle, setVehicle] = useState('')
  const [message, setMessage] = useState('')
  const [prepared, setPrepared] = useState('')
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const content = siteQuery.data?.content
  const text = (key: string, fallback: string) => content?.text[key] || fallback
  const heroImage = content?.images.contact_media_id

  usePageMeta(
    text('contact_meta_title', 'Contact Us | Taziva Motor Spares'),
    text('contact_meta_description', 'Contact Taziva Motor Spares for fitment help, parts enquiries and workshop support in Rusape.'),
  )

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const lines = [
      'TMS enquiry',
      name.trim() ? `Name: ${name.trim()}` : '',
      phone.trim() ? `Phone: ${phone.trim()}` : '',
      email.trim() ? `Email: ${email.trim()}` : '',
      vehicle.trim() ? `Vehicle: ${vehicle.trim()}` : '',
      message.trim() ? `Message: ${message.trim()}` : '',
    ].filter(Boolean)
    const body = lines.join('\n')
    setPrepared(body)

    if (contactEmail) {
      const subject = encodeURIComponent('TMS parts enquiry')
      window.location.href = `mailto:${contactEmail}?subject=${subject}&body=${encodeURIComponent(body)}`
    }
  }

  return (
    <PublicShell>
      <section className="relative overflow-hidden bg-brand-ink text-white">
        <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
        <div className="relative mx-auto w-full max-w-7xl px-5 py-14 sm:px-6 lg:px-8 lg:py-20">
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-brand-mint">{text('contact_eyebrow', 'Contact us')}</p>
          <h1 className="mt-5 max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-5xl">
            {text('contact_heading', 'Tell us what you need.')}
          </h1>
          <p className="mt-6 max-w-2xl text-base leading-relaxed text-brand-surface/70 sm:text-lg">
            {text('contact_intro', 'Send a part number, vehicle detail or service question. We will help you find the right next step.')}
          </p>
          {heroImage && <img src={heroImage.url} alt={heroImage.alt} className="mt-8 h-52 w-full max-w-2xl rounded-xl border border-white/15 object-cover" />}
        </div>
      </section>

      <section className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8 lg:py-16">
        <Reveal>
          <div className="grid gap-8 lg:grid-cols-[360px_1fr] lg:items-start">
            <div className="space-y-4">
              <article className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <MapIcon className="h-6 w-6 text-brand-green-deep" />
                <h2 className="mt-3 text-sm font-black text-brand-ink">{text('contact_location_title', 'Location')}</h2>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">
                  {text('contact_location_body', `${siteQuery.data?.branch.name ?? 'Taziva Motor Spares'}, Rusape, Zimbabwe. Confirm the exact shop address with the team.`)}
                </p>
              </article>
              <article className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <ClockIcon className="h-6 w-6 text-brand-green-deep" />
                <h2 className="mt-3 text-sm font-black text-brand-ink">{text('contact_hours_title', 'Opening hours')}</h2>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">{text('contact_hours_body', "Contact the team to confirm today's hours and collection times.")}</p>
              </article>
              <article className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <PhoneIcon className="h-6 w-6 text-brand-green-deep" />
                <h2 className="mt-3 text-sm font-black text-brand-ink">{text('contact_phone_title', 'Phone and WhatsApp')}</h2>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">{text('contact_phone_body', 'Use the enquiry form below and include the best number to reach you.')}</p>
              </article>
              <article className="rounded-xl border border-brand-ink/10 bg-white p-6 shadow-sm">
                <MailIcon className="h-6 w-6 text-brand-green-deep" />
                <h2 className="mt-3 text-sm font-black text-brand-ink">{text('contact_email_title', 'Email')}</h2>
                <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">
                  {contactEmail
                    ? text('contact_email_body', `Enquiries open in your email app to ${contactEmail}.`).replace('{email}', contactEmail)
                    : text('contact_email_body', 'No public contact email is configured yet. The form will prepare your message locally.')}
                </p>
              </article>
            </div>

            <div className="rounded-2xl border border-brand-ink/10 bg-white p-6 shadow-sm sm:p-8">
              <h2 className="text-xl font-black text-brand-ink">{text('contact_form_heading', 'Parts and service enquiry')}</h2>
              <p className="mt-2 text-sm leading-relaxed text-brand-ink/70">
                {text('contact_form_intro', 'Add as much detail as you can: make, model, year, part number or a photo reference.')}
              </p>
              <form className="mt-6 grid gap-4 sm:grid-cols-2" onSubmit={submit}>
                <div className="space-y-2">
                  <label htmlFor="contact-name" className="text-sm font-bold text-brand-ink">Your name</label>
                  <input id="contact-name" value={name} onChange={(event) => setName(event.target.value)} className="h-11 w-full rounded-md border border-brand-ink/20 px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green" />
                </div>
                <div className="space-y-2">
                  <label htmlFor="contact-phone" className="text-sm font-bold text-brand-ink">Phone / WhatsApp</label>
                  <input id="contact-phone" value={phone} onChange={(event) => setPhone(event.target.value)} className="h-11 w-full rounded-md border border-brand-ink/20 px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green" />
                </div>
                <div className="space-y-2">
                  <label htmlFor="contact-email" className="text-sm font-bold text-brand-ink">Email</label>
                  <input id="contact-email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} className="h-11 w-full rounded-md border border-brand-ink/20 px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green" />
                </div>
                <div className="space-y-2">
                  <label htmlFor="contact-vehicle" className="text-sm font-bold text-brand-ink">Vehicle details</label>
                  <input id="contact-vehicle" value={vehicle} onChange={(event) => setVehicle(event.target.value)} placeholder="Make, model, year" className="h-11 w-full rounded-md border border-brand-ink/20 px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green" />
                </div>
                <div className="space-y-2 sm:col-span-2">
                  <label htmlFor="contact-message" className="text-sm font-bold text-brand-ink">Message</label>
                  <textarea id="contact-message" value={message} onChange={(event) => setMessage(event.target.value)} rows={5} required className="w-full rounded-md border border-brand-ink/20 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green" placeholder="Part needed, part number, service request or question" />
                </div>
                <div className="sm:col-span-2">
                  <button type="submit" className="inline-flex min-h-12 items-center gap-2 rounded-full bg-brand-green-deep px-6 text-sm font-bold text-white hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2">
                    Prepare enquiry <ArrowRightIcon className="h-4 w-4" />
                  </button>
                </div>
              </form>

              {prepared && (
                <div className="mt-6 rounded-lg bg-brand-pale p-4" role="status">
                  <p className="text-sm font-bold text-brand-ink">Your enquiry is ready.</p>
                  <p className="mt-1 text-xs leading-relaxed text-brand-ink/70">
                    {contactEmail ? 'Your email app should open with this message. If it does not, copy the text below.' : 'Copy this message and send it to the branch contact channel.'}
                  </p>
                  <textarea readOnly value={prepared} rows={6} aria-label="Prepared enquiry message" className="mt-3 w-full rounded-md border border-brand-green/20 bg-white p-3 text-xs leading-relaxed text-brand-ink" />
                </div>
              )}

              <p className="mt-6 text-xs text-brand-ink/60">
                Looking for stock instead? <Link to="/products" className="font-bold text-brand-green-deep hover:underline">Browse products</Link>.
              </p>
            </div>
          </div>
        </Reveal>
      </section>
    </PublicShell>
  )
}
