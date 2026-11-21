import { useQuery } from '@tanstack/react-query'
import type { MeResponse } from '@/lib/access'
import api from '@/lib/api'
import { preferActiveBranch } from '@/lib/branch'

async function fetchMe(): Promise<MeResponse> {
  const response = await api.get<MeResponse>('/api/user')
  return preferActiveBranch(response.data)
}

export function useMe() {
  return useQuery({
    queryKey: ['me'],
    queryFn: fetchMe,
    retry: false,
    staleTime: 1000 * 30,
  })
}
