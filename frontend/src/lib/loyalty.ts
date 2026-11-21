export type LoyaltyConfig = {
  inactivity_days: number
  min_qualifying_minor: number
  min_qualifying_sales: number
  loyalty_discount_percent: number
  combined_approval_threshold_percent: number
  marketing_channels: string[]
  channel_integration: string
  updated_at: string | null
}

export type LoyaltyQualification = {
  id: string
  customer_id: string
  customer_name: string | null
  currency_code: string
  qualifying_sale_count: number
  gross_sales_minor: number
  returned_minor: number
  net_qualifying_minor: number
  last_purchase_at: string | null
  qualifies: boolean
  is_inactive: boolean
  marketing_channel: string | null
  calculated_at: string | null
}

export type WinbackCandidate = {
  customer_id: string
  name: string | null
  marketing_channel: string | null
  last_purchase_at: string | null
  days_inactive: number
  net_qualifying_minor: number
  qualifying_sale_count: number
  currency_code: string
}

export type WinbackExportStub = {
  delivery_status: string
  channel_integration: string
  reason: string
  contact_disposition: string
  generated_at: string
  candidates: Array<Pick<WinbackCandidate, 'customer_id' | 'marketing_channel' | 'days_inactive'>>
}

export type RecalculationResult = {
  customers_processed: number
  sale_events: number
  inactivity_days: number
  calculated_at: string
}

export type DataResponse<T> = { data: T }
export type ListResponse<T> = { data: T[] }
