import { Link } from "react-router-dom"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"
import { useMe } from "@/hooks/useMe"

export default function PlatformLandingPage() {
  const { data } = useMe()
  const isAdmin = Boolean(data?.user.is_platform_admin)
  const canViewBranches = isAdmin || (data?.platform_permissions.includes("platform.branches.view") ?? false)
  const canViewAudit = isAdmin || (data?.platform_permissions.includes("platform.audit.view") ?? false)

  return (
    <div className="min-h-screen bg-background p-8">
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Platform Console</h1>
          <p className="text-muted-foreground">TorqueERP Platform Administration</p>
        </div>
        <Card>
          <CardHeader>
            <CardTitle>Overview</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">Platform-level management. Branch isolation enforced.</p>
          </CardContent>
        </Card>
        {canViewBranches && (
          <Card>
            <CardHeader><CardTitle>Branches and analytics</CardTitle></CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">Manage branch lifecycle, compare finance across branches and open a branch workspace without signing in again.</p>
              <Link className="mt-4 inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" to="/platform/branches">Open branch console <span className="ml-2" aria-hidden="true">→</span></Link>
            </CardContent>
          </Card>
        )}
        <Card>
          <CardHeader><CardTitle>Public website</CardTitle></CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">Manage public pages, preview drafts, prepare images and publish approved content.</p>
            <Link className="mt-4 inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" to="/platform/content">Open content studio <span className="ml-2" aria-hidden="true">→</span></Link>
          </CardContent>
        </Card>
        {canViewAudit && (
          <Card>
            <CardHeader><CardTitle>Audit log</CardTitle></CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">Review platform and cross-branch activity recorded by the system.</p>
              <Link className="mt-4 inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" to="/platform/audit">Open audit log <span className="ml-2" aria-hidden="true">→</span></Link>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}
