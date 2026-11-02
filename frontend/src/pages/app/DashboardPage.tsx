import { useQuery } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'

type MetricsResponse = {
  data: {
    money_in_minor: number
    money_out_minor: number
    invoiced_revenue_minor: number
    expenses_incurred_minor: number
    profit_basis_minor: number
    money_owed_to_us_minor: number
    bills_to_pay_minor: number
    inventory_value_minor: number
    cash_position_minor: number
    currency_code: string
  }
}

export default function DashboardPage() {
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canViewReports = branch?.permissions.includes('branch.reports.view') ?? false
  const canViewFinance = branch?.permissions.includes('branch.finance.view') ?? false

  const metricsQuery = useQuery({
    queryKey: ['finance-metrics', branchId],
    queryFn: async () => {
      const response = await api.get<MetricsResponse>(`/api/branches/${branchId}/finance/metrics`)
      return response.data.data
    },
    enabled: Boolean(branchId && canViewFinance),
  })

  if (!meQuery.data) {
    return (
      <AppShell>
        <LoadingState text="Loading branch access..." />
      </AppShell>
    )
  }

  if (!branch) {
    return (
      <AppShell>
        <PermissionDeniedState
          title="No branch membership"
          description="Your account is active but is not assigned to a branch. Ask a platform administrator for access."
        />
      </AppShell>
    )
  }

  const metrics = metricsQuery.data

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Branch Workspace</h1>
          <p className="text-muted-foreground">{branch.name} ({branch.code}) — dashboard</p>
        </div>

        {!canViewFinance && (
          <PermissionDeniedState description="Your role does not include permission to view finance metrics." />
        )}

        {canViewFinance && metricsQuery.isLoading && <LoadingState text="Loading metrics..." />}
        {canViewFinance && metricsQuery.isError && (
          <ErrorState
            title="Metrics could not be loaded"
            description={apiErrorMessage(metricsQuery.error)}
            retry={() => void metricsQuery.refetch()}
          />
        )}

        {canViewFinance && metrics && (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card>
              <CardHeader><CardTitle className="text-base">Money In</CardTitle></CardHeader>
              <CardContent>{metrics.money_in_minor} {metrics.currency_code}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">Money Out</CardTitle></CardHeader>
              <CardContent>{metrics.money_out_minor} {metrics.currency_code}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">Invoiced Revenue</CardTitle></CardHeader>
              <CardContent>{metrics.invoiced_revenue_minor} {metrics.currency_code}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">Money Owed to Us</CardTitle></CardHeader>
              <CardContent>{metrics.money_owed_to_us_minor} {metrics.currency_code}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">Bills to Pay</CardTitle></CardHeader>
              <CardContent>{metrics.bills_to_pay_minor} {metrics.currency_code}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">Cash Position</CardTitle></CardHeader>
              <CardContent>{metrics.cash_position_minor} {metrics.currency_code}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">Inventory Value</CardTitle></CardHeader>
              <CardContent>{metrics.inventory_value_minor} {metrics.currency_code}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">Profit Basis</CardTitle></CardHeader>
              <CardContent>{metrics.profit_basis_minor} {metrics.currency_code}</CardContent>
            </Card>
          </div>
        )}

        {!canViewReports && !canViewFinance && (
          <EmptyState title="No report access" description="Your role does not include report or finance permissions." />
        )}
      </div>
    </AppShell>
  )
}
