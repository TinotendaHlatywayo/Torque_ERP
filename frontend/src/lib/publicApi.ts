import axios from "axios"
import api from "@/lib/api"

export type PublicBranch = {
  name: string
  code: string
  timezone: string
  currency_code: string
}

export type PublicCategory = {
  name: string
  slug: string
}

export type ProductSummary = {
  id: string
  name: string
  slug: string
  category_name: string | null
  category_slug: string | null
  short_description: string
  price: string
  price_value: number
  currency_code: string
  image_url: string | null
  image_alt: string | null
  can_reserve: boolean
}

export type PublicProduct = ProductSummary & {
  public_description: string
}

export type PublicSiteImage = {
  url: string
  alt: string
}

export type PublicSiteContent = {
  text: Record<string, string>
  images: Record<string, PublicSiteImage | null>
}

export type PublicSiteResponse = {
  branch: PublicBranch
  categories: PublicCategory[]
  featured: ProductSummary[]
  reservation_minutes: number
  catalogue_ready: boolean
  content: PublicSiteContent
}

export type PublicProductsParams = {
  q?: string
  category?: string
  page?: number
}

export type PublicProductsResponse = {
  data: ProductSummary[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  categories: PublicCategory[]
}

export type PublicProductResponse = {
  product: PublicProduct
  related: ProductSummary[]
  can_reserve: boolean
}

export type CmsSection = {
  type: string
  heading: string
  body: string
  image_url: string | null
  alt: string | null
}

export type PublicPageResponse = {
  page: {
    title: string
    slug: string
    sections: CmsSection[]
  }
}

export type ReservationStartResponse = {
  idempotency_key: string
  product: {
    slug: string
    name: string
    price: string
    currency_code: string
    can_reserve: boolean
  }
}

export type CreateReservationPayload = {
  product_slug: string
  idempotency_key: string
  name: string
  email?: string
  phone?: string
  quantity: string
}

export type CreatedReservation = {
  number: string
  status: string
  amount: string
  currency_code: string
  expires_at: string | null
}

export type ReservationCreateResponse = {
  token: string
  status_url: string
  payment_url: string | null
  notice: string
  reservation: CreatedReservation
}

export type PublicReservation = {
  number: string
  product_name: string
  quantity: string
  amount: string
  currency_code: string
  status: string
  payment_status: string | null
  expires_at: string | null
  can_cancel: boolean
}

export type ReservationStatusResponse = {
  reservation: PublicReservation
}

export type ReservationCancelResponse = {
  reservation: PublicReservation
  notice: string
}

export type StorefrontUser = {
  id: number
  name: string
  email: string
  is_active: boolean
}

export type StorefrontOrderItem = {
  name: string
  quantity: string
  total: string
}

export type StorefrontOrder = {
  id: string
  sale_number: string
  status: string
  payment_status: string
  currency_code: string
  subtotal: string
  total: string
  total_minor: number
  receipt_number: string | null
  created_at: string | null
  items: StorefrontOrderItem[]
}

export type StorefrontReservation = {
  id: string
  number: string
  product_name: string
  quantity: string
  amount: string
  status: string
  expires_at: string | null
}

export type StorefrontAccount = {
  user: StorefrontUser
  orders: StorefrontOrder[]
  reservations: StorefrontReservation[]
}

export type StorefrontAccountResponse = StorefrontAccount & {
  message?: string
}

export type RegisterStorefrontPayload = {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export type StorefrontCheckoutLine = {
  product_slug: string
  quantity: string
}

export type StorefrontCheckoutPayload = {
  lines: StorefrontCheckoutLine[]
  phone?: string
  address?: string
  notes?: string
}

export type StorefrontCheckoutResponse = {
  message: string
  order: StorefrontOrder
}

export async function getPublicSite(): Promise<PublicSiteResponse> {
  const { data } = await api.get<PublicSiteResponse>("/api/public/site")
  return data
}

export async function getPublicProducts(params: PublicProductsParams = {}): Promise<PublicProductsResponse> {
  const { data } = await api.get<PublicProductsResponse>("/api/public/products", {
    params: {
      q: params.q || undefined,
      category: params.category || undefined,
      page: params.page || undefined,
    },
  })
  return data
}

export async function getPublicProduct(slug: string): Promise<PublicProductResponse> {
  const { data } = await api.get<PublicProductResponse>(`/api/public/products/${encodeURIComponent(slug)}`)
  return data
}

export async function getPublicPage(slug: string): Promise<PublicPageResponse> {
  const { data } = await api.get<PublicPageResponse>(`/api/public/pages/${encodeURIComponent(slug)}`)
  return data
}

export async function startReservation(productSlug: string): Promise<ReservationStartResponse> {
  const { data } = await api.post<ReservationStartResponse>("/api/public/reservations/start", {
    product_slug: productSlug,
  })
  return data
}

export async function createReservation(payload: CreateReservationPayload): Promise<ReservationCreateResponse> {
  const { data } = await api.post<ReservationCreateResponse>("/api/public/reservations", payload)
  return data
}

export async function getReservationStatus(token: string): Promise<ReservationStatusResponse> {
  const { data } = await api.get<ReservationStatusResponse>(`/api/public/reservations/${encodeURIComponent(token)}`)
  return data
}

export async function cancelReservation(token: string): Promise<ReservationCancelResponse> {
  const { data } = await api.post<ReservationCancelResponse>(`/api/public/reservations/${encodeURIComponent(token)}/cancel`)
  return data
}

async function ensureCsrfCookie(): Promise<void> {
  await api.get("/sanctum/csrf-cookie")
}

export async function registerStorefrontAccount(payload: RegisterStorefrontPayload): Promise<StorefrontAccountResponse> {
  await ensureCsrfCookie()
  const { data } = await api.post<StorefrontAccountResponse>("/api/public/register", payload)
  return data
}

export async function getStorefrontAccount(): Promise<StorefrontAccount> {
  const { data } = await api.get<StorefrontAccount>("/api/public/account")
  return data
}

export async function logoutStorefrontAccount(): Promise<void> {
  await ensureCsrfCookie()
  await api.post("/api/public/logout")
}

export async function placeStorefrontOrder(payload: StorefrontCheckoutPayload, idempotencyKey: string): Promise<StorefrontCheckoutResponse> {
  await ensureCsrfCookie()
  const { data } = await api.post<StorefrontCheckoutResponse>("/api/public/checkout", payload, {
    headers: { "Idempotency-Key": idempotencyKey },
  })
  return data
}

export function validationFieldErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return {}
  }

  const errors = error.response.data?.errors
  if (!errors || typeof errors !== "object") {
    return {}
  }

  const result: Record<string, string> = {}
  for (const [field, messages] of Object.entries(errors as Record<string, unknown>)) {
    const message = Array.isArray(messages) ? messages.find((item): item is string => typeof item === "string") : messages
    if (typeof message === "string") {
      result[field] = message
    }
  }
  return result
}

export function safePublicImageUrl(url: string | null | undefined): string | null {
  if (!url) {
    return null
  }
  if (url.startsWith("/") && !url.startsWith("//")) {
    return url
  }
  try {
    const parsed = new URL(url)
    return parsed.protocol === "http:" || parsed.protocol === "https:" ? parsed.toString() : null
  } catch {
    return null
  }
}
