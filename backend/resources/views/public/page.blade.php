<!doctype html>
<html lang="en-ZW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags(collect($version->sections)->pluck('body')->implode(' ')), 155) }}">
    <meta name="theme-color" content="#f5f3ef">
    <link rel="canonical" href="{{ rtrim(config('app.url'), '/') }}/{{ $version->slug }}">
    <link rel="icon" href="{{ $siteImages['favicon_media_id']['url'] }}">
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
    <title>{{ $version->title }} | Taziva Motor Spares</title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Taziva Motor Spares">
    <meta property="og:title" content="{{ $version->title }} | Taziva Motor Spares">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags(collect($version->sections)->pluck('body')->implode(' ')), 155) }}">
    <meta property="og:url" content="{{ rtrim(config('app.url'), '/') }}/{{ $version->slug }}">
    <meta property="og:image" content="{{ asset('images/torqueerp-social.svg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <script type="application/ld+json">{!! json_encode([
        '@'.'context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $version->title,
        'url' => rtrim(config('app.url'), '/').'/'.$version->slug,
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Taziva Motor Spares', 'url' => rtrim(config('app.url'), '/').'/'],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header"><a class="brand" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ $siteImages['logo_media_id']['url'] }}" alt="{{ $siteImages['logo_media_id']['alt'] }}"></a><nav aria-label="Main navigation"><a href="{{ route('public.home') }}">Home</a></nav></header>
<main id="main" class="cms-page">
    @if (! collect($version->sections)->contains(fn ($section) => $section['type'] === 'hero'))
        <section class="cms-hero"><p class="eyebrow">Taziva Motor Spares · Rusape</p><h1>{{ $version->title }}</h1></section>
    @endif
    @foreach ($version->sections as $section)
        @if ($section['type'] === 'hero')
            <section class="cms-hero"><p class="eyebrow">Taziva Motor Spares · Rusape</p><h1>{{ $section['heading'] ?: $version->title }}</h1>@if ($section['body'])<p>{{ $section['body'] }}</p>@endif</section>
        @elseif ($section['type'] === 'text')
            <section class="cms-text"><h2>{{ $section['heading'] }}</h2><p>{{ $section['body'] }}</p></section>
        @elseif ($section['type'] === 'image' && isset($media[$section['media_id']]))
            @php($image = $media[$section['media_id']])
            <figure class="cms-image">
                @if (isset($image->variants['960_webp']))
                    <picture>
                        @if (isset($image->variants['960_avif']))<source type="image/avif" srcset="{{ route('public.media', [$image->id, '960-avif']) }}">@endif
                        <source type="image/webp" srcset="{{ route('public.media', [$image->id, '960-webp']) }}">
                        <img src="{{ route('public.media', [$image->id, '960-webp']) }}" alt="{{ $section['alt'] }}" width="{{ $image->variants['960_webp']['width'] }}" height="{{ $image->variants['960_webp']['height'] }}" loading="lazy">
                    </picture>
                @else
                    <img src="{{ route('public.media', [$image->id, 'original']) }}" alt="{{ $section['alt'] }}" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy">
                @endif
            </figure>
        @endif
    @endforeach
</main>
<footer class="site-footer"><a class="brand brand-small" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ $siteImages['logo_media_id']['url'] }}" alt="{{ $siteImages['logo_media_id']['alt'] }}"></a><p>Locally focused. Road ready.</p><span>© {{ date('Y') }} Taziva Motor Spares</span></footer>
</body>
</html>
