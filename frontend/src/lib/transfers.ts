export type ListResponse<T> = { data: T[] }

export type NamedRef = {
  id: string
  name: string
  code?: string
  sku?: string
}

export type TransferStatus = 'draft' | 'dispatched' | 'in_transit' | 'received' | 'cancelled'

export type StocktakeStatus = 'draft' | 'reviewed' | 'approved'

export type StockMovementRef = {
  id: string
  movement_type: string
  quantity: string
  balance_after: string
}

export type StockTransferRecord = {
  id: string
  transfer_number: string
  source_branch_id: string
  source_branch: NamedRef | null
  destination_branch_id: string
  destination_branch: NamedRef | null
  product_id: string
  product: NamedRef | null
  unit_id: string
  unit: NamedRef | null
  destination_product_id: string
  destination_product: NamedRef | null
  destination_unit_id: string
  destination_unit: NamedRef | null
  quantity: string
  dispatched_quantity: string | null
  reason: string
  status: TransferStatus
  dispatched_at: string | null
  in_transit_at: string | null
  received_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  source_movement_id: string | null
  destination_movement_id: string | null
  cancellation_movement_id: string | null
  source_movement: StockMovementRef | null
  destination_movement: StockMovementRef | null
  cancellation_movement: StockMovementRef | null
  created_at: string | null
  updated_at: string | null
}

export type StocktakeLineRecord = {
  id: string
  stocktake_id: string
  product_id: string
  product: NamedRef | null
  unit_id: string
  unit: NamedRef | null
  expected_quantity: string
  counted_quantity: string
  variance: string | null
  reason: string
  stock_movement_id: string | null
  stock_movement: StockMovementRef | null
}

export type StocktakeRecord = {
  id: string
  stocktake_number: string
  branch_id: string
  reason: string
  status: StocktakeStatus
  reviewed_at: string | null
  approved_at: string | null
  approval_reference: string | null
  line_count: number
  lines: StocktakeLineRecord[]
  created_at: string | null
  updated_at: string | null
}

export type TransferActionResponse = { data: StockTransferRecord }
export type StocktakeActionResponse = { data: StocktakeRecord }
