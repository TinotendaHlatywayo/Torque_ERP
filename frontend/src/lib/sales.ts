export type SalePayment = {
  id: string
  method: string
  amount_minor: number
  currency_code: string
  reference: string | null
  status: string
  created_at: string | null
}

export type SaleItem = {
  id: string
  product_id: string
  product_name: string
  sku: string
  unit_id: string
  unit_code: string
  quantity: string
  unit_price_minor: number
  discount_minor: number
  tax_minor: number
  total_minor: number
  currency_code: string
}

export type SaleSummary = {
  id: string
  sale_number: string
  status: string
  payment_status: string
  currency_code: string
  subtotal_minor: number
  discount_minor: number
  tax_minor: number
  total_minor: number
  paid_minor: number
  customer: { id: string; name: string } | null
  receipt: { id: string; receipt_number: string } | null
  items_count: number
  created_at: string | null
}

export type SaleDetail = {
  id: string
  sale_number: string
  status: string
  payment_status: string
  currency_code: string
  subtotal_minor: number
  discount_minor: number
  tax_minor: number
  total_minor: number
  paid_minor: number
  voided_at?: string | null
  void_reason?: string | null
  customer: { id: string; name: string } | null
  items: SaleItem[]
  payments: SalePayment[]
  receipt: { id: string; receipt_number: string; issued_at?: string | null } | null
  created_at: string | null
}

export type SaleReturnItem = {
  id: string
  sale_item_id: string
  product_id: string
  quantity: string
  amount_minor: number
  currency_code: string
}

export type SaleReturn = {
  id: string
  return_number: string
  sale_id: string
  sale_number: string | null
  status: string
  restock_decision: string
  refund_status: string
  reason: string
  currency_code: string
  total_minor: number
  items: SaleReturnItem[]
  created_at: string | null
}
