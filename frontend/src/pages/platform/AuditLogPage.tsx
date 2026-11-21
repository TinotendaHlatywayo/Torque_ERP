import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AuditLogTable } from '@/components/audit/AuditLogTable'
import { LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import { fetchPlatformAuditLogs } from '@/lib/audit'

export default function PlatformAuditLogPage() {
  const meQuery = useMe()
  const canView = Boolean(meQuery.data?.user.is_platform_admin) || (meQuery.data?.platform_permissions.includes('platform.audit.view') ?? false)

  const [page, setPage] = useState(1)
  const [event, setEvent] = useState('')
  const [actorId, setActorId] = useState('')
  const [applied, setApplied] = useState({ event: '', actorId: '' })

  const query = useQuery({
    queryKey: ['audit-logs', 'platform', page, applied],
    queryFn: () => fetchPlatformAuditLogs({ page, per_page: 20, event: applied.event, actor_id: applied.actorId }),
    enabled: canView,
  })

  if (meQuery.isLoading) return <div className="min-h-screen bg-background p-8"><LoadingState text="Loading platform access..." /></div>
  if (!meQuery.data || !canView) return <div className="min-h-screen bg-background p-8"><PermissionDeniedState description="You do not have platform audit access." /></div>

  return (
    <div className="min-h-screen bg-background p-8">
      <div className="max-w-7xl mx-auto">
        <AuditLogTable
          title="Platform audit log"
          description="Cross-branch and platform administration events"
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
          exportName={`platform-audit-page-${page}.csv`}
        />
      </div>
    </div>
  )
}
