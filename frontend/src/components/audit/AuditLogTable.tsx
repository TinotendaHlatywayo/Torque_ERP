import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/StateComponents'
import { auditActor, auditBranch, auditDetail, auditSubject, exportAuditLogsCsv, type PaginatedAuditLogs } from '@/lib/audit'
import { apiErrorMessage } from '@/lib/apiErrors'

type Props = {
  title: string
  description: string
  data: PaginatedAuditLogs | undefined
  isLoading: boolean
  isError: boolean
  error: unknown
  onRetry: () => void
  page: number
  event: string
  actorId: string
  onPageChange: (page: number) => void
  onEventChange: (value: string) => void
  onActorIdChange: (value: string) => void
  onApplyFilters: () => void
  exportName: string
}

export function AuditLogTable({
  title, description, data, isLoading, isError, error, onRetry,
  page, event, actorId, onPageChange, onEventChange, onActorIdChange, onApplyFilters, exportName,
}: Props) {
  const [expanded, setExpanded] = useState<Record<string, boolean>>({})
  const logs = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">{title}</h1>
        <p className="text-muted-foreground">{description}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Filters</CardTitle>
        </CardHeader>
        <CardContent>
          <form className="grid gap-4 md:grid-cols-[1fr_1fr_auto]" onSubmit={(e) => { e.preventDefault(); onApplyFilters() }}>
            <div className="space-y-2">
              <Label htmlFor="audit-event">Event</Label>
              <Input id="audit-event" value={event} onChange={(e) => onEventChange(e.target.value)} placeholder="e.g. sale.posted" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="audit-actor">Actor ID</Label>
              <Input id="audit-actor" value={actorId} onChange={(e) => onActorIdChange(e.target.value)} placeholder="User ID" inputMode="numeric" />
            </div>
            <div className="flex items-end gap-2">
              <Button type="submit" variant="outline" className="min-h-11">Apply</Button>
              <Button type="button" className="min-h-11" disabled={logs.length === 0} onClick={() => exportAuditLogsCsv(logs, exportName)}>
                Export CSV
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Events</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading && <LoadingState text="Loading audit logs..." />}
          {isError && <ErrorState title="Audit logs could not be loaded" description={apiErrorMessage(error)} retry={onRetry} />}
          {!isLoading && !isError && logs.length === 0 && <EmptyState title="No audit events" description="Events will appear here when activity is recorded." />}
          {!isLoading && !isError && logs.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[900px] text-left text-sm">
                <thead className="border-b text-xs uppercase tracking-wide text-muted-foreground">
                  <tr>
                    <th className="py-3 pr-4" scope="col">Event</th>
                    <th className="py-3 pr-4" scope="col">Actor</th>
                    <th className="py-3 pr-4" scope="col">Subject</th>
                    <th className="py-3 pr-4" scope="col">Branch</th>
                    <th className="py-3 pr-4" scope="col">Created</th>
                    <th className="py-3" scope="col">Detail</th>
                  </tr>
                </thead>
                <tbody>
                  {logs.map((log) => {
                    const key = String(log.id)
                    const detail = auditDetail(log)
                    const open = Boolean(expanded[key])
                    return (
                      <tr key={key} className="border-b align-top last:border-0">
                        <td className="py-3 pr-4 font-medium">{log.event}</td>
                        <td className="py-3 pr-4">{auditActor(log)}</td>
                        <td className="py-3 pr-4">{auditSubject(log)}</td>
                        <td className="py-3 pr-4">{auditBranch(log)}</td>
                        <td className="py-3 pr-4 whitespace-nowrap">{new Date(log.created_at).toLocaleString()}</td>
                        <td className="py-3">
                          {detail !== undefined ? (
                            <div>
                              <Button
                                type="button"
                                variant="outline"
                                className="min-h-11"
                                aria-expanded={open}
                                aria-controls={`audit-detail-${key}`}
                                onClick={() => setExpanded((c) => ({ ...c, [key]: !c[key] }))}
                              >
                                {open ? 'Hide' : 'View'}
                              </Button>
                              {open && (
                                <pre id={`audit-detail-${key}`} className="mt-2 max-w-xl overflow-auto rounded-md bg-muted p-3 text-xs whitespace-pre-wrap break-words">
                                  {JSON.stringify(detail, null, 2)}
                                </pre>
                              )}
                            </div>
                          ) : <span className="text-muted-foreground">—</span>}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
          {meta && meta.last_page > 1 && (
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-sm">
              <p className="text-muted-foreground">Page {meta.current_page} of {meta.last_page} · {meta.total} total</p>
              <div className="flex gap-2">
                <Button type="button" variant="outline" className="min-h-11" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>Previous</Button>
                <Button type="button" variant="outline" className="min-h-11" disabled={page >= meta.last_page} onClick={() => onPageChange(page + 1)}>Next</Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
