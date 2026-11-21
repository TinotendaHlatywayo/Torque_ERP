import { createContext } from 'react'

export type CartLine = {
  productSlug: string
  name: string
  sku: string
  priceValue: number
  currencyCode: string
  imageUrl: string | null
  quantity: number
  maxStock: number | null
}

export type CartContextValue = {
  lines: CartLine[]
  itemCount: number
  totalValue: number
  addItem: (line: CartLine) => void
  updateQuantity: (productSlug: string, quantity: number) => void
  removeItem: (productSlug: string) => void
  clearCart: () => void
}

export const CartContext = createContext<CartContextValue | null>(null)
