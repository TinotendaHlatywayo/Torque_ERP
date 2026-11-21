export type LeaveType = {
  id: string
  name: string
  code: string
  is_paid: boolean
  requires_approval: boolean
  max_days_per_year: string | null
  counts_weekends: boolean
  is_active: boolean
}

export type HrEmployeeRef = {
  id: string
  employee_number: string
  full_name: string
}

export type LeaveAttachment = {
  id: string
  original_name: string
  mime_type: string
  size_bytes: number
  created_at: string | null
}

export type LeaveRequest = {
  id: string
  employee: HrEmployeeRef | null
  leave_type: {
    id: string
    name: string
    code: string
    is_paid: boolean
    requires_approval: boolean
  } | null
  start_date: string | null
  end_date: string | null
  days_requested: string
  reason: string | null
  status: 'submitted' | 'approved' | 'rejected' | 'cancelled'
  decided_by: { id: number; name: string } | null
  decided_at: string | null
  decision_note: string | null
  attachments: LeaveAttachment[]
  created_at: string | null
}

export type Paginated<T> = {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type LeaveTypeListResponse = { data: LeaveType[] }
export type LeaveRequestListResponse = Paginated<LeaveRequest>
export type LeaveRequestMutationResponse = { data: LeaveRequest }

export type AttendanceRecord = {
  id: string
  employee: HrEmployeeRef | null
  work_date: string | null
  status: string
  scheduled_minutes: number
  worked_minutes: number
  overtime_minutes: number
  notes: string | null
}

export type AttendanceListResponse = Paginated<AttendanceRecord> & { statuses: string[] }
export type AttendanceMutationResponse = { data: AttendanceRecord }

export type DisciplinaryRecord = {
  id: string
  employee: HrEmployeeRef | null
  incident_date: string | null
  category: string
  severity: string
  summary: string
  details: string | null
  status: 'open' | 'resolved' | 'appealed' | 'closed'
  action_taken: string | null
  resolved_at: string | null
  resolution_note: string | null
  attachments: LeaveAttachment[]
  allowed_transitions: string[]
}

export type DisciplinaryListResponse = Paginated<DisciplinaryRecord> & {
  categories: string[]
  severities: string[]
  statuses: string[]
}
export type DisciplinaryMutationResponse = { data: DisciplinaryRecord; allowed_transitions?: string[] }

export type EmployeeOption = {
  id: string
  employee_number: string
  full_name: string
  status: string
}

export type EmployeeOptionListResponse = Paginated<EmployeeOption>
