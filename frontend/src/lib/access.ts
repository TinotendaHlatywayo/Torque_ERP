export type RoleOption = {
  name: string
  label: string
  permissions: string[]
}

export type PermissionOption = {
  name: string
  label: string
}

export type BranchSummary = {
  id: string
  name: string
  code: string
  role: string
  role_label: string
  permissions: string[]
}

export type MeResponse = {
  user: {
    id: number
    name: string
    email: string
    is_active: boolean
    is_platform_admin: boolean
  }
  branches: BranchSummary[]
  platform_permissions: string[]
  account_type: "platform" | "branch" | "customer" | "branch_impersonation"
  default_destination: "/platform" | "/app" | "/account"
  impersonation: {
    active: boolean
    branch_id: string | null
    platform_user_id: number | null
  }
}

export type Membership = {
  id: string
  user: {
    id: number
    name: string
    email: string
    is_active: boolean
  }
  role: string
  role_label: string
  permissions: string[]
  effective_permissions: string[]
  is_active: boolean
  created_at: string | null
}

export type MembershipListResponse = {
  data: Membership[]
}

export type RolesResponse = {
  roles: RoleOption[]
  permissions: PermissionOption[]
}

export function hasPermission(me: MeResponse | undefined, branchId: string, permission: string): boolean {
  const branch = me?.branches.find((item) => item.id === branchId)
  return branch?.permissions.includes(permission) ?? false
}
