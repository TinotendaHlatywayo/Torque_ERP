import { useState, type FormEvent } from "react"
import { useMutation, useQuery } from "@tanstack/react-query"
import { Link, useNavigate, useParams } from "react-router-dom"
import { ErrorState, LoadingState } from "@/components/feedback/StateComponents"
import { usePageMeta } from "@/hooks/usePageMeta"
import { PublicShell } from "@/components/layout/PublicShell"
import { ArrowRightIcon } from "@/components/public/icons"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { apiErrorMessage } from "@/lib/apiErrors"
import { createReservation, startReservation, validationFieldErrors, type CreateReservationPayload } from "@/lib/publicApi"
import { cn } from "@/lib/utils"

type FieldName = "name" | "email" | "phone" | "quantity"
type FormValues = Record<FieldName, string>

type FieldProps = {
  id: FieldName
  label: string
  value: string
  onChange: (value: string) => void
  error?: string
  type?: string
  required?: boolean
  placeholder?: string
  hint?: string
  min?: string
  step?: string
  maxLength?: number
  autoComplete?: string
}

function Field({ id, label, value, onChange, error, type = "text", required, placeholder, hint, min, step, maxLength, autoComplete }: FieldProps) {
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined

  return (
    <div className="space-y-2">
      <Label htmlFor={id} className="text-brand-ink">
        {label}{required && <span className="ml-1 text-brand-green-deep" aria-hidden="true">*</span>}
      </Label>
      <Input
        id={id}
        name={id}
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        required={required}
        min={min}
        step={step}
        maxLength={maxLength}
        autoComplete={autoComplete}
        aria-invalid={Boolean(error)}
        aria-describedby={describedBy}
        className={cn("min-h-11 bg-white", error && "border-red-600 focus-visible:ring-red-600")}
      />
      {hint && !error && <p id={`${id}-hint`} className="text-xs text-brand-ink/70">{hint}</p>}
      {error && <p id={`${id}-error`} role="alert" className="text-sm font-semibold text-red-700">{error}</p>}
    </div>
  )
}

export default function ReservationPage() {
  const { slug = "" } = useParams()
  const navigate = useNavigate()
  usePageMeta(
    "Reserve a Part | Taziva Motor Spares",
    "Place a short stock hold on a motor spare from Taziva Motor Spares.",
  )
  const [values, setValues] = useState<FormValues>({ name: "", email: "", phone: "", quantity: "1" })
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<FieldName, string>>>({})
  const [formError, setFormError] = useState("")

  const startQuery = useQuery({
    queryKey: ["public", "reservation", "start", slug],
    queryFn: () => startReservation(slug),
    enabled: Boolean(slug),
  })

  const reservationMutation = useMutation({
    mutationFn: createReservation,
    onSuccess: (result) => {
      if (result.payment_url) {
        window.location.assign(result.payment_url)
        return
      }
      navigate(`/reservation/${result.token}`, { replace: true })
    },
    onError: (error) => {
      const errors = validationFieldErrors(error)
      setFieldErrors({
        name: errors.name,
        email: errors.email,
        phone: errors.phone,
        quantity: errors.quantity,
      })
      setFormError(apiErrorMessage(error, "We could not create your reservation. Please try again."))
    },
  })

  const updateField = (field: FieldName, value: string) => {
    setValues((previous) => ({ ...previous, [field]: value }))
    setFieldErrors((previous) => ({ ...previous, [field]: undefined }))
    setFormError("")
  }

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const start = startQuery.data
    if (!start) {
      return
    }

    const errors: Partial<Record<FieldName, string>> = {}
    const name = values.name.trim()
    const email = values.email.trim()
    const phone = values.phone.trim()
    const quantity = values.quantity.trim()

    if (!name) errors.name = "Enter your name."
    if (!email && !phone) errors.phone = "Enter an email address or phone number."
    if (!/^\d+(\.\d{1,4})?$/.test(quantity) || Number(quantity) <= 0) {
      errors.quantity = "Enter a quantity greater than zero."
    }

    setFieldErrors(errors)
    if (Object.keys(errors).length > 0 || !start.product.can_reserve) {
      setFormError("Please check the highlighted fields.")
      return
    }

    setFormError("")
    const payload: CreateReservationPayload = {
      product_slug: slug,
      idempotency_key: start.idempotency_key,
      name,
      email: email || undefined,
      phone: phone || undefined,
      quantity,
    }
    reservationMutation.mutate(payload)
  }

  return (
    <PublicShell>
      <div className="mx-auto w-full max-w-3xl px-5 py-10 sm:px-6 lg:px-8 lg:py-14">
        <nav aria-label="Breadcrumb" className="text-sm text-brand-ink/70">
          <ol className="flex flex-wrap items-center gap-2">
            <li><Link to="/" className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Home</Link></li>
            <li aria-hidden="true">/</li>
            <li><Link to="/shop" className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">Shop</Link></li>
            {startQuery.data && (
              <>
                <li aria-hidden="true">/</li>
                <li>
                  <Link to={`/shop/${startQuery.data.product.slug}`} className="rounded hover:text-brand-green-deep hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">
                    {startQuery.data.product.name}
                  </Link>
                </li>
              </>
            )}
            <li aria-hidden="true">/</li>
            <li className="font-semibold text-brand-ink" aria-current="page">Reserve</li>
          </ol>
        </nav>

        {startQuery.isPending && <LoadingState text="Preparing your reservation..." />}

        {startQuery.isError && (
          <div className="mt-8">
            <ErrorState
              title="Reservation is not available"
              description={apiErrorMessage(startQuery.error, "This part may no longer be available to reserve.")}
              retry={() => void startQuery.refetch()}
            />
          </div>
        )}

        {startQuery.data && (
          <>
            <header className="mt-6">
              <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-green-deep">Reserve online</p>
              <h1 className="mt-2 text-3xl font-black tracking-tight text-brand-ink sm:text-4xl">Reserve your part</h1>
              <p className="mt-2 text-sm leading-relaxed text-brand-ink/80 sm:text-base">
                Fill in your details to place a short hold on this item.
              </p>
            </header>

            <div className="mt-8 rounded-xl border border-brand-ink/10 bg-white p-5 sm:p-6">
              <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-ink/70">Your selection</p>
              <div className="mt-3 flex flex-wrap items-baseline justify-between gap-3">
                <h2 className="text-xl font-extrabold text-brand-ink">{startQuery.data.product.name}</h2>
                <p className="text-lg font-black text-brand-green-deep">{startQuery.data.product.price}</p>
              </div>
            </div>

            {!startQuery.data.product.can_reserve && (
              <div role="alert" className="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">
                This part is no longer available for an online reservation.
              </div>
            )}

            <form onSubmit={submit} className="mt-8 space-y-5 rounded-xl border border-brand-ink/10 bg-white p-5 sm:p-8" noValidate>
              <h2 className="text-lg font-extrabold text-brand-ink">Your details</h2>

              {formError && (
                <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">
                  {formError}
                </div>
              )}

              <Field
                id="name"
                label="Full name"
                value={values.name}
                onChange={(value) => updateField("name", value)}
                error={fieldErrors.name}
                required
                autoComplete="name"
                placeholder="Your name"
                maxLength={120}
              />
              <div className="grid gap-5 sm:grid-cols-2">
                <Field
                  id="email"
                  label="Email address"
                  type="email"
                  value={values.email}
                  onChange={(value) => updateField("email", value)}
                  error={fieldErrors.email}
                  placeholder="you@example.com"
                  autoComplete="email"
                  maxLength={190}
                />
                <Field
                  id="phone"
                  label="Phone number"
                  type="tel"
                  value={values.phone}
                  onChange={(value) => updateField("phone", value)}
                  error={fieldErrors.phone}
                  placeholder="+263 77 000 0000"
                  hint="Provide an email or a phone number."
                  autoComplete="tel"
                  maxLength={40}
                />
              </div>
              <Field
                id="quantity"
                label="Quantity"
                type="number"
                value={values.quantity}
                onChange={(value) => updateField("quantity", value)}
                error={fieldErrors.quantity}
                required
                min="0.0001"
                step="any"
              />

              <Button
                type="submit"
                size="lg"
                disabled={reservationMutation.isPending || !startQuery.data.product.can_reserve}
                className="min-h-12 w-full bg-brand-green-deep text-white hover:bg-brand-green-deep/90 focus-visible:ring-brand-green sm:w-auto"
              >
                {reservationMutation.isPending ? "Placing reservation..." : "Continue to reservation"}
                {!reservationMutation.isPending && <ArrowRightIcon className="ml-2 h-4 w-4" />}
              </Button>
              <p className="text-xs leading-relaxed text-brand-ink/70">
                You will be sent to payment when checkout is available. Otherwise your reservation details will be shown immediately.
              </p>
            </form>
          </>
        )}
      </div>
    </PublicShell>
  )
}
