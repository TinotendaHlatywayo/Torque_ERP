import { FormEvent, useState } from 'react'
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
  AssetRecord,
  DataResponse,
  DepreciationEstimate,
  DisposalResponse,
  EstimateReport,
  FinancialEffect,
  ListResponse,
} from '@/lib/assets'

type Feedback = { type: 'error' | 'success'; text: string }

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm'
const today = new Date().toISOString().slice(0, 10)

const emptyCreate = {
  name: '',
  acquisition_date: today,
  cost_minor: '',
  currency_code: 'USD',
  location: '',
  custodian: '',
  source_type: '',
  source_id: '',
}

const emptyMaintenance = {
  performed_date: today,
  cost_minor: '',
  currency_code: 'USD',
  provider: '',
  description: '',
  expense_id: '',
  payment_id: '',
}

const emptyDisposal = {
  disposal_date: today,
  proceeds_minor: '0',
  accumulated_estimate_minor: '0',
  notes: '',
}

function formatMoney(minor: number, currency: string): string {
  return `${(minor / 100).toFixed(2)} ${currency}`
}

export default function AssetsPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id ?? ''
  const canView = branch?.permissions.includes('branch.assets.view') ?? false
  const canManage = branch?.permissions.includes('branch.assets.manage') ?? false
  const canDispose = branch?.permissions.includes('branch.assets.dispose') ?? false

  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [selectedAssetId, setSelectedAssetId] = useState<string | null>(null)
  const [createForm, setCreateForm] = useState(emptyCreate)
  const [maintenanceForm, setMaintenanceForm] = useState(emptyMaintenance)
  const [disposalForm, setDisposalForm] = useState(emptyDisposal)
  const [disposalEffect, setDisposalEffect] = useState<FinancialEffect | null>(null)
  const [estimateParams, setEstimateParams] = useState({ as_of: today, useful_life_years: 5, residual_minor: 0 })
  const [showReport, setShowReport] = useState(false)

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['assets', branchId] }),
      queryClient.invalidateQueries({ queryKey: ['asset', branchId, selectedAssetId] }),
      queryClient.invalidateQueries({ queryKey: ['asset-estimate', branchId, selectedAssetId] }),
      queryClient.invalidateQueries({ queryKey: ['asset-report', branchId] }),
    ])
  }

  const assetsQuery = useQuery({
    queryKey: ['assets', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<AssetRecord>>(`/api/branches/${branchId}/assets`)
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const assetQuery = useQuery({
    queryKey: ['asset', branchId, selectedAssetId],
    queryFn: async () => {
      const response = await api.get<DataResponse<AssetRecord>>(`/api/branches/${branchId}/assets/${selectedAssetId!}`)
      return response.data.data
    },
    enabled: Boolean(branchId && canView && selectedAssetId),
  })

  const estimateQuery = useQuery({
    queryKey: ['asset-estimate', branchId, selectedAssetId, estimateParams],
    queryFn: async () => {
      const response = await api.get<DataResponse<DepreciationEstimate>>(
        `/api/branches/${branchId}/assets/${selectedAssetId!}/depreciation-estimate`,
        { params: estimateParams },
      )
      return response.data.data
    },
    enabled: Boolean(branchId && canView && selectedAssetId),
  })

  const reportQuery = useQuery({
    queryKey: ['asset-report', branchId, estimateParams],
    queryFn: async () => {
      const response = await api.get<EstimateReport>(`/api/branches/${branchId}/assets/depreciation-estimate`, { params: estimateParams })
      return response.data
    },
    enabled: Boolean(branchId && canView && showReport),
  })

  const createMutation = useMutation({
    mutationFn: async () => {
      await api.post(`/api/branches/${branchId}/assets`, {
        name: createForm.name,
        acquisition_date: createForm.acquisition_date,
        cost_minor: Number(createForm.cost_minor),
        currency_code: createForm.currency_code,
        location: createForm.location,
        custodian: createForm.custodian,
        ...(createForm.source_type && createForm.source_id
          ? { source_type: createForm.source_type, source_id: createForm.source_id }
          : {}),
      })
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Asset acquired. No expense or money movement was created.' })
      setCreateForm(emptyCreate)
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const maintenanceMutation = useMutation({
    mutationFn: async () => {
      await api.post(`/api/branches/${branchId}/assets/${selectedAssetId}/maintenances`, {
        performed_date: maintenanceForm.performed_date,
        cost_minor: Number(maintenanceForm.cost_minor),
        currency_code: maintenanceForm.currency_code,
        provider: maintenanceForm.provider,
        description: maintenanceForm.description,
        expense_id: maintenanceForm.expense_id || null,
        payment_id: maintenanceForm.payment_id || null,
      })
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Maintenance record saved without creating a new payment.' })
      setMaintenanceForm(emptyMaintenance)
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const disposeMutation = useMutation({
    mutationFn: async () => {
      const response = await api.post<DisposalResponse>(`/api/branches/${branchId}/assets/${selectedAssetId}/dispose`, {
        disposal_date: disposalForm.disposal_date,
        proceeds_minor: Number(disposalForm.proceeds_minor),
        accumulated_estimate_minor: Number(disposalForm.accumulated_estimate_minor),
        notes: disposalForm.notes || null,
      })
      return response.data.data.financial_effect
    },
    onSuccess: async (effect) => {
      setDisposalEffect(effect)
      setFeedback({ type: 'success', text: 'Disposal recorded and audited. No accounting entry was posted.' })
      setDisposalForm(emptyDisposal)
      await refresh()
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const submit = (event: FormEvent<HTMLFormElement>, action: () => void) => {
    event.preventDefault()
    setFeedback(null)
    action()
  }

  const selectAsset = (assetId: string) => {
    setSelectedAssetId(assetId)
    setDisposalEffect(null)
    setFeedback(null)
  }

  if (meQuery.isLoading) return <AppShell><LoadingState text="Loading assets..." /></AppShell>
  if (meQuery.isError) return <AppShell><ErrorState description={apiErrorMessage(meQuery.error)} /></AppShell>
  if (!canView) return <AppShell><PermissionDeniedState description="You do not have permission to view assets." /></AppShell>

  const assets = assetsQuery.data?.data ?? []
  const selectedAsset = assetQuery.data ?? null
  const estimate = estimateQuery.data ?? null

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Assets and maintenance</h1>
            <p className="text-sm text-muted-foreground">Acquisition, custodianship, maintenance, disposal and non-posting depreciation estimates</p>
          </div>
          <Button variant="outline" onClick={() => setShowReport(!showReport)}>{showReport ? 'Hide' : 'Show'} branch estimate report</Button>
        </div>

        {feedback && (
          <div role={feedback.type === 'error' ? 'alert' : 'status'} className={`rounded-md border px-3 py-2 text-sm ${feedback.type === 'error' ? 'border-destructive text-destructive' : 'border-green-600 text-green-700'}`}>
            {feedback.text}
          </div>
        )}

        <div className="grid gap-6 lg:grid-cols-3">
          <Card className="lg:col-span-2">
            <CardHeader><CardTitle>Asset register</CardTitle></CardHeader>
            <CardContent>
              {assetsQuery.isLoading && <LoadingState text="Loading asset register..." />}
              {assetsQuery.isError && <ErrorState description={apiErrorMessage(assetsQuery.error)} />}
              {assets.length === 0 && !assetsQuery.isLoading && <EmptyState title="No assets" description="Record the first asset acquisition." />}
              <div className="space-y-2">
                {assets.map((asset) => (
                  <button
                    key={asset.id}
                    type="button"
                    onClick={() => selectAsset(asset.id)}
                    aria-pressed={selectedAssetId === asset.id}
                    className="flex w-full items-center justify-between rounded-md border p-3 text-left hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    <div>
                      <div className="font-medium">{asset.name}</div>
                      <div className="text-xs text-muted-foreground">{asset.location} · {asset.custodian} · acquired {asset.acquisition_date}</div>
                    </div>
                    <div className="text-right">
                      <div className="text-sm">{formatMoney(asset.cost_minor, asset.currency_code)}</div>
                      <span className="rounded-full border px-2 py-0.5 text-xs">{asset.status}</span>
                    </div>
                  </button>
                ))}
              </div>
            </CardContent>
          </Card>

          {canManage && (
            <Card>
              <CardHeader><CardTitle>Acquire asset</CardTitle></CardHeader>
              <CardContent>
                <form className="space-y-3" onSubmit={(event) => submit(event, () => createMutation.mutate())}>
                  <div><Label htmlFor="asset-name">Name</Label><Input id="asset-name" required value={createForm.name} onChange={(event) => setCreateForm({ ...createForm, name: event.target.value })} /></div>
                  <div><Label htmlFor="asset-acquisition-date">Acquisition date</Label><Input id="asset-acquisition-date" type="date" required value={createForm.acquisition_date} onChange={(event) => setCreateForm({ ...createForm, acquisition_date: event.target.value })} /></div>
                  <div className="grid grid-cols-2 gap-2">
                    <div><Label htmlFor="asset-cost">Cost (minor units)</Label><Input id="asset-cost" type="number" min="1" required value={createForm.cost_minor} onChange={(event) => setCreateForm({ ...createForm, cost_minor: event.target.value })} /></div>
                    <div><Label htmlFor="asset-currency">Currency</Label><Input id="asset-currency" required maxLength={3} value={createForm.currency_code} onChange={(event) => setCreateForm({ ...createForm, currency_code: event.target.value.toUpperCase() })} /></div>
                  </div>
                  <div><Label htmlFor="asset-location">Location</Label><Input id="asset-location" required value={createForm.location} onChange={(event) => setCreateForm({ ...createForm, location: event.target.value })} /></div>
                  <div><Label htmlFor="asset-custodian">Custodian</Label><Input id="asset-custodian" required value={createForm.custodian} onChange={(event) => setCreateForm({ ...createForm, custodian: event.target.value })} /></div>
                  <div>
                    <Label htmlFor="asset-source-type">Existing purchase link</Label>
                    <select id="asset-source-type" className={selectClass} value={createForm.source_type} onChange={(event) => setCreateForm({ ...createForm, source_type: event.target.value })}>
                      <option value="">None (unlinked)</option>
                      <option value="expense">Expense</option>
                      <option value="supplier_bill">Supplier bill</option>
                      <option value="money_movement">Money movement / payment</option>
                    </select>
                  </div>
                  {createForm.source_type && (
                    <div><Label htmlFor="asset-source-id">Existing record ID</Label><Input id="asset-source-id" required value={createForm.source_id} onChange={(event) => setCreateForm({ ...createForm, source_id: event.target.value })} /></div>
                  )}
                  <Button type="submit" disabled={createMutation.isPending}>Record acquisition</Button>
                </form>
              </CardContent>
            </Card>
          )}
        </div>

        {selectedAssetId && (
          <div className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
              <CardHeader><CardTitle>{selectedAsset?.name ?? 'Asset detail'}</CardTitle></CardHeader>
              <CardContent className="space-y-3 text-sm">
                {assetQuery.isLoading && <LoadingState text="Loading asset detail..." />}
                {assetQuery.isError && <ErrorState description={apiErrorMessage(assetQuery.error)} />}
                {selectedAsset && (
                  <>
                    <div>Acquired {selectedAsset.acquisition_date} · cost {formatMoney(selectedAsset.cost_minor, selectedAsset.currency_code)} · status {selectedAsset.status}</div>
                    <div>Location: {selectedAsset.location} · Custodian: {selectedAsset.custodian}</div>
                    <div>Source: {selectedAsset.source_type ? `${selectedAsset.source_type} (${selectedAsset.source_id})` : 'Not linked'}</div>
                    {selectedAsset.description && <div>{selectedAsset.description}</div>}
                    {selectedAsset.status === 'disposed' && (
                      <div className="rounded-md border px-3 py-2">
                        Disposed {selectedAsset.disposal_date} · proceeds {formatMoney(selectedAsset.disposal_proceeds_minor ?? 0, selectedAsset.currency_code)}
                        {selectedAsset.disposal_notes && <div className="text-muted-foreground">{selectedAsset.disposal_notes}</div>}
                      </div>
                    )}
                    <div className="pt-2 font-medium">Maintenance history</div>
                    {selectedAsset.maintenances.length === 0 && <p className="text-muted-foreground">No maintenance recorded.</p>}
                    {selectedAsset.maintenances.map((record) => (
                      <div key={record.id} className="border-b py-2">
                        <div>{record.performed_date} · {formatMoney(record.cost_minor, record.currency_code)} · {record.provider}</div>
                        <div className="text-muted-foreground">{record.description}</div>
                      </div>
                    ))}
                  </>
                )}
              </CardContent>
            </Card>

            <div className="space-y-6">
              <Card>
                <CardHeader><CardTitle>Depreciation estimate</CardTitle></CardHeader>
                <CardContent className="space-y-3 text-sm">
                  <div className="rounded-md border border-amber-600 px-3 py-2 text-amber-700">
                    ESTIMATE — BLOCKED for accounting use. No depreciation policy is confirmed and nothing is posted.
                  </div>
                  <div className="grid grid-cols-3 gap-2">
                    <div><Label htmlFor="estimate-as-of">As of</Label><Input id="estimate-as-of" type="date" value={estimateParams.as_of} onChange={(event) => setEstimateParams({ ...estimateParams, as_of: event.target.value })} /></div>
                    <div><Label htmlFor="estimate-years">Years</Label><Input id="estimate-years" type="number" min="1" max="100" value={estimateParams.useful_life_years} onChange={(event) => setEstimateParams({ ...estimateParams, useful_life_years: Number(event.target.value) })} /></div>
                    <div><Label htmlFor="estimate-residual">Residual (minor)</Label><Input id="estimate-residual" type="number" min="0" value={estimateParams.residual_minor} onChange={(event) => setEstimateParams({ ...estimateParams, residual_minor: Number(event.target.value) })} /></div>
                  </div>
                  {estimateQuery.isLoading && <LoadingState text="Calculating estimate..." />}
                  {estimateQuery.isError && <ErrorState description={apiErrorMessage(estimateQuery.error)} />}
                  {estimate && (
                    <dl className="space-y-1">
                      <div className="flex justify-between"><dt>Accumulated estimate</dt><dd>{formatMoney(estimate.accumulated_estimate_minor, estimate.currency_code)}</dd></div>
                      <div className="flex justify-between"><dt>Estimated book value</dt><dd>{formatMoney(estimate.book_value_estimate_minor, estimate.currency_code)}</dd></div>
                      <div className="flex justify-between"><dt>Months elapsed</dt><dd>{estimate.months_elapsed} / {estimate.useful_life_months}</dd></div>
                      <div className="flex justify-between"><dt>Posted to ledger</dt><dd>{estimate.posted ? 'Yes' : 'No'}</dd></div>
                    </dl>
                  )}
                  <p className="text-xs text-muted-foreground">Supply your own useful life and residual assumptions; no policy default is asserted by the system.</p>
                </CardContent>
              </Card>

              {canManage && selectedAsset?.status === 'active' && (
                <Card>
                  <CardHeader><CardTitle>Record maintenance</CardTitle></CardHeader>
                  <CardContent>
                    <form className="space-y-3" onSubmit={(event) => submit(event, () => maintenanceMutation.mutate())}>
                      <div><Label htmlFor="maintenance-date">Date</Label><Input id="maintenance-date" type="date" required value={maintenanceForm.performed_date} onChange={(event) => setMaintenanceForm({ ...maintenanceForm, performed_date: event.target.value })} /></div>
                      <div className="grid grid-cols-2 gap-2">
                        <div><Label htmlFor="maintenance-cost">Cost (minor units)</Label><Input id="maintenance-cost" type="number" min="1" required value={maintenanceForm.cost_minor} onChange={(event) => setMaintenanceForm({ ...maintenanceForm, cost_minor: event.target.value })} /></div>
                        <div><Label htmlFor="maintenance-currency">Currency</Label><Input id="maintenance-currency" required maxLength={3} value={maintenanceForm.currency_code} onChange={(event) => setMaintenanceForm({ ...maintenanceForm, currency_code: event.target.value.toUpperCase() })} /></div>
                      </div>
                      <div><Label htmlFor="maintenance-provider">Provider</Label><Input id="maintenance-provider" required value={maintenanceForm.provider} onChange={(event) => setMaintenanceForm({ ...maintenanceForm, provider: event.target.value })} /></div>
                      <div><Label htmlFor="maintenance-description">Description</Label><Input id="maintenance-description" required maxLength={500} value={maintenanceForm.description} onChange={(event) => setMaintenanceForm({ ...maintenanceForm, description: event.target.value })} /></div>
                      <div className="grid grid-cols-2 gap-2">
                        <div><Label htmlFor="maintenance-expense">Expense ID (optional)</Label><Input id="maintenance-expense" value={maintenanceForm.expense_id} onChange={(event) => setMaintenanceForm({ ...maintenanceForm, expense_id: event.target.value })} /></div>
                        <div><Label htmlFor="maintenance-payment">Payment ID (optional)</Label><Input id="maintenance-payment" value={maintenanceForm.payment_id} onChange={(event) => setMaintenanceForm({ ...maintenanceForm, payment_id: event.target.value })} /></div>
                      </div>
                      <Button type="submit" disabled={maintenanceMutation.isPending}>Save maintenance</Button>
                    </form>
                  </CardContent>
                </Card>
              )}

              {canDispose && selectedAsset?.status === 'active' && (
                <Card>
                  <CardHeader><CardTitle>Dispose asset</CardTitle></CardHeader>
                  <CardContent className="space-y-3">
                    <form className="space-y-3" onSubmit={(event) => submit(event, () => disposeMutation.mutate())}>
                      <div><Label htmlFor="disposal-date">Disposal date</Label><Input id="disposal-date" type="date" required value={disposalForm.disposal_date} onChange={(event) => setDisposalForm({ ...disposalForm, disposal_date: event.target.value })} /></div>
                      <div className="grid grid-cols-2 gap-2">
                        <div><Label htmlFor="disposal-proceeds">Proceeds (minor units)</Label><Input id="disposal-proceeds" type="number" min="0" required value={disposalForm.proceeds_minor} onChange={(event) => setDisposalForm({ ...disposalForm, proceeds_minor: event.target.value })} /></div>
                        <div><Label htmlFor="disposal-accumulated">Accumulated estimate (minor)</Label><Input id="disposal-accumulated" type="number" min="0" required value={disposalForm.accumulated_estimate_minor} onChange={(event) => setDisposalForm({ ...disposalForm, accumulated_estimate_minor: event.target.value })} /></div>
                      </div>
                      <div><Label htmlFor="disposal-notes">Notes</Label><Input id="disposal-notes" maxLength={1000} value={disposalForm.notes} onChange={(event) => setDisposalForm({ ...disposalForm, notes: event.target.value })} /></div>
                      <Button type="submit" variant="destructive" disabled={disposeMutation.isPending}>Record disposal</Button>
                    </form>
                    {disposalEffect && (
                      <div className="rounded-md border px-3 py-2 text-sm" role="status">
                        <div className="font-medium">Financial effect — ESTIMATE, BLOCKED for accounting use</div>
                        <div>Cost: {formatMoney(disposalEffect.cost_minor, disposalEffect.currency_code)}</div>
                        <div>Accumulated estimate: {formatMoney(disposalEffect.accumulated_estimate_minor, disposalEffect.currency_code)}</div>
                        <div>Proceeds: {formatMoney(disposalEffect.proceeds_minor, disposalEffect.currency_code)}</div>
                        <div>Estimated {disposalEffect.estimated_gain_loss_minor >= 0 ? 'gain' : 'loss'}: {formatMoney(Math.abs(disposalEffect.estimated_gain_loss_minor), disposalEffect.currency_code)}</div>
                        <div className="text-muted-foreground">Automatic entries posted: {disposalEffect.automatic_entries_posted}</div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              )}
            </div>
          </div>
        )}

        {showReport && canView && (
          <Card>
            <CardHeader><CardTitle>Branch depreciation estimate report</CardTitle></CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="rounded-md border border-amber-600 px-3 py-2 text-amber-700">
                ESTIMATE — BLOCKED for accounting use. No entries are posted.
              </div>
              {reportQuery.isLoading && <LoadingState text="Loading estimate report..." />}
              {reportQuery.isError && <ErrorState description={apiErrorMessage(reportQuery.error)} />}
              {reportQuery.data?.data.length === 0 && <EmptyState title="No assets" description="No estimates are available." />}
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead><tr className="border-b"><th scope="col" className="py-2">Asset</th><th scope="col">Cost</th><th scope="col">Accumulated estimate</th><th scope="col">Book value estimate</th></tr></thead>
                  <tbody>
                    {(reportQuery.data?.data ?? []).map((row) => (
                      <tr key={row.asset_id} className="border-b">
                        <td className="py-2">{row.asset_name}</td>
                        <td>{formatMoney(row.cost_minor, row.currency_code)}</td>
                        <td>{formatMoney(row.accumulated_estimate_minor, row.currency_code)}</td>
                        <td>{formatMoney(row.book_value_estimate_minor, row.currency_code)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  )
}
