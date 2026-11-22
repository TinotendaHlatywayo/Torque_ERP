import { Fragment, useEffect, useMemo, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { Link, useNavigate } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { EmptyState, ErrorState, LoadingState } from "@/components/feedback/StateComponents"
import { useMe } from "@/hooks/useMe"
import api from "@/lib/api"
import { apiErrorMessage } from "@/lib/apiErrors"
import { storeBranchId } from "@/lib/branch"
import {
  exportPlatformBranchReport,
  fetchPlatformBranchReport,
  fetchPlatformBranches,
  impersonatePlatformBranch,
  registerPlatformBranch,
  updatePlatformBranchStatus,
  type PlatformBranch,
  type PlatformBranchFinance,
} from "@/lib/platform"

type StatusFilter = "all" | PlatformBranch["status"]
type StatusAction = "approve" | "suspend" | "activate" | "archive"
type StatusMutationVars = { branchId: string; action: StatusAction; branchName: string; reason?: string }

function formatMoney(minor: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: "currency", currency }).format(minor / 100)
  } catch {
    return `${currency} ${(minor / 100).toFixed(2)}`
  }
}

function formatDate(value: string | null): string {
  if (!value) {
    return "Not recorded"
  }

  try {
    return new Date(value).toLocaleDateString()
  } catch {
    return value
  }
}

function statusClass(status: string): string {
  if (status === "active") {
    return "rounded-full bg-brand-green/15 px-2.5 py-1 text-xs font-bold text-brand-green-deep dark:text-brand-mint"
  }
  if (status === "suspended") {
    return "rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-bold text-amber-700 dark:text-amber-300"
  }
  if (status === "pending") {
    return "rounded-full bg-sky-500/15 px-2.5 py-1 text-xs font-bold text-sky-700 dark:text-sky-300"
  }
  return "rounded-full bg-muted px-2.5 py-1 text-xs font-bold text-muted-foreground"
}

export default function BranchesPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const isAdmin = Boolean(meQuery.data?.user.is_platform_admin)
  const canViewBranches = isAdmin || (meQuery.data?.platform_permissions.includes("platform.branches.view") ?? false)
  const canManage = isAdmin || (meQuery.data?.platform_permissions.includes("platform.branches.manage") ?? false)
  const canManageDemoData = isAdmin || (meQuery.data?.platform_permissions.includes("platform.settings.manage") ?? false)
  const canViewReports = isAdmin || (meQuery.data?.platform_permissions.includes("platform.reports.view") ?? false)

  const [form, setForm] = useState({ name: "", code: "", slug: "", timezone: "Africa/Harare", currency_code: "USD" })
  const [showRegister, setShowRegister] = useState(false)
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all")
  const [sort, setSort] = useState<"name" | "revenue" | "cash">("name")
  const [asOf, setAsOf] = useState("")
  const [selectedBranchId, setSelectedBranchId] = useState<string | null>(null)
  const [pendingAction, setPendingAction] = useState<{ branchId: string; branchName: string } | null>(null)
  const [reason, setReason] = useState("")
  const [error, setError] = useState("")
  const [notice, setNotice] = useState("")

  const branchesQuery = useQuery({
    queryKey: ["platform", "branches"],
    queryFn: fetchPlatformBranches,
    enabled: canViewBranches,
  })

  const platformId = branchesQuery.data?.[0]?.platform_id

  const reportQuery = useQuery({
    queryKey: ["platform", "branch-report", platformId, asOf],
    queryFn: () => fetchPlatformBranchReport(platformId ?? "", asOf || undefined),
    enabled: canViewReports && Boolean(platformId),
  })

  useEffect(() => {
    if (!notice) {
      return
    }

    const timer = window.setTimeout(() => setNotice(""), 5000)
    return () => window.clearTimeout(timer)
  }, [notice])

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ["platform", "branches"] })
    void queryClient.invalidateQueries({ queryKey: ["platform", "branch-report"] })
  }

  const registerMutation = useMutation({
    mutationFn: registerPlatformBranch,
    onSuccess: () => {
      setForm({ name: "", code: "", slug: "", timezone: "Africa/Harare", currency_code: "USD" })
      setShowRegister(false)
      setError("")
      setNotice("Branch registered and sent for approval.")
      refresh()
    },
    onError: (err: unknown) => setError(apiErrorMessage(err, "Could not register the branch.")),
  })

  const statusMutation = useMutation({
    mutationFn: ({ branchId, action, reason }: StatusMutationVars) =>
      updatePlatformBranchStatus(branchId, action, reason),
    onSuccess: (_result, variables) => {
      setError("")
      setNotice(`${variables.action} completed for ${variables.branchName}.`)
      refresh()
    },
    onError: (err: unknown) => setError(apiErrorMessage(err, "Could not update the branch.")),
  })

  const exportMutation = useMutation({
    mutationFn: (reportAsOf: string) => exportPlatformBranchReport(platformId ?? "", reportAsOf || undefined),
    onSuccess: (result) => {
      setError("")
      setNotice("CSV export is ready. Download will start shortly.")
      window.location.assign(result.download_url)
    },
    onError: (err: unknown) => setError(apiErrorMessage(err, "Could not export the branch report.")),
  })

  const impersonationMutation = useMutation({
    mutationFn: impersonatePlatformBranch,
    onSuccess: async (_result, branchId) => {
      storeBranchId(branchId)
      queryClient.clear()
      await queryClient.invalidateQueries({ queryKey: ["me"] })
      navigate("/app", { replace: true })
    },
    onError: (err: unknown) => setError(apiErrorMessage(err, "Could not open that branch workspace.")),
  })

  const seedDemoMutation = useMutation({
    mutationFn: ({ branchId }: { branchId: string; branchName: string }) => api.post(`/api/branches/${branchId}/demo/seed`),
    onSuccess: (response, variables) => {
      setError("")
      setNotice(response.data.message || `Demo data seeded for ${variables.branchName}.`)
      refresh()
    },
    onError: (err: unknown) => setError(apiErrorMessage(err, "Could not seed demo data for that branch.")),
  })

  const branches = useMemo(() => branchesQuery.data ?? [], [branchesQuery.data])
  const report = reportQuery.data
  const financeByBranch = useMemo(() => {
    const entries = (report?.data ?? []).map((row): [string, PlatformBranchFinance] => [row.branch_id, row])
    return new Map(entries)
  }, [report])

  const filteredBranches = useMemo(() => {
    const query = search.trim().toLowerCase()

    return branches
      .filter((branch) => statusFilter === "all" || branch.status === statusFilter)
      .filter((branch) =>
        !query ||
        branch.name.toLowerCase().includes(query) ||
        branch.code.toLowerCase().includes(query) ||
        branch.slug.toLowerCase().includes(query),
      )
      .sort((left, right) => {
        if (sort === "revenue" || sort === "cash") {
          const field = sort === "revenue" ? "invoiced_revenue_minor" : "cash_position_minor"
          return (financeByBranch.get(right.id)?.[field] ?? 0) - (financeByBranch.get(left.id)?.[field] ?? 0)
        }

        return left.name.localeCompare(right.name)
      })
  }, [branches, financeByBranch, search, sort, statusFilter])

  if (meQuery.isLoading || branchesQuery.isLoading) {
    return (
      <div className="min-h-screen bg-background p-8">
        <LoadingState text="Loading platform console..." />
      </div>
    )
  }

  if (branchesQuery.isError) {
    return (
      <div className="min-h-screen bg-background p-8">
        <ErrorState
          description={apiErrorMessage(branchesQuery.error, "Could not load branches.")}
          retry={() => void branchesQuery.refetch()}
        />
      </div>
    )
  }

  const currency = report?.overall.currency_codes[0] ?? branches[0]?.currency_code ?? "USD"
  const activeCount = branches.filter((branch) => branch.status === "active").length
  const pendingCount = branches.filter((branch) => branch.status === "pending").length
  const suspendedCount = branches.filter((branch) => branch.status === "suspended").length

  const runStatusAction = (branch: PlatformBranch, action: StatusAction) => {
    setError("")

    if (action === "suspend") {
      setPendingAction({ branchId: branch.id, branchName: branch.name })
      setReason("Suspended from platform console")
      return
    }

    statusMutation.mutate({ branchId: branch.id, action, branchName: branch.name })
  }

  return (
    <div className="min-h-screen bg-background p-4 md:p-8">
      <div className="mx-auto max-w-7xl space-y-6">
        <div className="animate-fade-up flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">
              <Link className="hover:underline" to="/platform">Platform console</Link>
              <span aria-hidden="true">/</span>
              <span>Branches</span>
            </div>
            <h1 className="text-3xl font-black tracking-tight text-foreground">Branches and analytics</h1>
            <p className="max-w-2xl text-sm text-muted-foreground">
              Monitor branch health, compare finances, manage lifecycle and open an active workspace without signing in again.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={refresh} disabled={branchesQuery.isFetching || reportQuery.isFetching}>
              {branchesQuery.isFetching || reportQuery.isFetching ? "Refreshing..." : "Refresh"}
            </Button>
            {canViewReports && platformId && (
              <Button
                variant="outline"
                onClick={() => exportMutation.mutate(asOf)}
                disabled={exportMutation.isPending || reportQuery.isLoading}
              >
                {exportMutation.isPending ? "Preparing CSV..." : "Export CSV"}
              </Button>
            )}
            {canManage && (
              <Button
                className="bg-brand-green-deep text-white hover:bg-brand-green-deep/90"
                onClick={() => setShowRegister((value) => !value)}
                aria-expanded={showRegister}
              >
                {showRegister ? "Hide form" : "Register branch"}
              </Button>
            )}
          </div>
        </div>

        {notice && (
          <div role="status" aria-live="polite" className="rounded-lg border border-brand-green/30 bg-brand-pale p-3 text-sm font-semibold text-brand-green-deep dark:bg-brand-green/10 dark:text-brand-mint">
            {notice}
          </div>
        )}

        {error && (
          <div role="alert" className="rounded-lg bg-destructive/10 p-3 text-sm font-semibold text-destructive">
            {error}
          </div>
        )}

        <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {[
            ["Total branches", branches.length],
            ["Active", activeCount],
            ["Pending approval", pendingCount],
            ["Suspended", suspendedCount],
          ].map(([label, value]) => (
            <div key={String(label)} className="hover-lift rounded-lg border bg-card p-4 shadow-sm">
              <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground">{label}</dt>
              <dd className="mt-1 text-2xl font-black text-foreground">{String(value)}</dd>
            </div>
          ))}
        </dl>

        {pendingAction && (
          <Card className="border-amber-300 bg-amber-50/70 dark:border-amber-500/40 dark:bg-amber-500/10">
            <CardContent className="space-y-3 p-4">
              <p className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                Enter a reason to suspend <span className="font-black">{pendingAction.branchName}</span>.
              </p>
              <div className="flex flex-col gap-2 sm:flex-row">
                <Label className="sr-only" htmlFor="suspension-reason">Suspension reason</Label>
                <Input
                  id="suspension-reason"
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                  maxLength={500}
                  className="min-h-11"
                  placeholder="Reason shown to branch administrators"
                />
                <Button
                  className="min-h-11 bg-amber-700 text-white hover:bg-amber-700/90"
                  disabled={!reason.trim() || statusMutation.isPending}
                  onClick={() => {
                    statusMutation.mutate({
                      branchId: pendingAction.branchId,
                      action: "suspend",
                      branchName: pendingAction.branchName,
                      reason: reason.trim(),
                    })
                    setPendingAction(null)
                  }}
                >
                  Confirm suspension
                </Button>
                <Button className="min-h-11" variant="ghost" onClick={() => setPendingAction(null)}>Cancel</Button>
              </div>
            </CardContent>
          </Card>
        )}

        {canViewReports && report && (
          <Card className="overflow-hidden border-brand-ink/10">
            <div className="h-1 bg-gradient-to-r from-brand-green via-brand-mint to-brand-green-deep" aria-hidden="true" />
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <CardTitle className="text-lg font-black">Overall finances</CardTitle>
                <p className="text-xs text-muted-foreground">As of {report.overall.as_of} · {report.overall.branch_count} branches</p>
              </div>
              <div className="space-y-1">
                <Label htmlFor="report-as-of" className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Report date</Label>
                <Input
                  id="report-as-of"
                  type="date"
                  value={asOf}
                  onChange={(event) => setAsOf(event.target.value)}
                  className="min-h-11"
                />
              </div>
            </CardHeader>
            <CardContent>
              {report.overall.mixed_currency && (
                <p className="mb-4 rounded-md bg-amber-500/10 p-3 text-sm font-semibold text-amber-700 dark:text-amber-300">
                  Branches use different currencies. Totals are additive minor units and should be reviewed per currency.
                </p>
              )}
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  ["Invoiced revenue", report.overall.invoiced_revenue_minor],
                  ["Money in", report.overall.money_in_minor],
                  ["Money out", report.overall.money_out_minor],
                  ["Profit basis", report.overall.profit_basis_minor],
                  ["Cash position", report.overall.cash_position_minor],
                  ["Receivables", report.overall.money_owed_to_us_minor],
                  ["Bills to pay", report.overall.bills_to_pay_minor],
                  ["Inventory value", report.overall.inventory_value_minor],
                ].map(([label, value]) => (
                  <div key={String(label)} className="rounded-lg border bg-card p-4">
                    <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">{label}</p>
                    <p className="mt-2 text-xl font-black text-foreground">{formatMoney(Number(value), currency)}</p>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {canManage && showRegister && platformId && (
          <Card className="animate-fade-up border-brand-ink/10">
            <CardHeader>
              <CardTitle className="text-lg font-black">Register branch</CardTitle>
            </CardHeader>
            <CardContent>
              <form
                className="grid gap-4 md:grid-cols-2"
                onSubmit={(event) => {
                  event.preventDefault()
                  setError("")
                  registerMutation.mutate({ ...form, platform_id: platformId })
                }}
              >
                <div className="space-y-2">
                  <Label htmlFor="branch-name">Name</Label>
                  <Input id="branch-name" className="min-h-11" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-code">Code</Label>
                  <Input id="branch-code" className="min-h-11" required value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-slug">Slug</Label>
                  <Input id="branch-slug" className="min-h-11" required value={form.slug} onChange={(event) => setForm({ ...form, slug: event.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-timezone">Timezone</Label>
                  <Input id="branch-timezone" className="min-h-11" required value={form.timezone} onChange={(event) => setForm({ ...form, timezone: event.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-currency">Currency</Label>
                  <Input id="branch-currency" className="min-h-11" required maxLength={3} value={form.currency_code} onChange={(event) => setForm({ ...form, currency_code: event.target.value.toUpperCase() })} />
                </div>
                <div className="flex items-end">
                  <Button type="submit" className="min-h-11 w-full bg-brand-green-deep text-white hover:bg-brand-green-deep/90" disabled={registerMutation.isPending}>
                    {registerMutation.isPending ? "Registering..." : "Register branch"}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        <Card className="border-brand-ink/10">
          <CardHeader className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <CardTitle className="text-lg font-black">All branches</CardTitle>
              <p className="text-xs text-muted-foreground">{filteredBranches.length} of {branches.length} shown</p>
            </div>
            <div className="grid gap-2 sm:grid-cols-3">
              <div className="space-y-1">
                <Label htmlFor="branch-search" className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Search</Label>
                <Input
                  id="branch-search"
                  className="min-h-11"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Name, code or slug"
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="branch-status" className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Status</Label>
                <select
                  id="branch-status"
                  className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={statusFilter}
                  onChange={(event) => setStatusFilter(event.target.value as StatusFilter)}
                >
                  <option value="all">All statuses</option>
                  <option value="active">Active</option>
                  <option value="pending">Pending</option>
                  <option value="suspended">Suspended</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              <div className="space-y-1">
                <Label htmlFor="branch-sort" className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Sort by</Label>
                <select
                  id="branch-sort"
                  className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={sort}
                  onChange={(event) => setSort(event.target.value as "name" | "revenue" | "cash")}
                >
                  <option value="name">Name</option>
                  <option value="revenue">Revenue</option>
                  <option value="cash">Cash position</option>
                </select>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {filteredBranches.length === 0 ? (
              <EmptyState
                title="No matching branches"
                description="Try another search term or clear the status filter."
                action={
                  <Button
                    variant="outline"
                    onClick={() => {
                      setSearch("")
                      setStatusFilter("all")
                    }}
                  >
                    Clear filters
                  </Button>
                }
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-[1100px] w-full text-left text-sm">
                  <thead className="border-b text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                      <th scope="col" className="px-3 py-3">Branch</th>
                      <th scope="col" className="px-3 py-3">Status</th>
                      <th scope="col" className="px-3 py-3">Revenue</th>
                      <th scope="col" className="px-3 py-3">Cash</th>
                      <th scope="col" className="px-3 py-3">Receivables</th>
                      <th scope="col" className="px-3 py-3">Inventory</th>
                      <th scope="col" className="px-3 py-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {filteredBranches.map((branch) => {
                      const finance = financeByBranch.get(branch.id)
                      const branchCurrency = finance?.currency_code ?? branch.currency_code
                      const detailsOpen = selectedBranchId === branch.id

                      return (
                        <Fragment key={branch.id}>
                          <tr className="hover:bg-muted/40">
                            <td className="px-3 py-3">
                              <button
                                type="button"
                                className="text-left font-bold text-foreground hover:text-brand-green-deep hover:underline"
                                aria-expanded={detailsOpen}
                                aria-controls={`branch-details-${branch.id}`}
                                onClick={() => setSelectedBranchId(detailsOpen ? null : branch.id)}
                              >
                                {branch.name}
                              </button>
                              <p className="text-xs text-muted-foreground">{branch.code} · {branch.timezone}</p>
                            </td>
                            <td className="px-3 py-3">
                              <span className={statusClass(branch.status)}>{branch.status}</span>
                              {branch.suspension_reason && (
                                <p className="mt-1 max-w-[180px] truncate text-xs text-muted-foreground" title={branch.suspension_reason}>
                                  {branch.suspension_reason}
                                </p>
                              )}
                            </td>
                            <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.invoiced_revenue_minor, branchCurrency) : "—"}</td>
                            <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.cash_position_minor, branchCurrency) : "—"}</td>
                            <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.money_owed_to_us_minor, branchCurrency) : "—"}</td>
                            <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.inventory_value_minor, branchCurrency) : "—"}</td>
                            <td className="px-3 py-3">
                              <div className="flex flex-wrap gap-2">
                                {canManage && branch.status === "pending" && (
                                  <Button size="sm" disabled={statusMutation.isPending} onClick={() => runStatusAction(branch, "approve")}>Approve</Button>
                                )}
                                {canManage && branch.status === "active" && (
                                  <Button size="sm" variant="outline" disabled={statusMutation.isPending} onClick={() => runStatusAction(branch, "suspend")}>Suspend</Button>
                                )}
                                {canManage && (branch.status === "suspended" || branch.status === "pending") && (
                                  <Button size="sm" disabled={statusMutation.isPending} onClick={() => runStatusAction(branch, "activate")}>Activate</Button>
                                )}
                                {canManage && branch.status !== "archived" && (
                                  <Button size="sm" variant="ghost" disabled={statusMutation.isPending} onClick={() => runStatusAction(branch, "archive")}>Archive</Button>
                                )}
                                {canManageDemoData && branch.status === "active" && (
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={seedDemoMutation.isPending}
                                    onClick={() => seedDemoMutation.mutate({ branchId: branch.id, branchName: branch.name })}
                                  >
                                    Seed demo data
                                  </Button>
                                )}
                                {isAdmin && branch.status === "active" && (
                                  <Button
                                    size="sm"
                                    className="bg-brand-green-deep text-white hover:bg-brand-green-deep/90"
                                    disabled={impersonationMutation.isPending}
                                    onClick={() => impersonationMutation.mutate(branch.id)}
                                  >
                                    Open workspace
                                  </Button>
                                )}
                              </div>
                            </td>
                          </tr>
                          {detailsOpen && (
                            <tr id={`branch-details-${branch.id}`}>
                              <td colSpan={7} className="bg-muted/30 px-4 py-4">
                                <dl className="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Platform</dt><dd className="mt-1 font-semibold">{branch.platform ?? "—"}</dd></div>
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Slug</dt><dd className="mt-1 font-semibold">{branch.slug}</dd></div>
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Currency</dt><dd className="mt-1 font-semibold">{branch.currency_code}</dd></div>
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Host</dt><dd className="mt-1 font-semibold">{branch.host ?? "—"}</dd></div>
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Approved</dt><dd className="mt-1 font-semibold">{formatDate(branch.approved_at)}</dd></div>
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Approved by</dt><dd className="mt-1 font-semibold">{branch.approved_by ?? "—"}</dd></div>
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Suspended by</dt><dd className="mt-1 font-semibold">{branch.suspended_by ?? "—"}</dd></div>
                                  <div><dt className="font-bold uppercase tracking-wide text-muted-foreground">Created</dt><dd className="mt-1 font-semibold">{formatDate(branch.created_at)}</dd></div>
                                  <div className="sm:col-span-2 lg:col-span-4">
                                    <dt className="font-bold uppercase tracking-wide text-muted-foreground">Registration notes</dt>
                                    <dd className="mt-1 font-semibold">{branch.registration_notes ?? "No registration notes recorded."}</dd>
                                  </div>
                                </dl>
                              </td>
                            </tr>
                          )}
                        </Fragment>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
