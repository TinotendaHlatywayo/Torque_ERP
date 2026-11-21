import { useQuery } from '@tanstack/react-query'
import { getStorefrontAccount } from '@/lib/publicApi'

export const storefrontAccountQueryKey = ['storefront-account'] as const

export function useStorefrontAccount() {
  return useQuery({
    queryKey: storefrontAccountQueryKey,
    queryFn: getStorefrontAccount,
    retry: false,
    staleTime: 1000 * 30,
  })
}
