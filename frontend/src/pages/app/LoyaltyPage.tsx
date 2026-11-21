import { FormEvent, useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import {
  DataResponse,
  ListResponse,
  LoyaltyConfig,
  LoyaltyQualification,
  RecalculationResult,
  WinbackCandidate,
  WinbackExportStub,
} from '@/lib/loyalty'

type Feedback = { type: 'error' | 'success'; text: string }

const emptyConfig: LoyaltyConfig = {
  inactivity_days: 90,
  min_qualifying_minor: 10000,
  min_qualifying_sales: 1,
  loyalty_discount_percent: 5,
  combined_approval_threshold_percent: 10,
  marketing_channels: [],
  channel_integration: 'not_configured',
  updated_at: null,
}

function formatMoney(minor: number, currency: string): string {
  return `${(minor / 100).toFixed(2)} ${currency}`
}

export default function LoyaltyPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id ?? ''
  const canView = branch?.permissions.includes('branch.loyalty.view') ?? false
  const canManage = branch?.permissions.includes('branch.loyalty.manage') ?? false
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [configForm, setConfigForm] = useState(emptyConfig)
  const [exportStub, setExportStub] = useState<WinbackExportStub | null>(null)

  const configQuery = useQuery({
    queryKey: ['loyalty-config', branchId],
    queryFn: async () => {
      const response = await api.get<DataResponse<LoyaltyConfig>>(`/api/branches/${branchId}/loyalty/config`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const qualificationsQuery = useQuery({
    queryKey: ['loyalty-qualifications', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<LoyaltyQualification>>(`/api/branches/${branchId}/loyalty/qualifications`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const candidatesQuery = useQuery({
    queryKey: ['loyalty-winback-candidates', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<WinbackCandidate>>(`/api/branches/${branchId}/loyalty/winback/candidates`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  useEffect(() => {
    if (configQuery.data) {
      setConfigForm(configQuery.data)
    }
  }, [configQuery.data])

  const refreshAll = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['loyalty-config', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['loyalty-qualifications', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['loyalty-winback-candidates', branchId] }),
    ])
  }

  const saveConfigMutation = useMutation({
    mutationFn: async () => {
      await api.put(`/api/branches/${branchId}/loyalty/config`, {
        inactivity_days: Number(configForm.inactivity_days),
        min_qualifying_minor: Number(configForm.min_qualifying_minor),
        min_qualifying_sales: Number(configForm.min_qualifying_sales),
        loyalty_discount_percent: Number(configForm.loyalty_discount_percent),
        combined_approval_threshold_percent: Number(configForm.combined_approval_threshold_percent),
      })
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Loyalty configuration saved.' })
      await refreshAll()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const recalculateMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post<DataResponse<RecalculationResult>>(`/api/branches/${branchId}/loyalty/recalculate`)
      return response.data.data
    },
    onSuccess: async (result) => {
      setFeedback({ type: 'success', text: `Recalculated ${result.customers_processed} customers from ${result.sale_events} sale events.` })
      await refreshAll()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const exportMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post<DataResponse<WinbackExportStub>>(`/api/branches/${branchId}/loyalty/winback/export`)
      return response.data.data
    },
    onSuccess: (result) => {
      setExportStub(result)
      setFeedback({ type: 'success', text: 'Export stub generated. No message was sent.' })
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const submitConfig = (event: FormEvent) => {
    event.preventDefault()
    setFeedback(null)
    saveConfigMutation.mutate()
  }

  if (meQuery.isLoading) return <AppShell><LoadingState text="Loading loyalty..." /></AppShell>
  if (meQuery.isError) return <AppShell><ErrorState description={apiErrorMessage(meQuery.error)} /></AppShell>
  if (!canView) return <AppShell><PermissionDeniedState description="You do not have permission to view loyalty data." /></AppShell>

  const qualifications = qualificationsQuery.data ?? []
  const candidates = candidatesQuery.data ?? []

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Loyalty and win-back</h1>
          <p className="text-sm text-muted-foreground">Qualification from committed sales and documented returns, consent-gated outreach</p>
        </div>
        {feedback && (
          <div className={`rounded-md border px-3 py-2 text-sm ${feedback.type === 'error' ? 'border-destructive text-destructive' : 'border-green-600 text-green-700'}`} role={feedback.type === 'error' ? 'alert' : 'status'}>
            {feedback.text}
          </div>
        )}

        <div className="grid gap-6 lg:grid-cols-3">
          <Card>
            <CardHeader><CardTitle>Configuration</CardTitle></CardHeader>
            <CardContent>
              {configQuery.isLoading && <LoadingState text="Loading configuration..." />}
              {configQuery.isError && <ErrorState description={apiErrorMessage(configQuery.error)} />}
              <form className="space-y-3" onSubmit={submitConfig}>
                <div>
                  <Label htmlFor="loyalty-inactivity-days">Inactivity window (days)</Label>
                  <Input id="loyalty-inactivity-days" type="number" min={1} max={3650} disabled={!canManage}
                    value={configForm.inactivity_days}
                    onChange={(event) => setConfigForm({ ...configForm, inactivity_days: Number(event.target.value) })} />
                </div>
                <div>
                  <Label htmlFor="loyalty-min-spend">Minimum qualifying spend (minor units)</Label>
                  <Input id="loyalty-min-spend" type="number" min={0} disabled={!canManage}
                    value={configForm.min_qualifying_minor}
                    onChange={(event) => setConfigForm({ ...configForm, min_qualifying_minor: Number(event.target.value) })} />
                </div>
                <div>
                  <Label htmlFor="loyalty-min-sales">Minimum qualifying sales</Label>
                  <Input id="loyalty-min-sales" type="number" min={1} disabled={!canManage}
                    value={configForm.min_qualifying_sales}
                    onChange={(event) => setConfigForm({ ...configForm, min_qualifying_sales: Number(event.target.value) })} />
                </div>
                <div>
                  <Label htmlFor="loyalty-discount">Loyalty discount (%)</Label>
                  <Input id="loyalty-discount" type="number" min={0} max={100} step="0.01" disabled={!canManage}
                    value={configForm.loyalty_discount_percent}
                    onChange={(event) => setConfigForm({ ...configForm, loyalty_discount_percent: Number(event.target.value) })} />
                </div>
                <div>
                  <Label htmlFor="loyalty-threshold">Combined discount approval threshold (%)</Label>
                  <Input id="loyalty-threshold" type="number" min={0} max={100} step="0.01" disabled={!canManage}
                    value={configForm.combined_approval_threshold_percent}
                    onChange={(event) => setConfigForm({ ...configForm, combined_approval_threshold_percent: Number(event.target.value) })} />
                </div>
                {canManage && <Button type="submit" disabled={saveConfigMutation.isPending}>Save configuration</Button>}
              </form>
              <p className="mt-3 text-xs text-muted-foreground">Channels: {configForm.marketing_channels.join(', ') || 'none'} · Integration: {configForm.channel_integration}</p>
              {canManage && (
                <Button className="mt-3" variant="outline" onClick={() => recalculateMutation.mutate()} disabled={recalculateMutation.isPending}>
                  Recalculate qualifications
                </Button>
              )}
            </CardContent>
          </Card>

          <Card className="lg:col-span-2">
            <CardHeader><CardTitle>Qualification list</CardTitle></CardHeader>
            <CardContent>
              {qualificationsQuery.isLoading && <LoadingState text="Loading qualifications..." />}
              {qualificationsQuery.isError && <ErrorState description={apiErrorMessage(qualificationsQuery.error)} />}
              {qualifications.length === 0 && !qualificationsQuery.isLoading && (
                <EmptyState title="No qualifications" description="Recalculate to build qualification snapshots from sales and returns." />
              )}
              {qualifications.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <caption className="sr-only">Customer loyalty qualifications</caption>
                    <thead>
                      <tr className="border-b text-left">
                        <th scope="col" className="py-2 pr-3">Customer</th>
                        <th scope="col" className="py-2 pr-3">Sales</th>
                        <th scope="col" className="py-2 pr-3">Gross</th>
                        <th scope="col" className="py-2 pr-3">Returns</th>
                        <th scope="col" className="py-2 pr-3">Net qualifying</th>
                        <th scope="col" className="py-2 pr-3">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {qualifications.map((qualification) => (
                        <tr key={qualification.id} className="border-b">
                          <td className="py-2 pr-3">{qualification.customer_name ?? qualification.customer_id}</td>
                          <td className="py-2 pr-3">{qualification.qualifying_sale_count}</td>
                          <td className="py-2 pr-3">{formatMoney(qualification.gross_sales_minor, qualification.currency_code)}</td>
                          <td className="py-2 pr-3">{formatMoney(qualification.returned_minor, qualification.currency_code)}</td>
                          <td className="py-2 pr-3">{formatMoney(qualification.net_qualifying_minor, qualification.currency_code)}</td>
                          <td className="py-2 pr-3">
                            {qualification.qualifies ? 'Qualified' : 'Not qualified'}
                            {qualification.is_inactive ? ' · inactive' : ''}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader><CardTitle>Win-back candidates</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Only customers with explicit marketing consent and an active channel appear here. No automated message is sent.
            </p>
            {candidatesQuery.isLoading && <LoadingState text="Loading candidates..." />}
            {candidatesQuery.isError && <ErrorState description={apiErrorMessage(candidatesQuery.error)} />}
            {candidates.length === 0 && !candidatesQuery.isLoading && (
              <EmptyState title="No eligible candidates" description="A candidate needs qualification, inactivity, consent and an active channel." />
            )}
            {candidates.length > 0 && (
              <ul className="space-y-2">
                {candidates.map((candidate) => (
                  <li key={candidate.customer_id} className="flex flex-wrap items-center justify-between rounded-md border p-3 text-sm">
                    <span className="font-medium">{candidate.name ?? candidate.customer_id}</span>
                    <span className="text-muted-foreground">
                      {candidate.marketing_channel} · {candidate.days_inactive} days inactive · {formatMoney(candidate.net_qualifying_minor, candidate.currency_code)}
                    </span>
                  </li>
                ))}
              </ul>
            )}
            <Button variant="outline" onClick={() => exportMutation.mutate()} disabled={exportMutation.isPending}>
              Generate export stub
            </Button>
            {exportStub && (
              <div className="rounded-md border p-3 text-sm" role="status">
                <div>Delivery: {exportStub.delivery_status} · Integration: {exportStub.channel_integration}</div>
                <div className="text-muted-foreground">{exportStub.reason}</div>
                <div className="mt-1">Candidates: {exportStub.candidates.length} · Contact: {exportStub.contact_disposition}</div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  )
}
