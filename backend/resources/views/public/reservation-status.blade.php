<!doctype html>
<html lang="en-ZW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" href="{{ $siteImages['favicon_media_id']['url'] }}">
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/catalogue.css') }}">
    <title>Reservation {{ $reservation->reservation_number }} | Taziva Motor Spares</title>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header"><a class="brand" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ $siteImages['logo_media_id']['url'] }}" alt="{{ $siteImages['logo_media_id']['alt'] }}"></a><nav aria-label="Main navigation"><a href="{{ route('shop.index') }}">Catalogue</a></nav></header>
<main id="main" class="reservation-status-page">
    @if (session('notice'))<p role="status" class="reservation-notice">{{ session('notice') }}</p>@endif
    <p class="eyebrow">Reservation {{ $reservation->reservation_number }}</p>
    <h1>{{ $reservation->status === 'awaiting_payment' ? 'Your item is on hold' : ($reservation->status === 'cancelled' ? 'Reservation cancelled' : ($reservation->status === 'expired' ? 'Hold expired' : 'Reservation update')) }}</h1>
    <div class="reservation-status-card"><span class="reservation-status-pill status-{{ $reservation->status }}">{{ str_replace('_', ' ', $reservation->status) }}</span><h2>{{ $reservation->product_name }}</h2><dl><div><dt>Quantity</dt><dd>{{ rtrim(rtrim(number_format((float) $reservation->quantity, 4, '.', ''), '0'), '.') }}</dd></div><div><dt>Amount</dt><dd>{{ $price }} {{ $reservation->currency_code }}</dd></div><div><dt>Hold expires</dt><dd>{{ $reservation->expires_at->timezone($reservation->branch->timezone)->format('D, j M Y · H:i') }} ({{ $reservation->branch->timezone }})</dd></div></dl>
        @if ($reservation->status === 'awaiting_payment')
            @if ($reservation->payment?->status === 'pending')
                <p class="reservation-pending-note">Your stock is held while Paynow processes payment. Return here after checkout to confirm the result.</p>
                @if ($reservation->payment->redirect_url)<a class="reservation-submit reservation-paynow-link" href="{{ $reservation->payment->redirect_url }}" rel="nofollow">Continue to Paynow checkout <span aria-hidden="true">→</span></a>@endif
                <a class="status-back-link" href="{{ route('reservation.paynow-return', $token) }}">Check payment status</a>
            @elseif ($reservation->payment?->status === 'initiation_unknown')
                <p class="reservation-pending-note">Paynow did not confirm whether checkout started. Keep this page open or contact the Rusape team before submitting another request.</p>
            @elseif ($reservation->payment?->status === 'failed')
                <p class="reservation-pending-note">Paynow could not start checkout. Your stock hold remains active until the expiry time shown above.</p>
            @elseif ($reservation->payment?->status === 'cancelled')
                <p class="reservation-pending-note">Paynow checkout was cancelled. Your stock hold remains active until the expiry time shown above.</p>
            @elseif ($reservation->payment?->status === 'disputed')
                <p class="reservation-pending-note">Paynow has marked this payment for review. The Rusape team will follow up with you.</p>
            @elseif (! $paynowEnabled)
                <p class="reservation-pending-note">Paynow checkout is not configured in this local preview. Your short stock hold remains active and will expire automatically.</p>
            @else
                <p class="reservation-pending-note">Your stock hold is active. Paynow checkout could not be started, so please contact the Rusape team before travelling.</p>
            @endif
            <form method="post" action="{{ route('reservation.cancel', $token) }}">@csrf<button class="reservation-cancel" type="submit">Cancel reservation</button></form>
        @elseif ($reservation->status === 'confirmed')<p class="reservation-pending-note">Paynow confirmed your payment. The Rusape team will contact you about collection.</p>@elseif ($reservation->payment?->status === 'paid_late')<p class="reservation-pending-note">Paynow reports a payment after this stock hold ended. The team must review it before confirming your order.</p>@elseif ($reservation->status === 'expired')<p class="reservation-pending-note">This short hold has ended and the stock is available to other customers. You can submit a new request from the product page.</p>@elseif ($reservation->status === 'cancelled')<p class="reservation-pending-note">The stock hold has been released. You can submit a new request from the product page.</p>@elseif ($reservation->status === 'refunded')<p class="reservation-pending-note">Paynow reports a refund. The Rusape team can help with any questions about its timing.</p>@endif
    </div>
    <a class="status-back-link" href="{{ route('shop.product', $reservation->product->public_slug) }}">Back to product details <span aria-hidden="true">→</span></a>
</main>
<footer class="site-footer"><a class="brand brand-small" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ $siteImages['logo_media_id']['url'] }}" alt="{{ $siteImages['logo_media_id']['alt'] }}"></a><p>Locally focused. Road ready.</p><span>© {{ date('Y') }} Taziva Motor Spares</span></footer>
</body>
</html>
