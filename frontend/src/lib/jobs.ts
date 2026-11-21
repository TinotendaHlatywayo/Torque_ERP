export type JobUser = {
  id: string | number
  name: string
}

export type JobCustomer = {
  id: string
  name: string
}

export type JobService = {
  id: string
  code: string
  name: string
  description: string | null
  default_hours: string
  default_rate_minor: number
  currency_code: string
  is_active: boolean
}

export type JobLabourLine = {
  id: string
  job_id: string
  workshop_service_id: string | null
  service: JobService | null
  description: string
  hours: string
  rate_minor: number
  amount_minor: number
  created_at: string | null
}

export type JobPartLine = {
  id: string
  job_id: string
  product_id: string
  product: { id: string; name: string; sku: string } | null
  unit_id: string
  unit: { id: string; code: string } | null
  quantity: string
  unit_price_minor: number
  amount_minor: number
  stock_movement_id: string | null
  idempotency_key: string | null
  created_at: string | null
}

export type JobInvoice = {
  id: string
  sale_number: string
  total_minor: number
  payment_status: string
}

export type JobRecord = {
  id: string
  job_number: string
  customer_id: string
  customer: JobCustomer | null
  technician_id: string | number | null
  technician: JobUser | null
  vehicle_make: string
  vehicle_model: string
  vehicle_registration: string | null
  vehicle_year: string | null
  vin: string | null
  complaint: string
  diagnosis: string | null
  status: 'draft' | 'estimated' | 'approved' | 'in_progress' | 'completed' | 'cancelled'
  previous_status: string | null
  estimate_minor: number
  approved_at: string | null
  approved_by: string | number | null
  approver: JobUser | null
  approval_reference: string | null
  approval_channel: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  sale_id: string | null
  invoice: JobInvoice | null
  labour_lines: JobLabourLine[]
  part_lines: JobPartLine[]
  line_counts: { labour: number; parts: number }
  created_at: string | null
  updated_at: string | null
}

export type ListResponse<T> = { data: T[] }

export type JobActionResponse = { data: JobRecord }

export type JobPartResponse = {
  data: JobPartLine
  idempotent_replay: boolean
}
