import { useState } from "react"
import { Link, useNavigate, useSearchParams } from "react-router-dom"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import { AuthShell } from "@/components/layout/AuthShell"
import { storefrontAccountQueryKey } from "@/hooks/useStorefrontAccount"
import { usePageMeta } from "@/hooks/usePageMeta"
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import type { MeResponse } from "@/lib/access"
import api from "@/lib/api"
import { getPublicSite } from "@/lib/publicApi"
import { apiErrorMessage } from "@/lib/apiErrors"
import { getStoredBranchId, preferActiveBranch, storeBranchId } from "@/lib/branch"

type AccessMode = "team" | "customer"

function safeRedirect(value: string | null, fallback: string): string {
  if (!value || !value.startsWith("/") || value.startsWith("//")) {
    return fallback
  }
  return value
}

export default function LoginPage() {
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [searchParams] = useSearchParams()
  const [accessMode, setAccessMode] = useState<AccessMode>(() => searchParams.get("redirect")?.startsWith("/account") ? "customer" : "team")
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const siteText = (key: string, fallback: string) => siteQuery.data?.content.text[key] || fallback
  const redirect = searchParams.get("redirect")
  const destination = safeRedirect(redirect, accessMode === "customer" ? "/account" : "/app")
  usePageMeta(
    siteText('login_meta_title', 'Sign In | TMS'),
    siteText('login_meta_description', 'Sign in to your TMS customer account, branch workspace or platform.'),
  )

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setError("")
    setLoading(true)

    try {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/login', { email, password })
      queryClient.clear()
      const meResponse = await api.get<MeResponse>('/api/user')
      const me = preferActiveBranch(meResponse.data)
      const storedBranchId = getStoredBranchId()
      if (storedBranchId && !me.branches.some((branch) => branch.id === storedBranchId)) {
        storeBranchId(null)
      }
      queryClient.setQueryData(['me'], me)
      await queryClient.invalidateQueries({ queryKey: storefrontAccountQueryKey })

      const hasStaffAccess = me.user.is_platform_admin || me.branches.length > 0
      if (accessMode === "team" && !hasStaffAccess) {
        setError("This account does not have platform or branch access. Choose customer access instead.")
        return
      }

      let target = destination
      if (!redirect) {
        if (accessMode === "customer") {
          target = "/account"
        } else if (me.user.is_platform_admin && me.branches.length === 0) {
          target = "/platform"
        } else if (me.branches.length > 0) {
          target = "/app"
        } else {
          target = "/platform"
        }
      }
      navigate(target, { replace: true })
    } catch (err: unknown) {
      setError(apiErrorMessage(err, "Invalid credentials or connection error."))
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthShell>
      <Card className="hover-lift overflow-hidden border-brand-ink/10 shadow-2xl">
        <div className="h-1 bg-gradient-to-r from-brand-green via-brand-mint to-brand-green-deep" aria-hidden="true" />
        <CardHeader className="space-y-1">
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">{siteText('brand_name', 'Taziva Motor Spares')}</p>
          <CardTitle className="text-2xl font-black tracking-tight">Sign in</CardTitle>
          <p className="text-sm text-muted-foreground">Access your online orders and reservations, or sign in to the team workspace.</p>
        </CardHeader>
        <CardContent>
          <div className="mb-5 grid grid-cols-2 gap-1 rounded-lg bg-muted p-1" role="group" aria-label="Sign in destination">
            <button type="button" aria-pressed={accessMode === "team"} onClick={() => setAccessMode("team")} className={`min-h-10 rounded-md px-3 text-sm font-bold transition-colors ${accessMode === "team" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"}`}>
              Platform / branch
            </button>
            <button type="button" aria-pressed={accessMode === "customer"} onClick={() => setAccessMode("customer")} className={`min-h-10 rounded-md px-3 text-sm font-bold transition-colors ${accessMode === "customer" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"}`}>
              Customer account
            </button>
          </div>
          <form className="space-y-4" onSubmit={handleLogin} noValidate>
            {error && (
              <div id="login-error" role="alert" aria-live="assertive" className="p-3 text-sm bg-destructive/10 text-destructive rounded-md">
                {error}
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                aria-describedby={error ? 'login-error' : undefined}
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@example.com"
                autoComplete="username"
                required
              />
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between gap-3">
                <Label htmlFor="password">Password</Label>
                <button
                  type="button"
                  onClick={() => setShowPassword((visible) => !visible)}
                  aria-pressed={showPassword}
                  className="min-h-11 rounded-md px-2 text-xs font-semibold text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
                >
                  {showPassword ? "Hide" : "Show"}
                </button>
              </div>
              <Input
                id="password"
                type={showPassword ? "text" : "password"}
                aria-describedby={error ? 'login-error' : undefined}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
                required
              />
            </div>
            <Button type="submit" className="min-h-11 w-full bg-brand-green-deep text-white hover:bg-brand-green-deep/90 focus-visible:ring-brand-green" disabled={loading}>
              {loading ? "Signing in..." : "Sign in"}
            </Button>
            <Link
              to="/"
              className="flex min-h-11 items-center justify-center rounded-md text-sm font-semibold text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
            >
              Back to public website
            </Link>
            <p className="text-center text-sm text-muted-foreground">
              New customer?{" "}
              <Link to="/signup" className="font-bold text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
                Create an account
              </Link>
            </p>
            <p className="pt-2 text-center text-xs text-muted-foreground">
              Team access is managed by your TMS platform administrator.
            </p>
          </form>
        </CardContent>
      </Card>
    </AuthShell>
  )
}
