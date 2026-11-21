import { Suspense, lazy, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { Reveal } from '@/components/ui/Reveal'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { DashboardV2Response } from '@/lib/dashboard'
import { cn } from '@/lib/utils'

const IntelligenceCharts = lazy(async () => {
  const module = await import('@/components/ui/IntelligenceCharts')
  return { default: module.IntelligenceCharts }
})

function MetricCard({ label, value, accent = 'bg-brand-green' }: { label: string; value: string; accent?: string }) {
  return (
    <Card className="hover-lift relative overflow-hidden border-border/60 shadow-sm transition-shadow hover:shadow-lg">
      <div className={cn('absolute inset-x-0 top-0 h-1', accent)} aria-hidden="true" />
      <CardHeader className="pb-2">
        <CardTitle className="text-xs font-bold uppercase tracking-wider text-muted-foreground">{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-black tracking-tight text-foreground">{value}</div>
      </CardContent>
    </Card>
  )
}

export default function DashboardPage() {
  const queryClient = useQueryClient()
  const [actionMessage, setActionMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)
  const [isProcessing, setIsProcessing] = useState(false)

  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canViewReports = branch?.permissions.includes('branch.reports.view') ?? false
  const canViewFinance = branch?.permissions.includes('branch.finance.view') ?? false
  const canViewInventory = branch?.permissions.includes('branch.inventory.view') ?? false
  const canManageDemoData = meQuery.data?.platform_permissions.includes('platform.settings.manage') ?? false

  const metricsQuery = useQuery({
    queryKey: ['dashboard-v2', branchId],
    queryFn: async () => {
      const response = await api.get<DashboardV2Response>(`/api/branches/${branchId}/dashboard/v2`)
      return response.data.data
    },
    enabled: Boolean(branchId && (canViewFinance || canViewInventory)),
  })

  const invalidateBranchData = async () => {
    if (!branchId) return
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['catalogue', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['stock-products', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['pos-products', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['customers', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['stock-levels', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['stock-movements', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['catalogue-search', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['sales', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['dashboard-v2', branchId] }),
    ])
  }

  const handleSeedDemoData = async () => {
    if (!branchId || !canManageDemoData) return
    setIsProcessing(true)
    setActionMessage(null)
    try {
      const response = await api.post(`/api/branches/${branchId}/demo/seed`)
      setActionMessage({ type: 'success', text: response.data.message || 'Demo data successfully seeded across the system.' })
      await invalidateBranchData()
    } catch (error: unknown) {
      setActionMessage({ type: 'error', text: apiErrorMessage(error) })
    } finally {
      setIsProcessing(false)
    }
  }

  const handleWipeDemoData = async () => {
    if (!branchId || !canManageDemoData) return
    if (!window.confirm('Are you sure you want to wipe demo data? This will clear all demo business data, including the demo destination branch. Your main branch, admin login, and audit history will remain.')) {
      return
    }
    setIsProcessing(true)
    setActionMessage(null)
    try {
      const response = await api.post(`/api/branches/${branchId}/demo/wipe`)
      setActionMessage({ type: 'success', text: response.data.message || 'Demo data wiped successfully.' })
      await invalidateBranchData()
    } catch (error: unknown) {
      setActionMessage({ type: 'error', text: apiErrorMessage(error) })
    } finally {
      setIsProcessing(false)
    }
  }

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

  const metrics = metricsQuery.data?.finance_metrics ?? null

  return (
    <AppShell>
      <div className="space-y-8 pb-12">
        <Reveal>
          <div className="relative overflow-hidden flex flex-col md:flex-row md:items-center md:justify-between gap-4 rounded-2xl bg-gradient-to-br from-brand-ink via-brand-ink to-brand-green-deep p-6 text-white shadow-lg sm:p-8">
            <div className="grid-texture absolute inset-0 opacity-20" aria-hidden="true" />
            <div className="relative z-10">
              <p className="text-xs font-bold uppercase tracking-[0.22em] text-brand-mint">Live operations</p>
              <h1 className="mt-2 text-3xl font-black tracking-tight">Branch Workspace</h1>
              <p className="mt-1 text-sm font-medium text-brand-surface/70">{branch.name} ({branch.code}) — Live ERP Operations Dashboard</p>
              {(canViewInventory || canViewFinance) && (
                <a href="/app/inventory-intelligence" className="mt-3 inline-flex min-h-11 items-center rounded-md text-sm font-bold text-brand-mint underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-mint">
                  Open inventory intelligence and forecast charts
                </a>
              )}
            </div>
            {canManageDemoData && (
              <div className="relative z-10 flex flex-wrap items-center gap-3">
                <Button
                  onClick={handleSeedDemoData}
                  disabled={isProcessing}
                  className="min-h-11 bg-white text-brand-ink hover:bg-brand-surface"
                >
                  {isProcessing ? 'Processing...' : 'Seed Demo Data'}
                </Button>
                <Button
                  variant="outline"
                  onClick={handleWipeDemoData}
                  disabled={isProcessing}
                  className="min-h-11 border-red-300/60 text-red-100 hover:bg-red-500/20 font-semibold"
                >
                  Wipe Demo Data
                </Button>
              </div>
            )}
          </div>
        </Reveal>

        {actionMessage && (
          <div role={actionMessage.type === 'error' ? 'alert' : 'status'} className={`p-4 rounded-lg text-sm font-semibold shadow-sm border ${actionMessage.type === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'}`}>
            {actionMessage.text}
          </div>
        )}

        {!canViewFinance && !canViewInventory && (
          <PermissionDeniedState description="Your role does not include permission to view finance or inventory metrics." />
        )}

        {(canViewFinance || canViewInventory) && metricsQuery.isLoading && <LoadingState text="Loading metrics..." />}
        {(canViewFinance || canViewInventory) && metricsQuery.isError && (
          <ErrorState
            title="Metrics could not be loaded"
            description={apiErrorMessage(metricsQuery.error)}
            retry={() => void metricsQuery.refetch()}
          />
        )}

        {canViewFinance && metrics && (
          <Reveal delay={80}>
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
              <MetricCard label="Money In" value={`${metrics.money_in_minor} ${metrics.currency_code}`} />
              <MetricCard label="Money Out" value={`${metrics.money_out_minor} ${metrics.currency_code}`} accent="bg-brand-mint" />
              <MetricCard label="Invoiced Revenue" value={`${metrics.invoiced_revenue_minor} ${metrics.currency_code}`} />
              <MetricCard label="Money Owed to Us" value={`${metrics.money_owed_to_us_minor} ${metrics.currency_code}`} accent="bg-brand-green-deep" />
              <MetricCard label="Bills to Pay" value={`${metrics.bills_to_pay_minor} ${metrics.currency_code}`} accent="bg-brand-mint" />
              <MetricCard label="Cash Position" value={`${metrics.cash_position_minor} ${metrics.currency_code}`} />
              <MetricCard label="Inventory Value" value={`${metrics.inventory_value_minor} ${metrics.currency_code}`} accent="bg-brand-green-deep" />
              <MetricCard label="Profit Basis" value={`${metrics.profit_basis_minor} ${metrics.currency_code}`} />
            </div>
          </Reveal>
        )}


        {(canViewInventory || canViewFinance) && metricsQuery.data && (
          <Suspense fallback={<LoadingState text="Loading charts..." />}>
            <IntelligenceCharts data={metricsQuery.data} />
          </Suspense>
        )}

        {!canViewReports && !canViewFinance && !canViewInventory && (
          <EmptyState title="No report access" description="Your role does not include report, finance or inventory permissions." />
        )}
      </div>
    </AppShell>
  )
}
