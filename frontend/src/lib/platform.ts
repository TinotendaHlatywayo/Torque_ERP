import api from "./api"

export type PlatformBranch = {
  id: string
  platform_id: string
  platform: string | null
  name: string
  code: string
  slug: string
  status: "pending" | "active" | "suspended" | "archived"
  host: string | null
  timezone: string
  currency_code: string
  approved_at: string | null
  approved_by: string | null
  suspended_at: string | null
  suspended_by: string | null
  suspension_reason: string | null
  registration_notes: string | null
  archived_at: string | null
  created_at: string | null
}

export type PlatformBranchFinance = {
  branch_id: string
  branch_name: string
  branch_code: string
  status: string
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

export type PlatformBranchOverall = {
  branch_count: number
  as_of: string
  currency_codes: string[]
  mixed_currency: boolean
  money_in_minor: number
  money_out_minor: number
  invoiced_revenue_minor: number
  expenses_incurred_minor: number
  profit_basis_minor: number
  money_owed_to_us_minor: number
  bills_to_pay_minor: number
  inventory_value_minor: number
  cash_position_minor: number
}

export type PlatformBranchReport = {
  data: PlatformBranchFinance[]
  overall: PlatformBranchOverall
}

export type RegisterBranchPayload = {
  platform_id: string
  name: string
  code: string
  slug: string
  timezone: string
  currency_code: string
  host?: string
  registration_notes?: string
}

export async function fetchPlatformBranches(): Promise<PlatformBranch[]> {
  const response = await api.get<{ data: PlatformBranch[] }>("/api/platform/branches")
  return response.data.data
}

export async function fetchPlatformBranchReport(platformId: string, asOf?: string): Promise<PlatformBranchReport> {
  const response = await api.get<PlatformBranchReport>(`/api/platforms/${platformId}/reports/branches`, {
    params: asOf ? { as_of: asOf } : undefined,
  })
  return response.data
}

export async function registerPlatformBranch(payload: RegisterBranchPayload): Promise<PlatformBranch> {
  const response = await api.post<{ data: PlatformBranch }>("/api/platform/branches", payload)
  return response.data.data
}

export async function updatePlatformBranchStatus(
  branchId: string,
  action: "approve" | "suspend" | "activate" | "archive",
  reason?: string,
): Promise<PlatformBranch> {
  const response = await api.patch<{ data: PlatformBranch }>(`/api/platform/branches/${branchId}/${action}`, {
    reason,
  })
  return response.data.data
}

export async function impersonatePlatformBranch(branchId: string): Promise<void> {
  await api.post(`/api/platform/branches/${branchId}/impersonate`)
}

export async function exportPlatformBranchReport(
  platformId: string,
  asOf?: string,
): Promise<{ id: string; download_url: string; expires_at: string | null }> {
  const response = await api.post<{ data: { id: string; download_url: string; expires_at: string | null } }>(
    `/api/platforms/${platformId}/reports/export`,
    asOf ? { as_of: asOf } : undefined,
  )
  return response.data.data
}
