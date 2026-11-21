export type CatalogueStatus = 'active' | 'inactive' | 'all'

export type Category = {
  id: string
  name: string
  slug: string
  description: string | null
  parent_id: string | null
  is_active: boolean
  product_count: number | null
  created_at: string | null
}

export type Unit = {
  id: string
  code: string
  name: string
  symbol: string | null
  allow_decimal: boolean
  is_active: boolean
  created_at: string | null
}

export type ProductStock = {
  quantity_on_hand: string
  quantity_reserved: string
  reorder_level: string | null
  is_low: boolean
}

export type ProductUnit = {
  id: string
  unit: Pick<Unit, 'id' | 'code' | 'name' | 'allow_decimal' | 'is_active'> | null
  conversion_factor: string
  selling_price_minor: number
  selling_price: string
  is_active: boolean
}

export type Product = {
  id: string
  name: string
  sku: string
  barcode: string | null
  description: string | null
  category: Pick<Category, 'id' | 'name' | 'is_active'> | null
  base_unit: Pick<Unit, 'id' | 'code' | 'name' | 'symbol' | 'allow_decimal' | 'is_active'> | null
  selling_price_minor: number
  selling_price: string
  currency_code: string
  track_stock: boolean
  is_active: boolean
  public_slug: string | null
  public_description: string | null
  is_public: boolean
  public_image_id: string | null
  public_image_url: string | null
  public_image_preview_url?: string | null
  public_image_alt: string | null
  stock: ProductStock
  alternate_unit_count: number
  created_at: string | null
  updated_at: string | null
  alternate_units?: ProductUnit[]
}

export type PaginatedProducts = {
  data: Product[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type CategoryListResponse = { data: Category[] }
export type UnitListResponse = { data: Unit[] }

export type ConversionResult = {
  product_id: string
  quantity: string
  from_unit: Pick<Unit, 'id' | 'code' | 'name' | 'allow_decimal'>
  to_unit: Pick<Unit, 'id' | 'code' | 'name' | 'allow_decimal'>
  from_factor: string
  to_factor: string
}

export type ConversionResponse = { data: ConversionResult }
