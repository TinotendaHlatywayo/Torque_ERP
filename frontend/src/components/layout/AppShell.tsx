import { useEffect, useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { Link, NavLink, useNavigate } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { useActiveBranch } from "@/hooks/useActiveBranch"
import { useMe } from "@/hooks/useMe"
import { useLocale } from "@/contexts/LocaleContext"
import { useTheme, type Theme } from "@/contexts/ThemeContext"
import type { Locale } from "@/i18n"
import api from "@/lib/api"
import { apiErrorMessage } from "@/lib/apiErrors"
import { storeBranchId } from "@/lib/branch"
import { cn } from "@/lib/utils"

const APP_VERSION = "0.0.0"

const baseNavItems = [
  { to: "/app", label: "Dashboard", end: true },
  { to: "/app/inventory-intelligence", label: "Inventory intelligence", end: false },
  { to: "/app/products", label: "Products", end: false },
  { to: "/app/catalogue-search", label: "Catalogue search", end: false },
  { to: "/app/customers", label: "Customers", end: false },
  { to: "/app/loyalty", label: "Loyalty and win-back", end: false },
  { to: "/app/stock", label: "Stock", end: false },
  { to: "/app/transfers", label: "Stock transfers", end: false },
  { to: "/app/pos", label: "POS", end: false },
  { to: "/app/sales", label: "Sales history", end: false },
  { to: "/app/reservations", label: "Online reservations", end: false },
  { to: "/app/sync", label: "Sync center", end: false },
  { to: "/app/users", label: "Users and roles", end: false },
  { to: "/app/employees", label: "Employees", end: false },
  { to: "/app/hr", label: "HR records", end: false },
  { to: "/app/payroll", label: "Payroll", end: false },
  { to: "/app/tasks", label: "Tasks", end: false },
  { to: "/app/jobs", label: "Workshop jobs", end: false },
  { to: "/app/assets", label: "Assets and maintenance", end: false },
]

function navClass({ isActive }: { isActive: boolean }) {
  return cn(
    "block min-h-11 rounded-md px-3 py-2 text-sm font-semibold transition-colors duration-200 motion-reduce:transition-none",
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
    isActive
      ? "bg-brand-green-deep text-white shadow-sm"
      : "text-muted-foreground hover:bg-brand-green/10 hover:text-brand-green-deep",
  )
}

const THEME_OPTIONS: { value: Theme; label: string }[] = [
  { value: "light", label: "Light" },
  { value: "dark", label: "Dark" },
  { value: "solarized", label: "Solarized" },
]

export function AppShell({ children }: { children: React.ReactNode }) {
  const [logoutError, setLogoutError] = useState("")
  const [helpOpen, setHelpOpen] = useState(false)
  const [scrollProgress, setScrollProgress] = useState(0)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const meQuery = useMe()
  const { theme, setTheme } = useTheme()
  const { locale, locales, setLocale } = useLocale()
  const { activeBranch, branches, setActiveBranch } = useActiveBranch()
  const hasPlatformAccess = Boolean(meQuery.data?.user.is_platform_admin) || (meQuery.data?.platform_permissions.length ?? 0) > 0
  const canViewBranchAudit = activeBranch?.permissions.includes("branch.audit.view") ?? false
  const canViewPlatformAudit = Boolean(meQuery.data?.user.is_platform_admin) || (meQuery.data?.platform_permissions.includes("platform.audit.view") ?? false)
  const auditLink = canViewPlatformAudit ? "/platform/audit" : canViewBranchAudit ? "/app/audit" : null

  useEffect(() => {
    const updateProgress = () => {
      const scrollable = document.documentElement.scrollHeight - window.innerHeight
      setScrollProgress(scrollable > 0 ? Math.min(100, (window.scrollY / scrollable) * 100) : 0)
    }

    updateProgress()
    window.addEventListener("scroll", updateProgress, { passive: true })
    window.addEventListener("resize", updateProgress)
    return () => {
      window.removeEventListener("scroll", updateProgress)
      window.removeEventListener("resize", updateProgress)
    }
  }, [])

  const navItems = [
    ...baseNavItems,
    ...(canViewBranchAudit ? [{ to: "/app/audit", label: "Audit log", end: false }] : []),
  ]

  const logoutMutation = useMutation({
    mutationFn: async () => {
      await api.post("/logout")
    },
    onSuccess: () => {
      storeBranchId(null)
      queryClient.clear()
      navigate("/login", { replace: true })
    },
    onError: (error: unknown) => {
      setLogoutError(apiErrorMessage(error, "Could not sign out. Please try again."))
    },
  })

  return (
    <div className="min-h-screen bg-background">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:bg-background focus:border">
        Skip to main content
      </a>
      <div
        className="fixed inset-x-0 top-0 z-[60] h-0.5 bg-brand-mint transition-[width] duration-150 motion-reduce:transition-none"
        style={{ width: `${scrollProgress}%` }}
        aria-hidden="true"
      />
      <header className="sticky top-0 z-50 border-b border-brand-ink/10 bg-card/95 shadow-sm backdrop-blur">
        <div className="container mx-auto flex flex-wrap items-center justify-between gap-3 px-4 py-3">
          <div className="flex items-center gap-4">
            <Link to="/" className="inline-flex min-h-11 items-center gap-2 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
              <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-green-deep text-sm font-black text-white shadow-sm">
                T
              </span>
              <span className="text-lg font-black tracking-tight text-foreground">TorqueERP</span>
            </Link>
            <Link to="/" className="hidden text-sm font-semibold text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:inline">
              View website
            </Link>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <label htmlFor="theme-select" className="sr-only">Theme</label>
            <select
              id="theme-select"
              value={theme}
              onChange={(event) => setTheme(event.target.value as Theme)}
              className="min-h-11 rounded-md border border-input bg-background px-3 text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              aria-label="Theme"
            >
              {THEME_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
            <label htmlFor="locale-select" className="sr-only">Language</label>
            <select
              id="locale-select"
              value={locale}
              onChange={(event) => setLocale(event.target.value as Locale)}
              className="min-h-11 rounded-md border border-input bg-background px-3 text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              aria-label="Language"
            >
              {(Object.entries(locales) as [Locale, string][]).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
            <label htmlFor="active-branch" className="sr-only">Active branch</label>
            <select
              id="active-branch"
              value={activeBranch?.id ?? ""}
              onChange={(event) => setActiveBranch(event.target.value)}
              disabled={branches.length === 0}
              className="min-h-11 rounded-md border border-input bg-background px-3 text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
              aria-label="Active branch"
            >
              {branches.length === 0 && <option value="">No active branch</option>}
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name} · {branch.role_label}
                </option>
              ))}
            </select>
            {meQuery.data?.user.name && (
              <span className="hidden text-sm font-semibold text-muted-foreground lg:inline">
                {meQuery.data.user.name}
              </span>
            )}
            {hasPlatformAccess && (
              <Link to="/platform" className="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-foreground hover:bg-brand-green/10 hover:text-brand-green-deep focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                Platform
              </Link>
            )}
            <div className="relative">
              <Button
                type="button"
                variant="outline"
                className="min-h-11"
                aria-haspopup="dialog"
                aria-expanded={helpOpen}
                aria-controls="app-help-panel"
                onClick={() => setHelpOpen((open) => !open)}
              >
                Help
              </Button>
              {helpOpen && (
                <div
                  id="app-help-panel"
                  role="dialog"
                  aria-label="Help"
                  className="absolute right-0 z-20 mt-2 w-72 rounded-md border bg-card p-4 text-sm text-card-foreground shadow-lg"
                >
                  <p className="font-semibold">Support</p>
                  <p className="mt-1 text-muted-foreground">Email <a className="underline underline-offset-2" href="mailto:support@torqueerp.local">support@torqueerp.local</a></p>
                  <p className="mt-3 font-semibold">Version</p>
                  <p className="mt-1 text-muted-foreground">TorqueERP v{APP_VERSION}</p>
                  {auditLink && (
                    <p className="mt-3">
                      <Link to={auditLink} className="font-semibold text-primary underline underline-offset-2" onClick={() => setHelpOpen(false)}>
                        View audit log
                      </Link>
                    </p>
                  )}
                  <Button type="button" variant="ghost" className="mt-3 min-h-11 w-full justify-start px-0" onClick={() => setHelpOpen(false)}>
                    Close
                  </Button>
                </div>
              )}
            </div>
            <Button
              type="button"
              variant="outline"
              className="min-h-11"
              disabled={logoutMutation.isPending}
              onClick={() => {
                setLogoutError("")
                logoutMutation.mutate()
              }}
            >
              {logoutMutation.isPending ? "Signing out..." : "Sign out"}
            </Button>
          </div>
        </div>
        {logoutError && (
          <div role="alert" className="container mx-auto px-4 pb-3 text-sm font-semibold text-destructive">
            {logoutError}
          </div>
        )}
      </header>

      <div className="container mx-auto px-4 py-6 flex flex-col md:flex-row gap-6">
        <aside className="relative w-full overflow-hidden rounded-xl border border-brand-ink/10 bg-card p-4 shadow-sm md:sticky md:top-[77px] md:w-64 md:self-start" aria-label="Sidebar">
          <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-green via-brand-mint to-brand-green-deep" aria-hidden="true" />
          <p className="mb-3 px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-muted-foreground">Workspace</p>
          <nav className="space-y-1">
            {navItems.map((item) => (
              <NavLink key={item.to} to={item.to} end={item.end} className={navClass}>
                {item.label}
              </NavLink>
            ))}
          </nav>
        </aside>
        <main id="main-content" className="flex-1 min-w-0" tabIndex={-1}>
          <div className="animate-fade-up">{children}</div>
        </main>
      </div>
    </div>
  )
}
