import { Link } from "react-router-dom"
import { useQuery } from "@tanstack/react-query"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useMe } from "@/hooks/useMe"
import { fetchPlatformBranchReport, fetchPlatformBranches } from "@/lib/platform"

function formatMoney(minor: number | undefined, currency: string): string {
  if (minor === undefined) {
    return "—"
  }

  try {
    return new Intl.NumberFormat(undefined, { style: "currency", currency }).format(minor / 100)
  } catch {
    return `${currency} ${(minor / 100).toFixed(2)}`
  }
}

function statusClass(status: string): string {
  if (status === "active") {
    return "rounded-full bg-brand-green/15 px-2 py-0.5 text-xs font-bold text-brand-green-deep dark:text-brand-mint"
  }
  if (status === "pending") {
    return "rounded-full bg-sky-500/15 px-2 py-0.5 text-xs font-bold text-sky-700 dark:text-sky-300"
  }
  if (status === "suspended") {
    return "rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-300"
  }
  return "rounded-full bg-muted px-2 py-0.5 text-xs font-bold text-muted-foreground"
}

export default function PlatformLandingPage() {
  const { data: me } = useMe()
  const isAdmin = Boolean(me?.user.is_platform_admin)
  const canViewBranches = isAdmin || (me?.platform_permissions.includes("platform.branches.view") ?? false)
  const canViewAudit = isAdmin || (me?.platform_permissions.includes("platform.audit.view") ?? false)
  const canViewReports = isAdmin || (me?.platform_permissions.includes("platform.reports.view") ?? false)

  const branchesQuery = useQuery({
    queryKey: ["platform", "branches"],
    queryFn: fetchPlatformBranches,
    enabled: canViewBranches,
  })

  const platformId = branchesQuery.data?.[0]?.platform_id
  const reportQuery = useQuery({
    queryKey: ["platform", "branch-report", platformId],
    queryFn: () => fetchPlatformBranchReport(platformId ?? ""),
    enabled: canViewReports && Boolean(platformId),
  })

  const branches = branchesQuery.data ?? []
  const activeCount = branches.filter((branch) => branch.status === "active").length
  const currency = reportQuery.data?.overall.currency_codes[0] ?? "USD"
  const firstName = me?.user.name?.split(" ")[0] ?? "admin"

  const actions = [
    {
      title: "Branches and analytics",
      description: "Manage lifecycle, compare branch finances and open any active workspace.",
      to: "/platform/branches",
      linkLabel: "Open branch console",
      visible: canViewBranches,
    },
    {
      title: "Public website",
      description: "Prepare images, edit shared copy and publish approved public pages.",
      to: "/platform/content",
      linkLabel: "Open content studio",
      visible: true,
    },
    {
      title: "Audit log",
      description: "Review platform-level and cross-branch activity recorded by the system.",
      to: "/platform/audit",
      linkLabel: "Open audit log",
      visible: canViewAudit,
    },
  ].filter((action) => action.visible)

  return (
    <div className="min-h-screen bg-background p-4 md:p-8">
      <div className="mx-auto max-w-6xl space-y-6">
        <section className="animate-fade-up relative overflow-hidden rounded-xl border bg-card p-6 shadow-sm md:p-8">
          <div className="grid-texture absolute inset-0 bg-brand-green-deep/95" aria-hidden="true" />
          <div className="relative max-w-2xl space-y-4 text-white">
            <p className="text-xs font-bold uppercase tracking-[0.25em] text-brand-mint">Platform console</p>
            <h1 className="text-3xl font-black tracking-tight md:text-4xl">Welcome back, {firstName}.</h1>
            <p className="text-sm text-white/80">
              One place to monitor every branch, manage the public storefront and review platform activity. Branch isolation stays enforced while you work.
            </p>
            <div className="flex flex-wrap gap-2 pt-2">
              <Link
                to="/platform/branches"
                className="inline-flex min-h-11 items-center rounded-md bg-white px-4 text-sm font-bold text-brand-green-deep transition-colors hover:bg-brand-pale focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
              >
                Open branch console <span className="ml-2" aria-hidden="true">→</span>
              </Link>
              <Link
                to="/platform/content"
                className="inline-flex min-h-11 items-center rounded-md border border-white/40 px-4 text-sm font-bold text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
              >
                Content studio
              </Link>
            </div>
          </div>
        </section>

        <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {[
            ["Total branches", canViewBranches ? branches.length : "—"],
            ["Active branches", canViewBranches ? activeCount : "—"],
            ["Invoiced revenue", formatMoney(reportQuery.data?.overall.invoiced_revenue_minor, currency)],
            ["Cash position", formatMoney(reportQuery.data?.overall.cash_position_minor, currency)],
          ].map(([label, value]) => (
            <div key={String(label)} className="hover-lift rounded-lg border bg-card p-4 shadow-sm">
              <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground">{label}</dt>
              <dd className="mt-1 text-xl font-black text-foreground">{value}</dd>
            </div>
          ))}
        </dl>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {actions.map((action) => (
            <Card key={action.title} className="hover-lift border-brand-ink/10">
              <CardHeader>
                <CardTitle className="text-lg font-black">{action.title}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">{action.description}</p>
                <Link
                  to={action.to}
                  className="inline-flex min-h-11 items-center rounded-md bg-brand-green-deep px-4 text-sm font-bold text-white transition-colors hover:bg-brand-green-deep/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  {action.linkLabel} <span className="ml-2" aria-hidden="true">→</span>
                </Link>
              </CardContent>
            </Card>
          ))}
        </div>

        {canViewBranches && (
          <Card className="border-brand-ink/10">
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="text-lg font-black">Branch snapshot</CardTitle>
              <Link className="text-sm font-bold text-brand-green-deep hover:underline" to="/platform/branches">View all</Link>
            </CardHeader>
            <CardContent>
              {branches.length === 0 ? (
                <p className="text-sm text-muted-foreground">No branches registered yet.</p>
              ) : (
                <ul className="divide-y">
                  {branches.slice(0, 4).map((branch) => {
                    const finance = reportQuery.data?.data.find((row) => row.branch_id === branch.id)
                    return (
                      <li key={branch.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                          <p className="font-bold">{branch.name}</p>
                          <p className="text-xs text-muted-foreground">{branch.code} · {branch.currency_code}</p>
                        </div>
                        <div className="flex items-center gap-3">
                          <span className={statusClass(branch.status)}>{branch.status}</span>
                          <span className="text-sm font-bold">{formatMoney(finance?.invoiced_revenue_minor, branch.currency_code)}</span>
                        </div>
                      </li>
                    )
                  })}
                </ul>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}
