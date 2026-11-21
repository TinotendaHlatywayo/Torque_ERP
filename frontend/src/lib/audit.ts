import api from '@/lib/api'

export type AuditLog = {
  id: number | string
  event: string
  actor?: { id?: number | string; name?: string; email?: string } | null
  actor_name?: string | null
  actor_id?: number | string | null
  subject_type?: string | null
  subject_id?: number | string | null
  branch_id?: number | string | null
  branch?: { id?: number | string; name?: string } | null
  created_at: string
  detail?: unknown
  details?: unknown
  old_values?: unknown
  new_values?: unknown
  metadata?: unknown
  [key: string]: unknown
}

export type PaginatedAuditLogs = {
  data: AuditLog[]
  meta: {
    current_page: number
    last_page: number
    total: number
    per_page?: number
  }
}

export type AuditLogFilters = {
  page?: number
  per_page?: number
  event?: string
  actor_id?: string
}

export async function fetchBranchAuditLogs(branchId: string, filters: AuditLogFilters) {
  const response = await api.get<PaginatedAuditLogs>(`/api/branches/${branchId}/audit-logs`, {
    params: {
      page: filters.page ?? 1,
      per_page: filters.per_page ?? 20,
      event: filters.event || undefined,
      actor_id: filters.actor_id || undefined,
    },
  })
  return response.data
}

export async function fetchPlatformAuditLogs(filters: AuditLogFilters) {
  const response = await api.get<PaginatedAuditLogs>('/api/platform/audit-logs', {
    params: {
      page: filters.page ?? 1,
      per_page: filters.per_page ?? 20,
      event: filters.event || undefined,
      actor_id: filters.actor_id || undefined,
    },
  })
  return response.data
}

export function auditActor(log: AuditLog) {
  return log.actor?.name || log.actor?.email || log.actor_name || (log.actor_id != null ? `User #${log.actor_id}` : 'System')
}

export function auditSubject(log: AuditLog) {
  const type = log.subject_type ?? '—'
  const id = log.subject_id != null ? `#${log.subject_id}` : ''
  return id ? `${type} ${id}` : type
}

export function auditBranch(log: AuditLog) {
  return log.branch?.name || (log.branch_id != null ? `Branch #${log.branch_id}` : '—')
}

export function auditDetail(log: AuditLog) {
  const detail = log.detail ?? log.details ?? log.metadata ?? (log.old_values !== undefined || log.new_values !== undefined ? { old_values: log.old_values, new_values: log.new_values } : undefined)
  return detail
}

function csvValue(value: unknown) {
  const str = value == null ? '' : typeof value === 'string' ? value : JSON.stringify(value)
  return `"${str.replace(/"/g, '""')}"`
}

export function exportAuditLogsCsv(logs: AuditLog[], filename: string) {
  const headers = ['id', 'event', 'actor', 'subject', 'branch', 'created_at', 'detail']
  const rows = logs.map((log) => [
    log.id,
    log.event,
    auditActor(log),
    auditSubject(log),
    auditBranch(log),
    log.created_at,
    auditDetail(log),
  ])
  const csv = [headers, ...rows].map((row) => row.map(csvValue).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
