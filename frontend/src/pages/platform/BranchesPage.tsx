import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useNavigate } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { EmptyState, ErrorState, LoadingState } from "@/components/feedback/StateComponents"
import { useMe } from "@/hooks/useMe"
import { apiErrorMessage } from "@/lib/apiErrors"
import { storeBranchId } from "@/lib/branch"
import {
  fetchPlatformBranchReport,
  fetchPlatformBranches,
  impersonatePlatformBranch,
  registerPlatformBranch,
  updatePlatformBranchStatus,
} from "@/lib/platform"

function formatMoney(minor: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: "currency", currency }).format(minor / 100)
  } catch {
    return `${currency} ${(minor / 100).toFixed(2)}`
  }
}

function statusClass(status: string): string {
  if (status === "active") {
    return "rounded-full bg-brand-green/15 px-2.5 py-1 text-xs font-bold text-brand-green-deep"
  }
  if (status === "suspended") {
    return "rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-bold text-amber-700"
  }
  if (status === "pending") {
    return "rounded-full bg-sky-500/15 px-2.5 py-1 text-xs font-bold text-sky-700"
  }
  return "rounded-full bg-muted px-2.5 py-1 text-xs font-bold text-muted-foreground"
}

export default function BranchesPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const isAdmin = Boolean(meQuery.data?.user.is_platform_admin)
  const canManage = isAdmin || (meQuery.data?.platform_permissions.includes("platform.branches.manage") ?? false)
  const canViewReports = isAdmin || (meQuery.data?.platform_permissions.includes("platform.reports.view") ?? false)

  const [form, setForm] = useState({ name: "", code: "", slug: "", timezone: "Africa/Harare", currency_code: "USD" })
  const [formError, setFormError] = useState("")
  const [actionError, setActionError] = useState("")
  const [impersonationError, setImpersonationError] = useState("")

  const branchesQuery = useQuery({
    queryKey: ["platform", "branches"],
    queryFn: fetchPlatformBranches,
    enabled: isAdmin,
  })

  const platformId = branchesQuery.data?.[0]?.platform_id

  const reportQuery = useQuery({
    queryKey: ["platform", "branch-report", platformId],
    queryFn: () => fetchPlatformBranchReport(platformId ?? ""),
    enabled: canViewReports && Boolean(platformId),
  })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ["platform", "branches"] })
    void queryClient.invalidateQueries({ queryKey: ["platform", "branch-report"] })
  }

  const registerMutation = useMutation({
    mutationFn: registerPlatformBranch,
    onSuccess: () => {
      setForm({ name: "", code: "", slug: "", timezone: "Africa/Harare", currency_code: "USD" })
      setFormError("")
      refresh()
    },
    onError: (error: unknown) => setFormError(apiErrorMessage(error, "Could not register the branch.")),
  })

  const statusMutation = useMutation({
    mutationFn: ({ branchId, action }: { branchId: string; action: "approve" | "suspend" | "activate" | "archive" }) =>
      updatePlatformBranchStatus(branchId, action, action === "suspend" ? "Suspended from platform console" : undefined),
    onSuccess: () => {
      setActionError("")
      refresh()
    },
    onError: (error: unknown) => setActionError(apiErrorMessage(error, "Could not update the branch.")),
  })

  const impersonationMutation = useMutation({
    mutationFn: impersonatePlatformBranch,
    onSuccess: async (_result, branchId) => {
      storeBranchId(branchId)
      queryClient.clear()
      await queryClient.invalidateQueries({ queryKey: ["me"] })
      navigate("/app", { replace: true })
    },
    onError: (error: unknown) => setImpersonationError(apiErrorMessage(error, "Could not open that branch workspace.")),
  })

  if (meQuery.isLoading || branchesQuery.isLoading) {
    return (
      <div className="min-h-screen bg-background p-8">
        <LoadingState text="Loading branches..." />
      </div>
    )
  }

  if (branchesQuery.isError) {
    return (
      <div className="min-h-screen bg-background p-8">
        <ErrorState description={apiErrorMessage(branchesQuery.error, "Could not load branches.")} retry={() => void branchesQuery.refetch()} />
      </div>
    )
  }

  const branches = branchesQuery.data ?? []
  const report = reportQuery.data
  const currency = report?.overall.currency_codes[0] ?? "USD"

  return (
    <div className="min-h-screen bg-background p-6 md:p-8">
      <div className="mx-auto max-w-7xl space-y-6">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Platform console</p>
          <h1 className="text-3xl font-bold tracking-tight">Branches and analytics</h1>
          <p className="text-muted-foreground">Manage branch lifecycle, compare finances and open any active branch workspace.</p>
        </div>

        {(formError || actionError || impersonationError) && (
          <div role="alert" className="rounded-md bg-destructive/10 p-3 text-sm font-semibold text-destructive">
            {formError || actionError || impersonationError}
          </div>
        )}

        {canViewReports && report && (
          <Card className="overflow-hidden border-brand-ink/10">
            <div className="h-1 bg-gradient-to-r from-brand-green via-brand-mint to-brand-green-deep" aria-hidden="true" />
            <CardHeader>
              <CardTitle>Overall finances · {report.overall.as_of}</CardTitle>
            </CardHeader>
            <CardContent>
              {report.overall.mixed_currency && (
                <p className="mb-4 rounded-md bg-amber-500/10 p-3 text-sm font-semibold text-amber-700">
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

        <Card className="border-brand-ink/10">
          <CardHeader>
            <CardTitle>All branches</CardTitle>
          </CardHeader>
          <CardContent>
            {branches.length === 0 ? (
              <EmptyState title="No branches yet" description="Register your first branch to begin." />
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
                    {branches.map((branch) => {
                      const finance = report?.data.find((row) => row.branch_id === branch.id)
                      const branchCurrency = finance?.currency_code ?? branch.currency_code

                      return (
                        <tr key={branch.id} className="hover:bg-muted/40">
                          <td className="px-3 py-3">
                            <p className="font-bold text-foreground">{branch.name}</p>
                            <p className="text-xs text-muted-foreground">{branch.code} · {branch.timezone}</p>
                          </td>
                          <td className="px-3 py-3">
                            <span className={statusClass(branch.status)}>{branch.status}</span>
                          </td>
                          <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.invoiced_revenue_minor, branchCurrency) : "—"}</td>
                          <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.cash_position_minor, branchCurrency) : "—"}</td>
                          <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.money_owed_to_us_minor, branchCurrency) : "—"}</td>
                          <td className="px-3 py-3 font-semibold">{finance ? formatMoney(finance.inventory_value_minor, branchCurrency) : "—"}</td>
                          <td className="px-3 py-3">
                            <div className="flex flex-wrap gap-2">
                              {canManage && branch.status === "pending" && (
                                <Button size="sm" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate({ branchId: branch.id, action: "approve" })}>Approve</Button>
                              )}
                              {canManage && branch.status === "active" && (
                                <Button size="sm" variant="outline" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate({ branchId: branch.id, action: "suspend" })}>Suspend</Button>
                              )}
                              {canManage && branch.status === "suspended" && (
                                <Button size="sm" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate({ branchId: branch.id, action: "activate" })}>Activate</Button>
                              )}
                              {canManage && branch.status !== "archived" && (
                                <Button size="sm" variant="ghost" disabled={statusMutation.isPending} onClick={() => statusMutation.mutate({ branchId: branch.id, action: "archive" })}>Archive</Button>
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
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        {canManage && platformId && (
          <Card className="border-brand-ink/10">
            <CardHeader>
              <CardTitle>Register branch</CardTitle>
            </CardHeader>
            <CardContent>
              <form
                className="grid gap-4 md:grid-cols-2"
                onSubmit={(event) => {
                  event.preventDefault()
                  setFormError("")
                  registerMutation.mutate({ ...form, platform_id: platformId })
                }}
              >
                <div className="space-y-2">
                  <Label htmlFor="branch-name">Name</Label>
                  <Input id="branch-name" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-code">Code</Label>
                  <Input id="branch-code" required value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-slug">Slug</Label>
                  <Input id="branch-slug" required value={form.slug} onChange={(event) => setForm({ ...form, slug: event.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-timezone">Timezone</Label>
                  <Input id="branch-timezone" required value={form.timezone} onChange={(event) => setForm({ ...form, timezone: event.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="branch-currency">Currency</Label>
                  <Input id="branch-currency" required maxLength={3} value={form.currency_code} onChange={(event) => setForm({ ...form, currency_code: event.target.value.toUpperCase() })} />
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
      </div>
    </div>
  )
}
