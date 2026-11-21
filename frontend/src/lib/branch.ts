import type { MeResponse } from "./access"

const ACTIVE_BRANCH_KEY = "torqueerp.activeBranchId"

export function getStoredBranchId(): string | null {
  try {
    return window.localStorage.getItem(ACTIVE_BRANCH_KEY)
  } catch {
    return null
  }
}

export function storeBranchId(branchId: string | null): void {
  try {
    if (branchId) {
      window.localStorage.setItem(ACTIVE_BRANCH_KEY, branchId)
    } else {
      window.localStorage.removeItem(ACTIVE_BRANCH_KEY)
    }
  } catch {
    return
  }
}

export function preferActiveBranch(me: MeResponse): MeResponse {
  const activeId = getStoredBranchId()
  if (!activeId) {
    return me
  }

  const index = me.branches.findIndex((branch) => branch.id === activeId)
  if (index === -1) {
    storeBranchId(null)
    return me
  }
  if (index === 0) {
    return me
  }

  const branches = [...me.branches]
  const [activeBranch] = branches.splice(index, 1)
  return { ...me, branches: [activeBranch, ...branches] }
}
