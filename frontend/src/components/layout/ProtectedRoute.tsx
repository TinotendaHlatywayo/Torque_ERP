import { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { LoadingState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'

type ProtectedRouteProps = {
  children: ReactNode
  platformOnly?: boolean
  platformPermission?: string
}

export function ProtectedRoute({ children, platformOnly = false, platformPermission }: ProtectedRouteProps) {
  const location = useLocation()
  const { data, isLoading, isError } = useMe()

  if (isLoading) {
    return <LoadingState text="Checking your access..." />
  }

  if (isError || !data) {
    const redirect = encodeURIComponent(`${location.pathname}${location.search}`)
    return <Navigate to={`/login?redirect=${redirect}`} replace />
  }

  const inBranchWorkspace = location.pathname === "/app" || location.pathname.startsWith("/app/")

  if (data.impersonation?.active && ! inBranchWorkspace) {
    return <Navigate to="/app" replace />
  }

  if (platformOnly && !data.user.is_platform_admin) {
    return <Navigate to="/app" replace />
  }

  if (platformPermission && !data.user.is_platform_admin) {
    return <Navigate to="/app" replace />
  }

  return <>{children}</>
}
