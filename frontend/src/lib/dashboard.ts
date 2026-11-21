export type DataQuality = {
  status: 'PASS' | 'WARNING'
  warnings: Array<{ section: string; code: string; message: string }>
}

export type ReorderSuggestion = {
  product_id: string
  sku: string
  name: string
  unit_code: string
  quantity_on_hand: string
  quantity_reserved: string
  quantity_available: string
  units_sold_in_window: number
  average_daily_sales_units: number
  days_of_cover_on_hand: number | null
  lead_time_days: number
  days_of_cover: number
  reorder_point_units: number
  suggested_order_units: number
  needs_reorder: boolean
  urgency: 'reorder_now' | 'plan_reorder'
}

export type AgeingBucket = {
  product_count: number
  stock_value_minor: number
}

export type AgeingItem = {
  product_id: string
  sku: string
  name: string
  quantity_on_hand: string
  stock_value_minor: number
  last_movement_at: string | null
  age_days: number | null
  classification: 'active' | 'slow_moving' | 'dead_stock' | 'no_stock' | null
  status: string
}

export type AbcItem = {
  product_id: string
  sku: string | null
  name: string | null
  revenue_minor: number
  units_sold: number
  share_percent: number
  cumulative_percent: number
  class: 'A' | 'B' | 'C'
}

export type CashForecast = {
  label: 'FORECAST'
  authoritative_finance_statement: boolean
  as_of: string
  history_window_days: number
  horizon_days: number
  assumptions: string[]
  beginning_cash_minor: number
  average_daily_inflow_minor: number | null
  average_daily_outflow_minor: number | null
  projected_inflow_minor: number | null
  projected_outflow_minor: number | null
  known_bills_due_minor: number
  cash_target_minor: number
  forecast_cash_position_minor: number | null
  series: Array<{ day: number; projected_cash_minor: number }>
  data_quality: DataQuality
}

export type FinanceMetrics = {
  as_of: string
  money_in_minor: number
  money_out_minor: number
  invoiced_revenue_minor: number
  expenses_incurred_minor: number
  profit_basis_minor: number
  money_owed_to_us_minor: number
  bills_to_pay_minor: number
  inventory_value_minor: number
  cash_position_minor: number
  currency_code: string
}

export type DashboardV2Data = {
  as_of: string
  currency_code: string
  capabilities: { inventory: boolean; finance: boolean }
  windows: Record<string, Record<string, number>>
  finance_metrics: FinanceMetrics | null
  inventory_intelligence: {
    reorder: {
      sales_window_days: number
      lead_time_days: number
      days_of_cover: number
      products_without_history: number
      needs_reorder_count: number
      suggestions: ReorderSuggestion[]
      data_quality: DataQuality
    }
    ageing: {
      as_of: string
      slow_moving_threshold_days: number
      dead_stock_threshold_days: number
      buckets: Record<string, AgeingBucket>
      items: AgeingItem[]
      data_quality: DataQuality
    }
    abc: {
      sales_window_days: number
      a_cumulative_contribution_percent: number
      b_cumulative_contribution_percent: number
      total_revenue_minor: number
      summary: Record<string, { product_count: number; revenue_minor: number; share_percent: number }>
      items: AbcItem[]
      data_quality: DataQuality
    }
  } | null
  cash_forecast: CashForecast | null
  data_quality: DataQuality
}

export type DashboardV2Response = { data: DashboardV2Data }
