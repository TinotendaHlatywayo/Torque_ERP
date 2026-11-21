<!doctype html>
<html lang="en-ZW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $query !== '' ? 'noindex, follow' : 'index, follow' }}">
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="icon" type="image/png" href="{{ asset('tms_logo_transparent.png') }}">
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/catalogue.css') }}">
    <title>{{ $title }} | Taziva Motor Spares</title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Taziva Motor Spares">
    <meta property="og:title" content="{{ $title }} | Taziva Motor Spares">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ asset('images/torqueerp-social.svg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    @if ($query === '')
        <script type="application/ld+json">{!! json_encode([
            '@'.'context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $title,
            'url' => $canonical,
            'itemListElement' => $products->getCollection()->values()->map(fn ($product, $index) => [
                '@type' => 'ListItem',
                'position' => ($products->firstItem() ?? 1) + $index,
                'url' => rtrim(config('app.url'), '/').'/shop/'.$product->public_slug,
                'name' => $product->name,
            ])->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header"><a class="brand" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ asset('tms_logo_transparent.png') }}" alt="TMS home"></a><nav aria-label="Main navigation"><a href="{{ route('public.home') }}">Home</a><a class="nav-signin" href="{{ rtrim(config('app.frontend_url'), '/') }}/login">Team sign in <span aria-hidden="true">↗</span></a></nav></header>
<main id="main" class="catalogue-page">
    <div class="catalogue-heading"><div><p class="eyebrow">Taziva Motor Spares · Rusape</p><h1>{{ $title }}</h1><p class="catalogue-intro">Browse our published catalogue. Contact our local team to confirm current availability before travelling.</p></div><form class="catalogue-search" method="get" action="{{ $category ? route('shop.category', $category->slug) : route('shop.index') }}" role="search"><label class="sr-only" for="catalogue-q">Search motor spares</label><input id="catalogue-q" name="q" value="{{ $query }}" maxlength="80" placeholder="Search parts and descriptions"><button type="submit" aria-label="Search catalogue">Search <span aria-hidden="true">→</span></button></form></div>
    <div class="catalogue-layout">
        <aside class="category-rail" aria-label="Product categories"><h2>Browse categories</h2><a class="{{ $category ? '' : 'is-active' }}" href="{{ route('shop.index') }}">All parts <span>{{ $products->total() }}</span></a>@foreach ($categories as $item)<a class="{{ $category?->id === $item->id ? 'is-active' : '' }}" href="{{ route('shop.category', $item->slug) }}">{{ $item->name }}<span aria-hidden="true">↗</span></a>@endforeach</aside>
        <section aria-label="Products">
            @if ($products->isEmpty())
                <div class="catalogue-empty"><span aria-hidden="true">⌕</span><h2>{{ $query ? 'No matching parts found' : 'The catalogue is being prepared' }}</h2><p>{{ $query ? 'Try a shorter search or browse a category.' : 'Our team is preparing product details for the online catalogue.' }}</p>@if ($query)<a href="{{ $category ? route('shop.category', $category->slug) : route('shop.index') }}">Clear search</a>@endif</div>
            @else
                <p class="result-count">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('part', $products->total()) }}{{ $query ? ' for “'.$query.'”' : '' }}</p>
                <div class="product-grid">
                    @foreach ($products as $product)
                        <article class="product-card">
                            <a class="product-card-image" href="{{ route('shop.product', $product->public_slug) }}" aria-label="View {{ $product->name }}">
                                @if ($product->publicImage && (isset($product->publicImage->variants['960_webp']) || isset($product->publicImage->variants['960_avif'])))
                                    @php($imageVariant = isset($product->publicImage->variants['960_webp']) ? '960-webp' : '960-avif')
                                    <img src="{{ route('public.media', [$product->publicImage->id, $imageVariant]) }}" alt="{{ $product->public_image_alt }}" width="{{ $product->publicImage->variants[str_replace('-', '_', $imageVariant)]['width'] }}" height="{{ $product->publicImage->variants[str_replace('-', '_', $imageVariant)]['height'] }}" loading="lazy">
                                @else
                                    <span class="product-placeholder" aria-hidden="true">TMS<span>PARTS</span></span>
                                @endif
                                <span class="image-arrow" aria-hidden="true">↗</span>
                            </a>
                            <div class="product-card-copy">
                                <p class="product-category">{{ $product->category?->name ?? 'Motor spares' }}</p>
                                <h2><a href="{{ route('shop.product', $product->public_slug) }}">{{ $product->name }}</a></h2>
                                <p class="product-summary">{{ \Illuminate\Support\Str::limit($product->public_description, 96) }}</p>
                                <div class="product-card-bottom"><strong>{{ \App\Support\Money::format($product->selling_price_minor, $product->currency_code) }} <small>{{ $product->currency_code }}</small></strong><span>Check availability</span></div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <nav class="catalogue-pagination" aria-label="Catalogue pages">{{ $products->links() }}</nav>
            @endif
        </section>
    </div>
</main>
<footer class="site-footer"><a class="brand brand-small" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ asset('tms_logo_transparent.png') }}" alt="TMS home"></a><p>Locally focused. Road ready.</p><span>© {{ date('Y') }} Taziva Motor Spares</span></footer>
</body>
</html>
