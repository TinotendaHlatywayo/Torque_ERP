import { ChangeEvent, FormEvent, useEffect, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import api from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

type Section = { type: "hero" | "text" | "image"; heading?: string; body?: string; media_id?: string; alt?: string }
type Page = { id: string; title: string; slug: string; status: "draft" | "published"; current_version: number; published_version: number | null; sections?: Section[] }
type Media = { id: string; name: string; width: number; height: number; preferred_variant?: string | null; variants: { key: string; url: string; width: number; height: number; mime_type: string; bytes?: number | null; is_preferred?: boolean }[] }
type Version = { id: string; version: number; title: string; slug: string; is_published: boolean; created_at: string }
type SiteContentResponse = {
  content: Record<string, string | null>
  version: number
  published_version: number | null
  status: "draft" | "published"
  published_at: string | null
  updated_at: string | null
}

type SiteImageField = {
  key: string
  label: string
  altKey: string
  defaultUrl: string
  defaultAlt: string
  fit: "contain" | "cover"
}

type SitePageGroup = { label: string; fields: string[]; images: SiteImageField[] }

const heroSlideParts = ["brake pads", "an air filter", "an oil filter", "spark plugs", "a shock absorber", "an alternator", "a car battery", "a brake disc", "a fuel filter", "a radiator"]

const heroSlideImages: SiteImageField[] = heroSlideParts.map((part, index) => {
  const slide = index + 1
  return {
    key: slide === 1 ? "home_hero_media_id" : `home_hero_slide_${slide}_media_id`,
    label: `Home hero slide ${slide} - ${part}`,
    altKey: slide === 1 ? "home_hero_image_alt" : `home_hero_slide_${slide}_image_alt`,
    defaultUrl: `/images/site/hero-slide-${String(slide).padStart(2, "0")}.jpg`,
    defaultAlt: `Taziva Motor Spares ${part}`,
    fit: "cover",
  }
})

const sitePageGroups: SitePageGroup[] = [
  {
    label: "Global",
    fields: ["brand_name", "header_note", "header_promo", "customer_login_label", "team_login_label", "footer_about", "footer_copyright"],
    images: [
      { key: "logo_media_id", label: "Site logo", altKey: "logo_alt", defaultUrl: "/tms_logo_transparent.png", defaultAlt: "Taziva Motor Spares", fit: "contain" },
      { key: "favicon_media_id", label: "Favicon", altKey: "logo_alt", defaultUrl: "/tms_logo_transparent.png", defaultAlt: "Taziva Motor Spares", fit: "contain" },
    ],
  },
  {
    label: "Home",
    fields: ["home_meta_title", "home_meta_description", "home_eyebrow", "home_heading", "home_heading_accent", "home_intro", "home_search_title", "home_search_subtitle", "home_search_placeholder", "home_shop_button", "home_account_button", "home_benefit_one_title", "home_benefit_one_body", "home_benefit_two_title", "home_benefit_two_body", "home_benefit_three_title", "home_benefit_three_body", "home_browse_eyebrow", "home_browse_heading", "home_featured_eyebrow", "home_featured_heading"],
    images: heroSlideImages,
  },
  {
    label: "About",
    fields: ["about_meta_title", "about_meta_description", "about_eyebrow", "about_heading", "about_intro", "about_approach_eyebrow", "about_approach_heading", "about_approach_body_one", "about_approach_body_two", "about_next_heading"],
    images: [{ key: "about_media_id", label: "About image", altKey: "about_image_alt", defaultUrl: "/images/site/about.jpg", defaultAlt: "Taziva Motor Spares team and workshop", fit: "cover" }],
  },
  {
    label: "Services",
    fields: ["services_meta_title", "services_meta_description", "services_eyebrow", "services_heading", "services_intro", "service_one_title", "service_one_body", "service_two_title", "service_two_body", "service_three_title", "service_three_body", "service_four_title", "service_four_body", "service_five_title", "service_five_body", "service_six_title", "service_six_body", "services_enquiry_eyebrow", "services_enquiry_heading", "services_enquiry_body"],
    images: [{ key: "services_media_id", label: "Services image", altKey: "services_image_alt", defaultUrl: "/images/site/services.jpg", defaultAlt: "Motor spares and workshop support", fit: "cover" }],
  },
  {
    label: "Contact",
    fields: ["contact_meta_title", "contact_meta_description", "contact_eyebrow", "contact_heading", "contact_intro", "contact_location_title", "contact_location_body", "contact_hours_title", "contact_hours_body", "contact_phone_title", "contact_phone_body", "contact_email_title", "contact_email_body", "contact_form_heading", "contact_form_intro"],
    images: [{ key: "contact_media_id", label: "Contact image", altKey: "contact_image_alt", defaultUrl: "/images/site/contact.jpg", defaultAlt: "Contact Taziva Motor Spares", fit: "cover" }],
  },
  {
    label: "Catalogue, cart and account",
    fields: ["products_meta_title", "products_meta_description", "products_heading", "products_intro", "cart_meta_title", "cart_meta_description", "cart_heading", "account_meta_title", "account_meta_description", "account_heading", "login_meta_title", "login_meta_description", "signup_meta_title", "signup_meta_description"],
    images: [],
  },
]

function messageFrom(error: unknown) {
  if (typeof error === "object" && error && "response" in error) {
    const response = (error as { response?: { data?: { message?: string } } }).response
    return response?.data?.message || "The request could not be completed. Please review the form and try again."
  }
  return "The server could not be reached. Check the connection and try again."
}

export default function ContentPage() {
  const queryClient = useQueryClient()
  const [pages, setPages] = useState<Page[]>([])
  const [media, setMedia] = useState<Media[]>([])
  const [versions, setVersions] = useState<Version[]>([])
  const [selectedId, setSelectedId] = useState("")
  const [title, setTitle] = useState("")
  const [slug, setSlug] = useState("")
  const [publishedSlug, setPublishedSlug] = useState("")
  const [sections, setSections] = useState<Section[]>([{ type: "hero", heading: "", body: "" }])
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState("")
  const [error, setError] = useState("")
  const [view, setView] = useState<"pages" | "site">("pages")
  const [siteContent, setSiteContent] = useState<Record<string, string | null>>({})
  const [siteMeta, setSiteMeta] = useState<SiteContentResponse | null>(null)
  const [activeSitePage, setActiveSitePage] = useState(sitePageGroups[0].label)

  async function loadPages() {
    const { data } = await api.get<{ data: Page[] }>("/api/platform/cms/pages")
    setPages(data.data)
  }

  async function loadMedia() {
    const { data } = await api.get<{ data: Media[] }>("/api/platform/cms/media")
    setMedia(data.data)
  }

  async function loadSite() {
    const { data } = await api.get<{ data: SiteContentResponse }>("/api/platform/cms/site-content")
    setSiteContent(data.data.content)
    setSiteMeta(data.data)
  }

  useEffect(() => {
    Promise.all([loadPages(), loadMedia(), loadSite()]).catch((reason: unknown) => setError(messageFrom(reason)))
  }, [])

  async function selectPage(id: string) {
    setSelectedId(id)
    setError("")
    setNotice("")
    try {
      const [{ data: pageResponse }, { data: versionResponse }] = await Promise.all([
        api.get<{ data: Page }>(`/api/platform/cms/pages/${id}`),
        api.get<{ data: Version[] }>(`/api/platform/cms/pages/${id}/versions`),
      ])
      const page = pageResponse.data
      setTitle(page.title)
      setSlug(page.slug)
      setPublishedSlug(page.status === "published" ? page.slug : "")
      setSections(page.sections?.length ? page.sections : [{ type: "hero", heading: "", body: "" }])
      setVersions(versionResponse.data)
    } catch (reason) {
      setError(messageFrom(reason))
    }
  }

  function newPage() {
    setSelectedId("")
    setTitle("")
    setSlug("")
    setPublishedSlug("")
    setSections([{ type: "hero", heading: "", body: "" }])
    setVersions([])
    setError("")
    setNotice("")
  }

  function updateSection(index: number, changes: Partial<Section>) {
    setSections((current) => current.map((section, position) => position === index ? { ...section, ...changes } : section))
  }

  async function save(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError("")
    setNotice("")
    try {
      const payload = { title, slug, sections }
      const result = selectedId
        ? await api.put<{ data: Page }>(`/api/platform/cms/pages/${selectedId}`, payload)
        : await api.post<{ data: Page }>("/api/platform/cms/pages", payload)
      setSelectedId(result.data.data.id)
      setNotice("Draft saved as version " + result.data.data.current_version + ".")
      await Promise.all([loadPages(), selectPage(result.data.data.id)])
    } catch (reason) {
      setError(messageFrom(reason))
    } finally {
      setBusy(false)
    }
  }

  async function upload(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    if (!file) return
    const form = new FormData()
    form.append("image", file)
    setBusy(true)
    setError("")
    try {
      const { data } = await api.post<{ data: Media }>("/api/platform/cms/media", form, { headers: { "Content-Type": "multipart/form-data" } })
      setMedia((current) => [data.data, ...current])
      setSections((current) => {
        const emptyIndex = current.findIndex((section) => section.type === "image" && !section.media_id)
        if (emptyIndex < 0) return [...current, { type: "image", media_id: data.data.id, alt: file.name.replace(/\.[^.]+$/, "") }]
        return current.map((section, index) => index === emptyIndex
          ? { ...section, media_id: data.data.id, alt: file.name.replace(/\.[^.]+$/, "") }
          : section)
      })
      setNotice("Image uploaded and optimized. Add descriptive alt text before publishing.")
    } catch (reason) {
      setError(messageFrom(reason))
    } finally {
      setBusy(false)
      event.target.value = ""
    }
  }

  async function uploadSiteImage(event: ChangeEvent<HTMLInputElement>, field: SiteImageField) {
    const file = event.target.files?.[0]
    if (!file) return
    const form = new FormData()
    form.append("image", file)
    setBusy(true)
    setError("")
    try {
      const { data } = await api.post<{ data: Media }>("/api/platform/cms/media", form, { headers: { "Content-Type": "multipart/form-data" } })
      setMedia((current) => [data.data, ...current])
      setSiteContent((current) => {
        const description = current[field.altKey] || ""
        const seedDescription = !description || description === field.defaultAlt
        return {
          ...current,
          [field.key]: data.data.id,
          ...(seedDescription ? { [field.altKey]: file.name.replace(/\.[^.]+$/, "") } : {}),
        }
      })
      setNotice(`${field.label} uploaded. Review the image description, then save and publish.`)
    } catch (reason) {
      setError(messageFrom(reason))
    } finally {
      setBusy(false)
      event.target.value = ""
    }
  }

  async function saveSite(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError("")
    setNotice("")
    try {
      const { data } = await api.put<{ data: SiteContentResponse }>("/api/platform/cms/site-content", { content: siteContent })
      setSiteContent(data.data.content)
      setSiteMeta(data.data)
      setNotice(`Site content draft saved as version ${data.data.version}.`)
    } catch (reason) {
      setError(messageFrom(reason))
    } finally {
      setBusy(false)
    }
  }

  async function publishSite() {
    setBusy(true)
    setError("")
    try {
      const { data } = await api.post<{ data: SiteContentResponse }>("/api/platform/cms/site-content/publish")
      setSiteContent(data.data.content)
      setSiteMeta(data.data)
      await queryClient.invalidateQueries({ queryKey: ['public', 'site'] })
      setNotice(`Site content version ${data.data.published_version} is now live.`)
    } catch (reason) {
      setError(messageFrom(reason))
    } finally {
      setBusy(false)
    }
  }

  async function publish() {
    if (!selectedId) return
    setBusy(true)
    setError("")
    try {
      const { data } = await api.post<{ data: Page }>(`/api/platform/cms/pages/${selectedId}/publish`)
      setPublishedSlug(data.data.slug)
      setNotice(`Version ${data.data.published_version} is now live at /pages/${data.data.slug}.`)
      await Promise.all([loadPages(), selectPage(selectedId)])
    } catch (reason) {
      setError(messageFrom(reason))
    } finally {
      setBusy(false)
    }
  }

  async function rollback(version: Version) {
    if (!selectedId || !window.confirm(`Restore version ${version.version} as a new draft? The live page will remain unchanged until you publish.`)) return
    setBusy(true)
    setError("")
    try {
      await api.post(`/api/platform/cms/pages/${selectedId}/rollback/${version.id}`)
      setNotice(`Version ${version.version} restored as a draft. Review it and publish when ready.`)
      await Promise.all([loadPages(), selectPage(selectedId)])
    } catch (reason) {
      setError(messageFrom(reason))
    } finally {
      setBusy(false)
    }
  }

  const mediaUrl = (id?: string) => {
    const item = media.find((candidate) => candidate.id === id)
    return item?.variants.find((variant) => variant.is_preferred)?.url || item?.variants.find((variant) => variant.key === "480_webp")?.url || ""
  }

  const siteImagePreviewUrl = (field: SiteImageField) => mediaUrl(siteContent[field.key] || undefined) || field.defaultUrl
  const activeSiteGroup = sitePageGroups.find((group) => group.label === activeSitePage) ?? sitePageGroups[0]

  return (
    <div className="min-h-screen bg-muted/30 px-4 py-8 md:px-8">
      <div className="mx-auto max-w-7xl space-y-6">
        <header className="flex flex-wrap items-end justify-between gap-4">
          <div><p className="text-sm font-semibold uppercase tracking-[.16em] text-primary">Website</p><h1 className="mt-2 text-3xl font-bold tracking-tight">Content studio</h1><p className="mt-2 max-w-2xl text-muted-foreground">Manage shared site content and custom pages. Publish only when every change is ready.</p></div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant={view === "pages" ? "default" : "outline"} onClick={() => setView("pages")}>Custom pages</Button>
            <Button type="button" variant={view === "site" ? "default" : "outline"} onClick={() => setView("site")}>Site pages</Button>
            {view === "pages" && <Button type="button" variant="outline" onClick={newPage}>New page</Button>}
          </div>
        </header>
        {notice && <p role="status" className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{notice}</p>}
        {error && <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</p>}
        {view === "site" && <form onSubmit={saveSite} className="space-y-6">
          <Card>
            <CardHeader><CardTitle className="text-base">Site pages</CardTitle><p className="text-sm text-muted-foreground">Choose a page to edit its text and shared images. Save a draft, then publish to update the live website.</p></CardHeader>
            <CardContent className="flex flex-wrap gap-2">
              {sitePageGroups.map((group) => <Button key={group.label} type="button" variant={activeSitePage === group.label ? "default" : "outline"} onClick={() => setActiveSitePage(group.label)}>{group.label}</Button>)}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{activeSiteGroup.label}</CardTitle></CardHeader>
            <CardContent className="space-y-8">
              {activeSiteGroup.images.length > 0 && <section className="space-y-4">
                <div><h3 className="text-sm font-semibold">Shared site images</h3><p className="text-xs text-muted-foreground">Upload a replacement or choose “Use default” to restore the branded placeholder.</p></div>
                <div className="grid gap-4 md:grid-cols-2">
                  {activeSiteGroup.images.map((field) => {
                    const imageId = siteContent[field.key] || ""
                    const altValue = siteContent[field.altKey] || ""
                    return <div key={field.key} className="space-y-3 rounded-lg border p-4">
                      <Label htmlFor={`site-image-${field.key}`}>{field.label}</Label>
                      <div className="flex h-32 items-center justify-center overflow-hidden rounded-md bg-muted">
                        <img src={siteImagePreviewUrl(field)} alt={altValue || field.defaultAlt} className={`max-h-full max-w-full ${field.fit === "contain" ? "object-contain" : "h-full w-full object-cover"}`} />
                      </div>
                      <select id={`site-image-${field.key}`} value={imageId} onChange={(event) => setSiteContent((current) => ({ ...current, [field.key]: event.target.value || null }))} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Use default</option>
                        {media.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                      </select>
                      <div className="space-y-2">
                        <Label htmlFor={`site-upload-${field.key}`}>Replace image</Label>
                        <input id={`site-upload-${field.key}`} type="file" accept="image/jpeg,image/png,image/webp" disabled={busy} onChange={(event) => void uploadSiteImage(event, field)} className="block w-full text-sm text-muted-foreground file:mr-3 file:min-h-10 file:cursor-pointer file:rounded-md file:border file:border-input file:bg-background file:px-3 file:text-sm file:font-medium" />
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor={`site-alt-${field.key}`}>Image description</Label>
                        <Input id={`site-alt-${field.key}`} value={altValue} onChange={(event) => setSiteContent((current) => ({ ...current, [field.altKey]: event.target.value }))} maxLength={250} />
                      </div>
                    </div>
                  })}
                </div>
              </section>}
              <section className="space-y-4">
                <div><h3 className="text-sm font-semibold">Page text</h3><p className="text-xs text-muted-foreground">Every label on this page can be changed here.</p></div>
                <div className="grid gap-4 md:grid-cols-2">
                  {activeSiteGroup.fields.map((key) => <div key={key} className="space-y-2">
                    <Label htmlFor={`site-${key}`}>{key.replace(/_/g, " ").replace(/\b\w/g, (character) => character.toUpperCase())}</Label>
                    {key.includes("_description") || key.endsWith("_body") || key.endsWith("_about") || key.endsWith("_intro") ? <textarea id={`site-${key}`} value={siteContent[key] || ""} onChange={(event) => setSiteContent((current) => ({ ...current, [key]: event.target.value }))} rows={3} maxLength={5000} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" /> : <Input id={`site-${key}`} value={siteContent[key] || ""} onChange={(event) => setSiteContent((current) => ({ ...current, [key]: event.target.value }))} maxLength={5000} />}
                  </div>)}
                </div>
              </section>
            </CardContent>
          </Card>
          <div className="flex flex-wrap items-center gap-3">
            <Button type="submit" disabled={busy}>{busy ? "Saving…" : "Save site draft"}</Button>
            <Button type="button" variant="outline" onClick={() => void publishSite()} disabled={busy || !siteMeta || siteMeta.version === 0}>Publish site content</Button>
            {siteMeta && <p className="text-sm text-muted-foreground">Draft v{siteMeta.version} · {siteMeta.status === "published" ? `Live v${siteMeta.published_version}` : "Not published"}</p>}
          </div>
        </form>}
        {view === "pages" && <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
          <Card className="h-fit">
            <CardHeader><CardTitle className="text-base">Pages <span className="ml-1 text-sm font-normal text-muted-foreground">{pages.length}</span></CardTitle></CardHeader>
            <CardContent className="space-y-2">
              {pages.length === 0 ? <p className="text-sm text-muted-foreground">Your pages will appear here.</p> : pages.map((page) => <button key={page.id} type="button" onClick={() => void selectPage(page.id)} className={`w-full rounded-md border p-3 text-left transition hover:border-primary/60 ${selectedId === page.id ? "border-primary bg-primary/5" : "border-border"}`}><span className="block truncate text-sm font-semibold">{page.title}</span><span className="mt-1 flex items-center justify-between gap-2 text-xs text-muted-foreground"><span>/{page.slug}</span><span className="rounded-full bg-secondary px-2 py-0.5 capitalize">{page.status}</span></span></button>)}
            </CardContent>
          </Card>

          <div className="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(280px,.8fr)]">
            <Card>
              <CardHeader><CardTitle className="text-base">{selectedId ? "Edit page" : "Create a page"}</CardTitle></CardHeader>
              <CardContent>
                <form onSubmit={save} className="space-y-5">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2"><Label htmlFor="page-title">Page title</Label><Input id="page-title" value={title} maxLength={120} onChange={(event) => setTitle(event.target.value)} required /></div>
                    <div className="space-y-2"><Label htmlFor="page-slug">URL slug</Label><div className="flex items-center gap-2"><span className="text-sm text-muted-foreground">/</span><Input id="page-slug" value={slug} maxLength={160} pattern="[a-z0-9]+(?:-[a-z0-9]+)*" onChange={(event) => setSlug(event.target.value)} required /></div></div>
                  </div>
                  <div className="space-y-3">
                    {sections.map((section, index) => <fieldset key={index} className="space-y-3 rounded-lg border p-4">
                      <legend className="px-1 text-sm font-semibold capitalize">{section.type === "hero" ? "Hero section" : section.type === "text" ? "Text section" : "Image section"}</legend>
                      {section.type !== "image" ? <>
                        <div className="space-y-2"><Label htmlFor={`heading-${index}`}>{section.type === "hero" ? "Main heading" : "Section heading"}</Label><Input id={`heading-${index}`} value={section.heading || ""} onChange={(event) => updateSection(index, { heading: event.target.value })} maxLength={180} /></div>
                        <div className="space-y-2"><Label htmlFor={`body-${index}`}>Text</Label><textarea id={`body-${index}`} value={section.body || ""} onChange={(event) => updateSection(index, { body: event.target.value })} maxLength={5000} rows={4} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" /></div>
                      </> : <>
                        {mediaUrl(section.media_id) ? <img src={mediaUrl(section.media_id)} alt="Selected page media preview" className="max-h-48 w-full rounded-md object-cover" /> : <p className="text-sm text-muted-foreground">Upload an image to add it to this section.</p>}
                        <div className="space-y-2"><Label htmlFor={`media-${index}`}>Library image</Label><select id={`media-${index}`} value={section.media_id || ""} onChange={(event) => updateSection(index, { media_id: event.target.value })} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="">Choose an image</option>{media.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.width}×{item.height}</option>)}</select></div>
                        <div className="space-y-2"><Label htmlFor={`alt-${index}`}>Image description (alt text)</Label><Input id={`alt-${index}`} value={section.alt || ""} onChange={(event) => updateSection(index, { alt: event.target.value })} maxLength={250} required /></div>
                      </>}
                      {sections.length > 1 && <Button type="button" variant="outline" size="sm" onClick={() => setSections((current) => current.filter((_, position) => position !== index))}>Remove section</Button>}
                    </fieldset>)}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" onClick={() => setSections((current) => [...current, { type: "text", heading: "", body: "" }])}>Add text section</Button>
                    <Button type="button" variant="outline" onClick={() => setSections((current) => [...current, { type: "image", alt: "" }])}>Add image section</Button>
                    <Label htmlFor="page-image-upload" className="inline-flex h-10 cursor-pointer items-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-sm hover:bg-accent">Upload image</Label>
                    <input id="page-image-upload" type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => void upload(event)} />
                  </div>
                  <div className="flex flex-wrap items-center gap-3 border-t pt-5">
                    <Button type="submit" disabled={busy}>{busy ? "Saving…" : "Save draft"}</Button>
                    {selectedId && <Button type="button" variant="outline" onClick={() => void publish()} disabled={busy}>Publish current draft</Button>}
                    {selectedId && publishedSlug && <a className="text-sm font-semibold text-primary underline-offset-4 hover:underline" href={`/pages/${publishedSlug}`} target="_blank" rel="noreferrer">View live page <span aria-hidden="true">↗</span></a>}
                  </div>
                </form>
              </CardContent>
            </Card>

            <div className="space-y-6">
              <Card><CardHeader><CardTitle className="text-base">Live preview</CardTitle></CardHeader><CardContent className="space-y-4">
                <div className="rounded-md bg-[#f5f3ef] p-5"><p className="text-xs font-semibold uppercase tracking-widest text-primary">Taziva Motor Spares · Rusape</p><h2 className="mt-3 text-2xl font-bold tracking-tight">{sections.find((section) => section.type === "hero")?.heading || title || "Your page heading"}</h2>{sections.find((section) => section.type === "hero")?.body && <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{sections.find((section) => section.type === "hero")?.body}</p>}</div>
                {sections.filter((section) => section.type !== "hero").map((section, index) => section.type === "text" ? <div key={index}><h3 className="font-semibold">{section.heading || "Section heading"}</h3><p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">{section.body}</p></div> : <div key={index}>{mediaUrl(section.media_id) && <img src={mediaUrl(section.media_id)} alt={section.alt || "Preview image"} className="max-h-48 w-full rounded-md object-cover" />}</div>)}
              </CardContent></Card>
              {selectedId && <Card><CardHeader><CardTitle className="text-base">Version history</CardTitle></CardHeader><CardContent className="space-y-2">{versions.length === 0 ? <p className="text-sm text-muted-foreground">Versions appear after the first save.</p> : versions.map((version) => <div key={version.id} className="flex items-center justify-between gap-3 rounded-md border p-3"><div><p className="text-sm font-semibold">Version {version.version}{version.is_published && <span className="ml-2 text-xs font-normal text-emerald-700">Live</span>}</p><p className="text-xs text-muted-foreground">{new Date(version.created_at).toLocaleString()}</p></div><Button type="button" size="sm" variant="outline" onClick={() => void rollback(version)} disabled={busy}>Restore</Button></div>)}</CardContent></Card>}
              <Card><CardHeader><CardTitle className="text-base">Media library</CardTitle></CardHeader><CardContent className="space-y-3">{media.length === 0 ? <p className="text-sm text-muted-foreground">Upload an image while editing a page to add it here.</p> : media.slice(0, 12).map((item) => <div key={item.id} className="flex items-center gap-3 rounded-md border p-2">{item.variants[0] && <img src={item.variants.find((variant) => variant.key === "480_webp")?.url || item.variants[0].url} alt="" className="h-12 w-12 rounded object-cover" />}<div className="min-w-0"><p className="truncate text-xs font-medium">{item.name}</p><p className="select-all break-all font-mono text-[10px] text-muted-foreground">{item.id}</p><p className="text-[10px] text-muted-foreground">Copy this ID to use the image on a product.</p></div></div>)}</CardContent></Card>
            </div>
          </div>
        </div>}
      </div>
    </div>
  )
}
