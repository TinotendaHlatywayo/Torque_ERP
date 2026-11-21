export type TaskUser = {
  id: string | number
  name: string
  email?: string
}

export type TaskCompletion = {
  id: string
  actor: TaskUser | null
  note: string | null
  created_at: string | null
}

export type TaskEvent = {
  id: string
  event_type: string
  payload: Record<string, unknown> | null
  created_at: string | null
}

export type TaskRecord = {
  id: string
  title: string
  description: string | null
  assigned_to: string | number | null
  assignee: TaskUser | null
  created_by: string | number
  creator: TaskUser | null
  status: string
  priority: string
  due_at: string | null
  completed_at: string | null
  event_type: string | null
  reference_type: string | null
  reference_id: string | null
  completions: TaskCompletion[]
  events: TaskEvent[]
  counts: { completions: number; events: number; notifications: number }
  created_at: string | null
  updated_at: string | null
}

export type NotificationRecord = {
  id: string
  task_id: string | null
  task: { id: string; title: string; status: string } | null
  event_type: string
  channel: string
  title: string
  body: string | null
  status: string
  attempts: number
  last_error: string | null
  sent_at: string | null
  failed_at: string | null
  read_at: string | null
  created_at: string | null
}

export type NotificationPreference = {
  event_type: string
  channel: string
  is_enabled: boolean
}

export type ListResponse<T> = { data: T[] }
