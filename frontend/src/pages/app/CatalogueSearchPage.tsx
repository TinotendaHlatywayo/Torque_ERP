import { FormEvent, useMemo, useState } from 'react'
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

type MatchType = 'exact_sku_barcode' | 'oem' | 'fitment' | 'typo_tolerant'

type CatalogueResult = {
  product_id: string
  sku: string
  barcode: string | null
  name: string
  selling_price_minor: number
  currency_code: string
  match_type: MatchType
  match_detail: string
  source: string
  fitment: {
    make: string
    model: string
    year_from: string | null
    year_to: string | null
    engine: string | null
    source: string
    source_ref: string
    verified: string
  } | null
  oem: {
    oem_number: string
    brand: string | null
    source: string
    source_ref: string
    verified: string
  } | null
}

type SearchResponse = {
  data: CatalogueResult[]
  meta: { count: number; query: Record<string, string> }
}

type Feedback = { type: 'error' | 'success'; text: string }

type ImportSummary = {
  idempotent_replay?: boolean
  rows_received?: number
  rows_created?: number
  rows_skipped?: number
}

const matchTypeLabel: Record<MatchType, string> = {
  exact_sku_barcode: 'Exact SKU/Barcode',
  oem: 'OEM / Cross-reference',
  fitment: 'Vehicle fitment',
  typo_tolerant: 'Approximate (typo-tolerant)',
}

function formatMinor(minor: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100)
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`
  }
}

export default function CatalogueSearchPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id ?? ''
  const canView = branch?.permissions.includes('branch.catalogue.view') ?? false
  const canManage = branch?.permissions.includes('branch.catalogue.manage') ?? false

  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [searchForm, setSearchForm] = useState({
    q: '',
    make: '',
    model: '',
    year: '',
    engine: '',
    oem: '',
  })
  const [lastQuery, setLastQuery] = useState<Record<string, string>>({})
  const [importForm, setImportForm] = useState({
    source: '',
    source_ref: '',
    type: 'fitment' as 'fitment' | 'oem',
    rowsJson: '',
  })

  const searchEnabled = Boolean(branchId && canView && Object.values(lastQuery).some((v) => v.trim() !== ''))

  const searchQuery = useQuery({
    queryKey: ['catalogue-search', branchId, lastQuery],
    queryFn: async () => {
      const params = new URLSearchParams()
      Object.entries(lastQuery).forEach(([k, v]) => {
        if (v.trim() !== '') params.set(k, v.trim())
      })
      const response = await api.get<SearchResponse>(`/api/branches/${branchId}/catalogue/search?${params.toString()}`)
      return response.data
    },
    enabled: searchEnabled,
  })

  const importMutation = useMutation({
    mutationFn: async () => {
      const idempotencyKey = `imp-${Date.now()}-${Math.random().toString(16).slice(2)}`
      let rows: unknown
      try {
        rows = JSON.parse(importForm.rowsJson)
      } catch {
        throw new Error('Rows JSON is invalid. Provide a JSON array.')
      }
      if (!Array.isArray(rows) || rows.length === 0) {
        throw new Error('Rows must be a non-empty JSON array.')
      }
      const payload = {
        source: importForm.source,
        source_ref: importForm.source_ref,
        format: 'json',
        rows,
      }
      const url = importForm.type === 'fitment'
        ? `/api/branches/${branchId}/catalogue/import-fitments`
        : `/api/branches/${branchId}/catalogue/import-oem`
      const response = await api.post<{ data: ImportSummary }>(url, payload, { headers: { 'Idempotency-Key': idempotencyKey } })
      return response.data.data
    },
    onSuccess: (summary) => {
      const details: string[] = []
      if (typeof summary.rows_created === 'number') details.push(`${summary.rows_created} created`)
      if (typeof summary.rows_skipped === 'number') details.push(`${summary.rows_skipped} skipped`)
      if (typeof summary.rows_received === 'number') details.push(`${summary.rows_received} received`)
      if (summary.idempotent_replay) details.push('idempotent replay')
      setFeedback({ type: 'success', text: `Import complete${details.length > 0 ? `: ${details.join(', ')}` : ''}.` })
      void queryClient.invalidateQueries({ queryKey: ['catalogue-search', branchId] })
      void queryClient.invalidateQueries({ queryKey: ['catalogue', branchId] })
      setImportForm((f) => ({ ...f, rowsJson: '' }))
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const onSearch = (e: FormEvent) => {
    e.preventDefault()
    setFeedback(null)
    const q = searchForm.q.trim()
    const make = searchForm.make.trim()
    const model = searchForm.model.trim()
    const year = searchForm.year.trim()
    const engine = searchForm.engine.trim()
    const oem = searchForm.oem.trim()
    if (!q && !make && !model && !year && !engine && !oem) {
      setFeedback({ type: 'error', text: 'Enter at least one search field (SKU/barcode, OEM, or vehicle make/model/year/engine).' })
      return
    }
    setLastQuery({ q, make, model, year, engine, oem })
  }

  const results = useMemo(() => searchQuery.data?.data ?? [], [searchQuery.data])

  if (meQuery.isLoading) return <AppShell><LoadingState text="Loading catalogue search..." /></AppShell>
  if (meQuery.isError) return <AppShell><ErrorState description={apiErrorMessage(meQuery.error)} /></AppShell>
  if (!canView) return <AppShell><PermissionDeniedState description="You do not have permission to view catalogue search." /></AppShell>

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Catalogue search</h1>
          <p className="text-sm text-muted-foreground">Search by exact SKU/barcode, OEM/cross-reference, or vehicle fitment (make/model/year/engine). Results show match type and source.</p>
        </div>

        {feedback && <div className={`rounded-md border px-3 py-2 text-sm ${feedback.type === 'error' ? 'border-destructive text-destructive' : 'border-green-600 text-green-700'}`} role={feedback.type === 'error' ? 'alert' : 'status'}>{feedback.text}</div>}

        <Card>
          <CardHeader><CardTitle>Search</CardTitle></CardHeader>
          <CardContent>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={onSearch}>
              <div>
                <Label htmlFor="cs-q">SKU or barcode (exact)</Label>
                <Input id="cs-q" value={searchForm.q} onChange={(e) => setSearchForm({ ...searchForm, q: e.target.value })} placeholder="e.g. BRK-PAD-001 or 1234567890123" />
              </div>
              <div>
                <Label htmlFor="cs-oem">OEM / cross-reference</Label>
                <Input id="cs-oem" value={searchForm.oem} onChange={(e) => setSearchForm({ ...searchForm, oem: e.target.value })} placeholder="e.g. 12345-ABC" />
              </div>
              <div>
                <Label htmlFor="cs-make">Vehicle make</Label>
                <Input id="cs-make" value={searchForm.make} onChange={(e) => setSearchForm({ ...searchForm, make: e.target.value })} placeholder="e.g. Toyota" />
              </div>
              <div>
                <Label htmlFor="cs-model">Vehicle model</Label>
                <Input id="cs-model" value={searchForm.model} onChange={(e) => setSearchForm({ ...searchForm, model: e.target.value })} placeholder="e.g. Corolla" />
              </div>
              <div>
                <Label htmlFor="cs-year">Year</Label>
                <Input id="cs-year" value={searchForm.year} onChange={(e) => setSearchForm({ ...searchForm, year: e.target.value })} placeholder="e.g. 2018" />
              </div>
              <div>
                <Label htmlFor="cs-engine">Engine</Label>
                <Input id="cs-engine" value={searchForm.engine} onChange={(e) => setSearchForm({ ...searchForm, engine: e.target.value })} placeholder="e.g. 1.8L" />
              </div>
              <div className="md:col-span-2 flex gap-2">
                <Button type="submit" disabled={searchQuery.isFetching}>Search</Button>
                <Button type="button" variant="outline" onClick={() => { setSearchForm({ q: '', make: '', model: '', year: '', engine: '', oem: '' }); setLastQuery({}); }}>
                  Clear
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Results ({results.length})</CardTitle></CardHeader>
          <CardContent>
            {searchQuery.isLoading && <LoadingState text="Searching..." />}
            {searchQuery.isError && <ErrorState description={apiErrorMessage(searchQuery.error)} />}
            {!searchQuery.isLoading && !searchQuery.isError && results.length === 0 && (
              <EmptyState title="No results" description="Try different search terms. Exact SKU/barcode always takes priority." />
            )}
            <div className="space-y-3">
              {results.map((r, idx) => (
                <div key={`${r.product_id}-${r.match_type}-${idx}`} className="rounded-md border p-3">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <div className="font-medium">{r.name}</div>
                      <div className="text-sm text-muted-foreground">SKU: {r.sku}{r.barcode ? ` · Barcode: ${r.barcode}` : ''}</div>
                      <div className="text-sm">Price: {formatMinor(r.selling_price_minor, r.currency_code)}</div>
                    </div>
                    <div className="text-right text-sm">
                      <div className="rounded-full border px-2 py-0.5 inline-block">{matchTypeLabel[r.match_type]}</div>
                      <div className="mt-1 text-xs text-muted-foreground max-w-xs break-words">{r.match_detail}</div>
                    </div>
                  </div>
                  <div className="mt-2 text-xs text-muted-foreground">Source: {r.source}</div>
                  {r.fitment && (
                    <div className="mt-2 text-xs">
                      <span className="font-medium">Fitment: </span>
                      {r.fitment.make} {r.fitment.model} {r.fitment.year_from ?? '?'}–{r.fitment.year_to ?? '?'} {r.fitment.engine ?? ''}
                    </div>
                  )}
                  {r.oem && (
                    <div className="mt-2 text-xs">
                      <span className="font-medium">OEM: </span>
                      {r.oem.oem_number}{r.oem.brand ? ` (${r.oem.brand})` : ''}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {canManage && (
          <Card>
            <CardHeader><CardTitle>Import fitment or OEM data (JSON)</CardTitle></CardHeader>
            <CardContent>
              <p className="mb-3 text-sm text-muted-foreground">
                Imports require explicit <code className="rounded bg-muted px-1">source</code> + <code className="rounded bg-muted px-1">source_ref</code> and are idempotent by <code className="rounded bg-muted px-1">Idempotency-Key</code> (auto-generated per submit). Only JSON is supported. Do not invent compatibility data.
              </p>
              <form
                className="space-y-3"
                onSubmit={(e) => {
                  e.preventDefault()
                  setFeedback(null)
                  importMutation.mutate()
                }}
              >
                <div className="grid gap-3 md:grid-cols-2">
                  <div>
                    <Label htmlFor="imp-source">Source (required)</Label>
                    <Input id="imp-source" required value={importForm.source} onChange={(e) => setImportForm({ ...importForm, source: e.target.value })} placeholder="e.g. Supplier catalog PDF" />
                  </div>
                  <div>
                    <Label htmlFor="imp-ref">Source ref (required)</Label>
                    <Input id="imp-ref" required value={importForm.source_ref} onChange={(e) => setImportForm({ ...importForm, source_ref: e.target.value })} placeholder="e.g. CAT-2026-01 p.12" />
                  </div>
                </div>
                <div>
                  <Label htmlFor="imp-type">Type</Label>
                  <select id="imp-type" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={importForm.type} onChange={(e) => setImportForm({ ...importForm, type: e.target.value as 'fitment' | 'oem' })}>
                    <option value="fitment">Vehicle fitments</option>
                    <option value="oem">OEM / cross-references</option>
                  </select>
                </div>
                <div>
                  <Label htmlFor="imp-rows">Rows JSON (array)</Label>
                  <textarea
                    id="imp-rows"
                    className="flex min-h-[140px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    required
                    value={importForm.rowsJson}
                    onChange={(e) => setImportForm({ ...importForm, rowsJson: e.target.value })}
                    placeholder={importForm.type === 'fitment'
                      ? '[{"product_id":"<ULID>","make":"Toyota","model":"Corolla","year_from":"2016","year_to":"2020","engine":"1.8L"}]'
                      : '[{"product_id":"<ULID>","oem_number":"12345-ABC","brand":"Denso"}]'}
                  />
                </div>
                <Button type="submit" disabled={importMutation.isPending}>
                  {importMutation.isPending ? 'Importing...' : 'Import'}
                </Button>
              </form>
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  )
}
