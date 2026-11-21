import { useState } from "react"
import { Link, useNavigate } from "react-router-dom"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import { AuthShell } from "@/components/layout/AuthShell"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { storefrontAccountQueryKey } from "@/hooks/useStorefrontAccount"
import { usePageMeta } from "@/hooks/usePageMeta"
import { apiErrorMessage } from "@/lib/apiErrors"
import { getPublicSite, registerStorefrontAccount, validationFieldErrors } from "@/lib/publicApi"

export default function SignUpPage() {
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [confirmation, setConfirmation] = useState("")
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const siteQuery = useQuery({ queryKey: ['public', 'site'], queryFn: getPublicSite, staleTime: 1000 * 60 * 5 })
  const siteText = (key: string, fallback: string) => siteQuery.data?.content.text[key] || fallback

  usePageMeta(
    siteText('signup_meta_title', 'Create Account | Taziva Motor Spares'),
    siteText('signup_meta_description', 'Create a Taziva Motor Spares account to order parts and track reservations online.'),
  )

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    setError("")
    setLoading(true)

    try {
      const account = await registerStorefrontAccount({
        name,
        email,
        password,
        password_confirmation: confirmation,
      })
      queryClient.setQueryData(storefrontAccountQueryKey, account)
      navigate("/account", { replace: true })
    } catch (err: unknown) {
      const errors = validationFieldErrors(err)
      setError(errors.email ?? errors.password ?? apiErrorMessage(err, "Unable to create your account. Please try again."))
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthShell>
      <Card className="hover-lift overflow-hidden border-brand-ink/10 shadow-2xl">
        <div className="h-1 bg-gradient-to-r from-brand-green via-brand-mint to-brand-green-deep" aria-hidden="true" />
        <CardHeader className="space-y-1">
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Customer account</p>
          <CardTitle className="text-2xl font-black tracking-tight">Create your store account</CardTitle>
          <p className="text-sm text-muted-foreground">Order parts online and keep track of your reservations in one place.</p>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={handleSubmit} noValidate>
            {error && (
              <div id="signup-error" role="alert" aria-live="assertive" className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                {error}
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="name">Full name</Label>
              <Input
                id="name"
                value={name}
                onChange={(event) => setName(event.target.value)}
                autoComplete="name"
                aria-describedby={error ? "signup-error" : undefined}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="email">Email address</Label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="email"
                aria-describedby={error ? "signup-error" : undefined}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="new-password"
                aria-describedby={error ? "signup-error" : undefined}
                minLength={8}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input
                id="password_confirmation"
                type="password"
                value={confirmation}
                onChange={(event) => setConfirmation(event.target.value)}
                autoComplete="new-password"
                minLength={8}
                required
              />
            </div>
            <Button type="submit" className="min-h-11 w-full bg-brand-green-deep text-white hover:bg-brand-green-deep/90 focus-visible:ring-brand-green" disabled={loading}>
              {loading ? "Creating account..." : "Create account"}
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              Already registered?{" "}
              <Link to="/login?redirect=/account" className="font-bold text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
                Sign in
              </Link>
            </p>
            <Link
              to="/"
              className="flex min-h-11 items-center justify-center rounded-md text-sm font-semibold text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green"
            >
              Back to public website
            </Link>
          </form>
        </CardContent>
      </Card>
    </AuthShell>
  )
}
