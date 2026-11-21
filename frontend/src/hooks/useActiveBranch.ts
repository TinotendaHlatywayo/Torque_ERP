import { useQueryClient } from "@tanstack/react-query"
import { getStoredBranchId, storeBranchId } from "@/lib/branch"
import { useMe } from "./useMe"

export function useActiveBranch() {
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const branches = meQuery.data?.branches ?? []
  const storedId = getStoredBranchId()
  const activeBranch = branches.find((branch) => branch.id === storedId) ?? branches[0]

  const setActiveBranch = (branchId: string) => {
    if (branchId === storedId) return
    storeBranchId(branchId)
    queryClient.clear()
    void queryClient.invalidateQueries({ queryKey: ["me"] })
  }

  return {
    activeBranch,
    branches,
    isLoading: meQuery.isLoading,
    setActiveBranch,
  }
}
