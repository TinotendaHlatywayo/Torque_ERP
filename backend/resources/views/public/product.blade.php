@php($imageVariant = $product->publicImage ? (isset($product->publicImage->variants['960_webp']) ? '960-webp' : (isset($product->publicImage->variants['960_avif']) ? '960-avif' : 'original')) : null)
<!doctype html>
<html lang="en-ZW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="icon" type="image/png" href="{{ asset('tms_logo_transparent.png') }}">
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/catalogue.css') }}">
    <title>{{ $product->name }} | Taziva Motor Spares</title>
    <meta property="og:type" content="product">
    <meta property="og:site_name" content="Taziva Motor Spares">
    <meta property="og:title" content="{{ $product->name }} | Taziva Motor Spares">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ asset('images/torqueerp-social.svg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <script type="application/ld+json">{!! json_encode([
        '@'.'context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $product->public_description,
        'url' => $canonical,
        'category' => $product->category?->name,
        'image' => $product->publicImage ? route('public.media', [$product->publicImage->id, $imageVariant]) : null,
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => $product->currency_code,
            'price' => $price,
            'url' => $canonical,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR) !!}</script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header"><a class="brand" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ asset('tms_logo_transparent.png') }}" alt="TMS home"></a><nav aria-label="Main navigation"><a href="{{ route('shop.index') }}">Catalogue</a><a class="nav-signin" href="{{ rtrim(config('app.frontend_url'), '/') }}/login">Team sign in <span aria-hidden="true">↗</span></a></nav></header>
<main id="main" class="product-detail-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('public.home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route('shop.index') }}">Catalogue</a>@if ($product->category)<span aria-hidden="true">/</span><a href="{{ route('shop.category', $product->category->slug) }}">{{ $product->category->name }}</a>@endif<span aria-hidden="true">/</span><span aria-current="page">{{ $product->name }}</span></nav>
    <section class="product-detail">
        <div class="product-detail-art">
            @if ($product->publicImage && (isset($product->publicImage->variants['960_webp']) || isset($product->publicImage->variants['960_avif'])))
                <picture>@if (isset($product->publicImage->variants['960_avif']))<source type="image/avif" srcset="{{ route('public.media', [$product->publicImage->id, '960-avif']) }}">@endif<img src="{{ route('public.media', [$product->publicImage->id, $imageVariant]) }}" alt="{{ $product->public_image_alt }}" width="{{ $product->publicImage->variants[str_replace('-', '_', $imageVariant)]['width'] }}" height="{{ $product->publicImage->variants[str_replace('-', '_', $imageVariant)]['height'] }}"></picture>
            @else
                <div class="product-placeholder product-placeholder-large" role="img" aria-label="Product image not available"><span>TMS</span><small>MOTOR SPARES</small></div>
            @endif
        </div>
        <div class="product-detail-copy"><p class="eyebrow">{{ $product->category?->name ?? 'Motor spares' }}</p><h1>{{ $product->name }}</h1><p class="detail-price">{{ $price }} <small>{{ $product->currency_code }}</small></p><p class="availability-note"><span aria-hidden="true">●</span> Contact us to confirm current availability</p><p class="product-description">{{ $product->public_description }}</p><p class="detail-stock-note">Stock changes through the day. We’ll confirm the latest availability before you make a trip.</p>@if ($product->track_stock)<a class="primary-link" href="{{ route('reservation.form', $product->public_slug) }}">Request a short stock hold <span aria-hidden="true">→</span></a>@endif</div>
    </section>
    @if ($related->isNotEmpty())<section class="related-products"><div class="related-heading"><p class="eyebrow">Keep looking</p><h2>More from this category</h2></div><div class="related-links">@foreach ($related as $item)<a href="{{ route('shop.product', $item->public_slug) }}">{{ $item->name }} <span aria-hidden="true">↗</span></a>@endforeach</div></section>@endif
</main>
<footer class="site-footer"><a class="brand brand-small" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ asset('tms_logo_transparent.png') }}" alt="TMS home"></a><p>Locally focused. Road ready.</p><span>© {{ date('Y') }} Taziva Motor Spares</span></footer>
</body>
</html>
