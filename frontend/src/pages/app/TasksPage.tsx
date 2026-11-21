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
  ListResponse,
  NotificationPreference,
  NotificationRecord,
  TaskRecord,
  TaskUser,
} from '@/lib/tasks'

type Tab = 'tasks' | 'notifications' | 'preferences'
type Feedback = { type: 'error' | 'success'; text: string }

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm'
const emptyTaskForm = { title: '', description: '', assigned_to: '', priority: 'normal', due_at: '' }

export default function TasksPage() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id ?? ''
  const canView = branch?.permissions.includes('branch.tasks.view') ?? false
  const canManage = branch?.permissions.includes('branch.tasks.manage') ?? false

  const [tab, setTab] = useState<Tab>('tasks')
  const [feedback, setFeedback] = useState<Feedback | null>(null)
  const [taskForm, setTaskForm] = useState(emptyTaskForm)
  const [selectedTaskId, setSelectedTaskId] = useState<string | null>(null)

  const refresh = async (keys: string[][]) => {
    await Promise.all(keys.map((key) => queryClient.invalidateQueries({ queryKey: key })))
  }

  const tasksQuery = useQuery({
    queryKey: ['tasks', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<TaskRecord>>(`/api/branches/${branchId}/tasks`)
      return response.data
    },
    enabled: Boolean(branchId && canView),
  })

  const usersQuery = useQuery({
    queryKey: ['branch-users', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<TaskUser>>(`/api/branches/${branchId}/users`)
      return response.data
    },
    enabled: Boolean(branchId && canManage),
  })

  const taskQuery = useQuery({
    queryKey: ['task', branchId, selectedTaskId],
    queryFn: async () => {
      const response = await api.get<{ data: TaskRecord }>(`/api/branches/${branchId}/tasks/${selectedTaskId!}`)
      return response.data
    },
    enabled: Boolean(branchId && canView && selectedTaskId),
  })

  const notificationsQuery = useQuery({
    queryKey: ['notifications', branchId],
    queryFn: async () => {
      const response = await api.get<ListResponse<NotificationRecord>>(`/api/branches/${branchId}/notifications`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'notifications'),
  })

  const preferencesQuery = useQuery({
    queryKey: ['notification-preferences', branchId],
    queryFn: async () => {
      const response = await api.get<{ data: NotificationPreference[] }>(`/api/branches/${branchId}/notification-preferences`)
      return response.data
    },
    enabled: Boolean(branchId && canView && tab === 'preferences'),
  })

  const createMutation = useMutation({
    mutationFn: async () => {
      await api.post(`/api/branches/${branchId}/tasks`, {
        title: taskForm.title,
        description: taskForm.description || null,
        assigned_to: taskForm.assigned_to ? Number(taskForm.assigned_to) : null,
        priority: taskForm.priority,
        due_at: taskForm.due_at ? new Date(taskForm.due_at).toISOString() : null,
      })
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Task created.' })
      setTaskForm(emptyTaskForm)
      await refresh([['tasks', branchId]])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const completeMutation = useMutation({
    mutationFn: async (taskId: string) => {
      await api.post(`/api/branches/${branchId}/tasks/${taskId}/complete`, { note: 'Completed from task centre' })
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Task completed.' })
      await refresh([['tasks', branchId], ['task', branchId, selectedTaskId ?? ''], ['notifications', branchId]])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const readMutation = useMutation({
    mutationFn: async (notificationId: string) => {
      await api.post(`/api/branches/${branchId}/notifications/${notificationId}/read`)
    },
    onSuccess: async () => refresh([['notifications', branchId]]),
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const preferencesMutation = useMutation({
    mutationFn: async (preferences: NotificationPreference[]) => {
      await api.put(`/api/branches/${branchId}/notification-preferences`, { preferences })
    },
    onSuccess: async () => {
      setFeedback({ type: 'success', text: 'Notification preferences saved.' })
      await refresh([['notification-preferences', branchId]])
    },
    onError: (error) => setFeedback({ type: 'error', text: apiErrorMessage(error) }),
  })

  const submit = (event: FormEvent, action: () => void) => {
    event.preventDefault()
    setFeedback(null)
    action()
  }

  if (meQuery.isLoading) return <AppShell><LoadingState text="Loading tasks..." /></AppShell>
  if (meQuery.isError) return <AppShell><ErrorState description={apiErrorMessage(meQuery.error)} /></AppShell>
  if (!canView) return <AppShell><PermissionDeniedState description="You do not have permission to view tasks." /></AppShell>

  const tasks = tasksQuery.data?.data ?? []
  const users = usersQuery.data?.data ?? []
  const notifications = notificationsQuery.data?.data ?? []
  const preferences = preferencesQuery.data?.data ?? []
  const selectedTask = taskQuery.data?.data ?? null

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Tasks and notifications</h1>
            <p className="text-sm text-muted-foreground">Operational work, task events and delivery state</p>
          </div>
          <div className="flex gap-2">
            {(['tasks', 'notifications', 'preferences'] as Tab[]).map((item) => (
              <Button key={item} variant={tab === item ? 'default' : 'outline'} aria-pressed={tab === item} onClick={() => setTab(item)}>
                {item}
              </Button>
            ))}
          </div>
        </div>
        {feedback && <div className={`rounded-md border px-3 py-2 text-sm ${feedback.type === 'error' ? 'border-destructive text-destructive' : 'border-green-600 text-green-700'}`} role={feedback.type === 'error' ? 'alert' : 'status'}>{feedback.text}</div>}

        {tab === 'tasks' && (
          <div className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
              <CardHeader><CardTitle>Task queue</CardTitle></CardHeader>
              <CardContent>
                {tasksQuery.isLoading && <LoadingState text="Loading task queue..." />}
                {tasksQuery.isError && <ErrorState description={apiErrorMessage(tasksQuery.error)} />}
                {tasks.length === 0 && !tasksQuery.isLoading && <EmptyState title="No tasks" description="Create the first operational task." />}
                <div className="space-y-2">
                  {tasks.map((task) => (
                    <button key={task.id} type="button" onClick={() => setSelectedTaskId(task.id)} className="flex w-full items-center justify-between rounded-md border p-3 text-left hover:bg-accent">
                      <div>
                        <div className="font-medium">{task.title}</div>
                        <div className="text-xs text-muted-foreground">{task.assignee?.name ?? 'Unassigned'} · {task.priority} · {task.counts.events} events · {task.counts.notifications} notifications</div>
                      </div>
                      <span className="rounded-full border px-2 py-0.5 text-xs">{task.status}</span>
                    </button>
                  ))}
                </div>
              </CardContent>
            </Card>
            <div className="space-y-6">
              {canManage && (
                <Card>
                  <CardHeader><CardTitle>New task</CardTitle></CardHeader>
                  <CardContent>
                    <form className="space-y-3" onSubmit={(event) => submit(event, () => createMutation.mutate())}>
                      <div><Label>Title<Input required value={taskForm.title} onChange={(event) => setTaskForm({ ...taskForm, title: event.target.value })} /></Label></div>
                      <div><Label>Description<Input value={taskForm.description} onChange={(event) => setTaskForm({ ...taskForm, description: event.target.value })} /></Label></div>
                      <div>
                        <Label>Assignee
                        <select className={selectClass} value={taskForm.assigned_to} onChange={(event) => setTaskForm({ ...taskForm, assigned_to: event.target.value })}>
                          <option value="">Unassigned</option>
                          {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                        </Label>
                      </div>
                      <div><Label>Priority<select className={selectClass} value={taskForm.priority} onChange={(event) => setTaskForm({ ...taskForm, priority: event.target.value })}><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></Label></div>
                      <div><Label>Due<Input type="datetime-local" value={taskForm.due_at} onChange={(event) => setTaskForm({ ...taskForm, due_at: event.target.value })} /></Label></div>
                      <Button type="submit" disabled={createMutation.isPending}>Create task</Button>
                    </form>
                  </CardContent>
                </Card>
              )}
              {selectedTask && (
                <Card>
                  <CardHeader><CardTitle>{selectedTask.title}</CardTitle></CardHeader>
                  <CardContent className="space-y-3 text-sm">
                    <div>Status: {selectedTask.status} · Priority: {selectedTask.priority}</div>
                    <div>Completions: {selectedTask.completions.length} · Events: {selectedTask.events.length}</div>
                    {canManage && selectedTask.status !== 'completed' && selectedTask.status !== 'cancelled' && (
                      <Button onClick={() => completeMutation.mutate(selectedTask.id)} disabled={completeMutation.isPending}>Complete</Button>
                    )}
                    <div className="space-y-1">
                      {selectedTask.events.map((event) => <div key={event.id} className="border-b py-1">{event.event_type} · {event.created_at}</div>)}
                    </div>
                  </CardContent>
                </Card>
              )}
            </div>
          </div>
        )}

        {tab === 'notifications' && (
          <Card>
            <CardHeader><CardTitle>Delivery state</CardTitle></CardHeader>
            <CardContent>
              {notificationsQuery.isLoading && <LoadingState text="Loading notifications..." />}
              {notifications.length === 0 && !notificationsQuery.isLoading && <EmptyState title="No notifications" description="Task notifications will appear here." />}
              <div className="space-y-2">
                {notifications.map((notification) => (
                  <div key={notification.id} className="flex items-center justify-between rounded-md border p-3">
                    <div>
                      <div className="font-medium">{notification.title}</div>
                      <div className="text-xs text-muted-foreground">{notification.channel} · {notification.status} · {notification.attempts} attempts{notification.last_error ? ` · ${notification.last_error}` : ''}</div>
                    </div>
                    {!notification.read_at && <Button variant="outline" onClick={() => readMutation.mutate(notification.id)}>Mark read</Button>}
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {tab === 'preferences' && (
          <Card>
            <CardHeader><CardTitle>Notification preferences</CardTitle></CardHeader>
            <CardContent>
              {preferencesQuery.isLoading && <LoadingState text="Loading preferences..." />}
              <div className="space-y-2">
                {preferences.map((preference) => (
                  <label key={`${preference.event_type}-${preference.channel}`} className="flex items-center justify-between rounded-md border p-3 text-sm">
                    <span>{preference.event_type} · {preference.channel}</span>
                    <input
                      type="checkbox"
                      checked={preference.is_enabled}
                      onChange={(event) => {
                        const next = preferences.map((item) => item.event_type === preference.event_type && item.channel === preference.channel
                          ? { ...item, is_enabled: event.target.checked }
                          : item)
                        preferencesMutation.mutate(next)
                      }}
                    />
                  </label>
                ))}
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  )
}
