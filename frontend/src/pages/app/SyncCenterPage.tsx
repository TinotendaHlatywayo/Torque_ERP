import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'

export default function SyncCenterPage() {
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const [deviceId, setDeviceId] = useState('device-1')
  const [online, setOnline] = useState(navigator.onLine)

  window.addEventListener('online', () => setOnline(true))
  window.addEventListener('offline', () => setOnline(false))

  const statusQuery = useQuery({
    queryKey: ['sync-status', branchId, deviceId],
    queryFn: async () => {
      const response = await api.get(`/api/branches/${branchId}/sync/status`, {
        params: { device_id: deviceId },
      })
      return response.data.data as Array<{
        operation_id: string
        operation_type: string
        status: string
        client_time: string | null
        server_time: string | null
        attempt_count: number
        error: string | null
        created_at: string | null
      }>
    },
    enabled: Boolean(branchId),
    refetchInterval: 10000,
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

  const operations = statusQuery.data ?? []
  const queuedCount = operations.filter((op) => op.status === 'queued').length
  const lastSync = operations
    .map((op) => op.server_time)
    .filter(Boolean)
    .sort()
    .pop()

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Sync Center</h1>
          <p className="text-muted-foreground">{branch.name} ({branch.code}) — device sync status</p>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader><CardTitle className="text-base">Connection</CardTitle></CardHeader>
            <CardContent>{online ? 'Online' : 'Offline'}</CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">Queued operations</CardTitle></CardHeader>
            <CardContent>{queuedCount}</CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">Last successful sync</CardTitle></CardHeader>
            <CardContent>{lastSync ?? 'Never'}</CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Device</CardTitle>
          </CardHeader>
          <CardContent>
            <label className="mb-2 block text-sm font-medium" htmlFor="device-id">Device ID</label>
            <input
              id="device-id"
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={deviceId}
              onChange={(event) => setDeviceId(event.target.value)}
            />
          </CardContent>
        </Card>

        {statusQuery.isLoading && <LoadingState text="Loading sync status..." />}
        {statusQuery.isError && (
          <ErrorState
            title="Sync status could not be loaded"
            description={apiErrorMessage(statusQuery.error)}
            retry={() => void statusQuery.refetch()}
          />
        )}

        {operations.length === 0 && !statusQuery.isLoading && (
          <EmptyState title="No operations" description="Queued operations will appear here." />
        )}

        {operations.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Operations</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="border-b text-muted-foreground">
                    <tr>
                      <th className="py-3 pr-4 font-medium">Operation</th>
                      <th className="py-3 pr-4 font-medium">Type</th>
                      <th className="py-3 pr-4 font-medium">Status</th>
                      <th className="py-3 pr-4 font-medium">Client time</th>
                      <th className="py-3 font-medium">Error</th>
                    </tr>
                  </thead>
                  <tbody>
                    {operations.map((operation) => (
                      <tr key={operation.operation_id} className="border-b last:border-0">
                        <td className="py-3 pr-4">{operation.operation_id}</td>
                        <td className="py-3 pr-4">{operation.operation_type}</td>
                        <td className="py-3 pr-4">{operation.status}</td>
                        <td className="py-3 pr-4">{operation.client_time ?? '-'}</td>
                        <td className="py-3">{operation.error ?? '-'}</td>
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
