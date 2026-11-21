export type EmployeeStatus = 'hired' | 'active' | 'suspended' | 'terminated' | 'archived'

export type EmployeeUser = {
  id: number
  name: string
  email: string
  is_active: boolean
}

export type EmployeeSensitive = {
  national_id: string | null
  date_of_birth: string | null
  address: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  notes: string | null
}

export type Employee = {
  id: string
  employee_number: string
  first_name: string
  last_name: string
  full_name: string
  email: string | null
  phone: string | null
  job_title: string | null
  department: string | null
  employment_type: string
  hire_date: string | null
  termination_date: string | null
  status: EmployeeStatus
  user: EmployeeUser | null
  sensitive: EmployeeSensitive
  sensitive_redacted: boolean
  archived_at: string | null
  allowed_transitions: EmployeeStatus[]
  created_at: string | null
  updated_at: string | null
}

export type EmployeeListResponse = {
  data: Employee[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  statuses: Array<{ value: EmployeeStatus; label: string }>
  employment_types: string[]
}

export type EmployeeMutationResponse = {
  data: Employee
  allowed_transitions?: EmployeeStatus[]
}

export type LinkableUser = {
  id: number
  name: string
  email: string
  role: string
}

export type LinkableUsersResponse = {
  data: LinkableUser[]
}

export type EmployeeFormState = {
  first_name: string
  last_name: string
  email: string
  phone: string
  job_title: string
  department: string
  employment_type: string
  hire_date: string
  status: EmployeeStatus
  user_id: string
  national_id: string
  date_of_birth: string
  address: string
  emergency_contact_name: string
  emergency_contact_phone: string
  notes: string
}

export const emptyEmployeeForm: EmployeeFormState = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  job_title: '',
  department: '',
  employment_type: 'full_time',
  hire_date: new Date().toISOString().slice(0, 10),
  status: 'hired',
  user_id: '',
  national_id: '',
  date_of_birth: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  notes: '',
}

export const employeeStatusLabels: Record<EmployeeStatus, string> = {
  hired: 'Hired',
  active: 'Active',
  suspended: 'Suspended',
  terminated: 'Terminated',
  archived: 'Archived',
}

export const employmentTypeLabels: Record<string, string> = {
  full_time: 'Full time',
  part_time: 'Part time',
  contract: 'Contract',
  casual: 'Casual',
  internship: 'Internship',
}
