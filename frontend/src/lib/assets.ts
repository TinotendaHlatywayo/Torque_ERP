export type AssetActor = { id: number; name: string } | null

export type AssetMaintenance = {
  id: string
  asset_id: string
  performed_date: string
  cost_minor: number
  currency_code: string
  provider: string
  description: string
  expense_id: string | null
  payment_id: string | null
  expense: { id: string; description: string } | null
  payment: { id: string; reference: string | null } | null
  actor: AssetActor
  created_at: string | null
}

export type AssetRecord = {
  id: string
  name: string
  description: string | null
  acquisition_date: string
  cost_minor: number
  currency_code: string
  location: string
  custodian: string
  status: 'active' | 'disposed'
  source_type: string | null
  source_id: string | null
  disposal_date: string | null
  disposal_proceeds_minor: number | null
  disposal_notes: string | null
  actor: AssetActor
  maintenance_count: number | null
  maintenances: AssetMaintenance[]
  created_at: string | null
}

export type DepreciationEstimate = {
  asset_id: string
  asset_name: string
  status: string
  as_of: string
  effective_as_of: string
  label: 'ESTIMATE'
  accounting_use: 'BLOCKED'
  policy_status: string
  notice: string
  cost_minor: number
  residual_minor: number
  depreciable_minor: number
  currency_code: string
  useful_life_years: number
  useful_life_months: number
  months_elapsed: number
  monthly_estimate_minor: number
  accumulated_estimate_minor: number
  book_value_estimate_minor: number
  disposal_date: string | null
  posted: boolean
  financial_movement_posted: boolean
}

export type FinancialEffect = {
  cost_minor: number
  accumulated_estimate_minor: number
  book_value_estimate_minor: number
  proceeds_minor: number
  estimated_gain_loss_minor: number
  currency_code: string
  label: 'ESTIMATE'
  accounting_use: 'BLOCKED'
  notice: string
  automatic_entries_posted: number
}

export type DataResponse<T> = { data: T }

export type ListResponse<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type EstimateReport = {
  data: DepreciationEstimate[]
  meta: {
    as_of: string
    label: 'ESTIMATE'
    accounting_use: 'BLOCKED'
    policy_status: string
    notice: string
    posted: boolean
    count: number
  }
}

export type DisposalResponse = {
  data: { asset: AssetRecord; financial_effect: FinancialEffect }
}
