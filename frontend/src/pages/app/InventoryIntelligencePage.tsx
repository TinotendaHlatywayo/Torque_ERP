import { Suspense, lazy } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { DashboardV2Response } from '@/lib/dashboard'

const IntelligenceCharts = lazy(async () => {
  const module = await import('@/components/ui/IntelligenceCharts')
  return { default: module.IntelligenceCharts }
})

export default function InventoryIntelligencePage() {
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canViewInventory = branch?.permissions.includes('branch.inventory.view') ?? false
  const canViewFinance = branch?.permissions.includes('branch.finance.view') ?? false

  const metricsQuery = useQuery({
    queryKey: ['dashboard-v2', branchId],
    queryFn: async () => {
      const response = await api.get<DashboardV2Response>(`/api/branches/${branchId}/dashboard/v2`)
      return response.data.data
    },
    enabled: Boolean(branchId && (canViewInventory || canViewFinance)),
  })

  if (!meQuery.data) {
    return <AppShell><LoadingState text="Loading branch access..." /></AppShell>
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

  if (!canViewInventory && !canViewFinance) {
    return (
      <AppShell>
        <PermissionDeniedState description="Your role does not include inventory or finance view permissions." />
      </AppShell>
    )
  }

  if (metricsQuery.isLoading) {
    return <AppShell><LoadingState text="Loading shared dashboard metrics..." /></AppShell>
  }

  if (metricsQuery.isError || !metricsQuery.data) {
    return (
      <AppShell>
        <ErrorState
          title="Inventory intelligence could not be loaded"
          description={apiErrorMessage(metricsQuery.error)}
          retry={() => void metricsQuery.refetch()}
        />
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="space-y-6 pb-12">
        <div className="rounded-xl border bg-card p-6 shadow-sm">
          <h1 className="text-3xl font-extrabold tracking-tight text-foreground">Inventory intelligence</h1>
          <p className="mt-1 text-sm font-medium text-muted-foreground">
            {branch.name} ({branch.code}) - reorder, ageing, ABC and labelled cash forecast from one shared metrics endpoint.
          </p>
          <a href="/app" className="mt-3 inline-block text-sm font-bold text-foreground underline underline-offset-4">
            Back to dashboard
          </a>
        </div>
        <Suspense fallback={<LoadingState text="Loading charts..." />}>
          <IntelligenceCharts data={metricsQuery.data} />
        </Suspense>
      </div>
    </AppShell>
  )
}
