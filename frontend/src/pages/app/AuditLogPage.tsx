import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { AuditLogTable } from '@/components/audit/AuditLogTable'
import { LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import { fetchBranchAuditLogs } from '@/lib/audit'

export default function AuditLogPage() {
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.audit.view') ?? false

  const [page, setPage] = useState(1)
  const [event, setEvent] = useState('')
  const [actorId, setActorId] = useState('')
  const [applied, setApplied] = useState({ event: '', actorId: '' })

  const query = useQuery({
    queryKey: ['audit-logs', 'branch', branchId, page, applied],
    queryFn: () => fetchBranchAuditLogs(branchId!, { page, per_page: 20, event: applied.event, actor_id: applied.actorId }),
    enabled: Boolean(branchId && canView),
  })

  if (!meQuery.data) return <AppShell><LoadingState text="Loading branch access..." /></AppShell>
  if (!branchId || !canView) return <AppShell><PermissionDeniedState description="Your role does not include permission to view branch audit logs." /></AppShell>

  return (
    <AppShell>
      <AuditLogTable
        title="Audit log"
        description={`${branch.name} — branch activity and system events`}
        data={query.data}
        isLoading={query.isLoading}
        isError={query.isError}
        error={query.error}
        onRetry={() => void query.refetch()}
        page={page}
        event={event}
        actorId={actorId}
        onPageChange={setPage}
        onEventChange={setEvent}
        onActorIdChange={setActorId}
        onApplyFilters={() => { setPage(1); setApplied({ event, actorId }) }}
        exportName={`branch-audit-page-${page}.csv`}
      />
    </AppShell>
  )
}
