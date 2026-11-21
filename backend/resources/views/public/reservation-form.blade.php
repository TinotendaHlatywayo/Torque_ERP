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
    <title>Reserve {{ $product->name }} | Taziva Motor Spares</title>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header"><a class="brand" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ $siteImages['logo_media_id']['url'] }}" alt="{{ $siteImages['logo_media_id']['alt'] }}"></a><nav aria-label="Main navigation"><a href="{{ route('shop.product', $product->public_slug) }}">Back to product</a></nav></header>
<main id="main" class="reservation-page">
    <div class="reservation-heading"><p class="eyebrow">A short stock hold</p><h1>Request a reservation</h1><p>Share a way for the Rusape team to reach you. A short hold is created only if the stock is still available when you submit.</p></div>
    <div class="reservation-layout">
        <section class="reservation-form-card" aria-labelledby="contact-heading">
            <h2 id="contact-heading">Your contact details</h2>
            @if ($errors->any())<div role="alert" class="reservation-error"><strong>We couldn't create the hold.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @if (! $available)
                <div class="reservation-unavailable"><span aria-hidden="true">!</span><h3>Not available for a hold right now</h3><p>Stock may have changed. Please check back or contact the store before travelling.</p><a href="{{ route('shop.product', $product->public_slug) }}">Return to product details</a></div>
            @else
                <form method="post" action="{{ route('reservation.store') }}" class="reservation-form">
                    @csrf
                    <input type="hidden" name="product_slug" value="{{ $product->public_slug }}">
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                    <div class="reservation-field"><label for="reservation-name">Your name</label><input id="reservation-name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="reservation-field"><label for="reservation-phone">Phone number <span>(recommended)</span></label><input id="reservation-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" maxlength="40" placeholder="+263 …">@error('phone')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="reservation-field"><label for="reservation-email">Email address</label><input id="reservation-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" placeholder="name@example.com">@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="reservation-field"><label for="reservation-quantity">Quantity</label><input id="reservation-quantity" name="quantity" type="number" min="0.0001" max="1000000" step="{{ $product->baseUnit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('quantity', '1') }}" inputmode="decimal" required>@error('quantity')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <p class="reservation-privacy">Use either a phone number or email so the team can contact you. Contact details are stored privately and shown only to authorized branch staff.</p>
                    <button type="submit" class="reservation-submit">Request this hold <span aria-hidden="true">→</span></button>
                </form>
            @endif
        </section>
        <aside class="reservation-summary" aria-label="Reservation summary"><p class="eyebrow">Your selection</p><div class="summary-product"><span class="summary-mark" aria-hidden="true">T</span><div><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Motor spares' }}</small></div></div><dl><div><dt>Current unit price</dt><dd>{{ $price }} {{ $product->currency_code }}</dd></div><div><dt>Hold period</dt><dd>{{ max(1, (int) config('catalog.reservation_expiration_minutes', 15)) }} minutes</dd></div></dl><p class="summary-note">The stock hold starts only after the server accepts your request. The final amount reflects the selected quantity and current listed price.</p></aside>
    </div>
</main>
<footer class="site-footer"><a class="brand brand-small" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ $siteImages['logo_media_id']['url'] }}" alt="{{ $siteImages['logo_media_id']['alt'] }}"></a><p>Locally focused. Road ready.</p><span>© {{ date('Y') }} Taziva Motor Spares</span></footer>
</body>
</html>
