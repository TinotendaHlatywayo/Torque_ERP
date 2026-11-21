import { useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { Link, useParams } from "react-router-dom"
import { ErrorState, LoadingState } from "@/components/feedback/StateComponents"
import { usePageMeta } from "@/hooks/usePageMeta"
import { PublicShell } from "@/components/layout/PublicShell"
import { BoxIcon } from "@/components/public/icons"
import { apiErrorMessage } from "@/lib/apiErrors"
import { getPublicPage, safePublicImageUrl, type CmsSection } from "@/lib/publicApi"

function SectionImage({ section }: { section: CmsSection }) {
  const [failed, setFailed] = useState(false)
  const imageUrl = safePublicImageUrl(section.image_url)

  if (!imageUrl || failed) {
    return (
      <div className="flex aspect-[16/9] w-full items-center justify-center rounded-xl bg-brand-surface text-brand-ink/50">
        <BoxIcon className="h-12 w-12" />
      </div>
    )
  }

  return (
    <img
      src={imageUrl}
      alt={section.alt ?? ""}
      loading="lazy"
      onError={() => setFailed(true)}
      className="aspect-[16/9] w-full rounded-xl object-cover"
    />
  )
}

export default function CmsPage() {
  const { slug = "" } = useParams()
  const pageQuery = useQuery({
    queryKey: ["public", "page", slug],
    queryFn: () => getPublicPage(slug),
    enabled: Boolean(slug),
  })

  const page = pageQuery.data?.page

  usePageMeta(
    page ? `${page.title} | Taziva Motor Spares` : "Taziva Motor Spares",
    "Learn more about Taziva Motor Spares, our catalogue and workshop support in Rusape.",
  )

  return (
    <PublicShell>
      <div className="mx-auto w-full max-w-5xl px-5 py-10 sm:px-6 lg:px-8 lg:py-14">
        <nav aria-label="Breadcrumb" className="text-sm text-brand-ink/70">
          <ol className="flex flex-wrap items-center gap-2">
            <li><Link to="/" className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Home</Link></li>
            <li aria-hidden="true">/</li>
            <li className="font-semibold text-brand-ink" aria-current="page">{page?.title ?? "Page"}</li>
          </ol>
        </nav>

        {pageQuery.isPending && <LoadingState text="Loading page..." />}

        {pageQuery.isError && (
          <div className="mt-8">
            <ErrorState
              title="Page not available"
              description={apiErrorMessage(pageQuery.error, "This page may not be published.")}
              retry={() => void pageQuery.refetch()}
            />
          </div>
        )}

        {page && (
          <>
            <header className="mt-8 border-b border-brand-ink/10 pb-8">
              <h1 className="text-3xl font-black tracking-tight text-brand-ink sm:text-4xl">{page.title}</h1>
            </header>

            <div className="space-y-10 py-10 sm:space-y-14">
              {page.sections.map((section, index) => {
                if (section.type === "hero") {
                  return (
                    <section key={`${section.type}-${index}`} className="overflow-hidden rounded-2xl bg-brand-ink text-white">
                      {section.image_url && <SectionImage section={section} />}
                      <div className="p-6 sm:p-10">
                        {section.heading && <h2 className="text-2xl font-black tracking-tight sm:text-3xl">{section.heading}</h2>}
                        {section.body && <p className="mt-4 whitespace-pre-line leading-relaxed text-brand-surface/70">{section.body}</p>}
                      </div>
                    </section>
                  )
                }

                if (section.type === "image") {
                  return (
                    <section key={`${section.type}-${index}`} className="space-y-4">
                      {section.heading && <h2 className="text-2xl font-black tracking-tight text-brand-ink">{section.heading}</h2>}
                      <SectionImage section={section} />
                      {section.body && <p className="text-sm leading-relaxed text-brand-ink/80">{section.body}</p>}
                    </section>
                  )
                }

                return (
                  <section key={`${section.type}-${index}`} className="max-w-3xl">
                    {section.heading && <h2 className="text-2xl font-black tracking-tight text-brand-ink">{section.heading}</h2>}
                    {section.body && <p className="mt-4 whitespace-pre-line leading-relaxed text-brand-ink">{section.body}</p>}
                  </section>
                )
              })}
            </div>
          </>
        )}
      </div>
    </PublicShell>
  )
}
