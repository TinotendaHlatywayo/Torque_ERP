<!doctype html>
<html lang="en-ZW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <meta name="description" content="Find the right motor spares with Taziva Motor Spares in Rusape. TorqueERP keeps our catalogue and service moving.">
    <meta name="theme-color" content="#078878">
    <link rel="canonical" href="{{ rtrim(config('app.url'), '/') }}/">
    <link rel="icon" type="image/png" href="{{ asset('tms_logo_transparent.png') }}">
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
    <title>Taziva Motor Spares | TorqueERP</title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Taziva Motor Spares">
    <meta property="og:title" content="Taziva Motor Spares | TorqueERP">
    <meta property="og:description" content="Find the right motor spares with Taziva Motor Spares in Rusape.">
    <meta property="og:url" content="{{ rtrim(config('app.url'), '/') }}/">
    <meta property="og:image" content="{{ asset('images/torqueerp-social.svg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="en_ZW">
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">{!! json_encode([
        '@'.'context' => 'https://schema.org',
        '@type' => 'AutoPartsStore',
        'name' => 'Taziva Motor Spares',
        'url' => rtrim(config('app.url'), '/').'/',
        'description' => 'Motor spares retailer in Rusape, Zimbabwe.',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Rusape',
            'addressCountry' => 'ZW',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <a class="brand" href="{{ route('public.home') }}" aria-label="Taziva Motor Spares home">
        <img class="brand-logo" src="{{ asset('tms_logo_transparent.png') }}" alt="TMS home">
    </a>
    <nav aria-label="Main navigation">
        <a href="#why-taziva">Why Taziva</a>
        <a href="{{ rtrim(config('app.frontend_url'), '/') }}/login" class="nav-signin">Team sign in <span aria-hidden="true">↗</span></a>
    </nav>
</header>
<main id="main">
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
            <p class="eyebrow"><span class="status-dot"></span> Your local motor spares partner</p>
            <h1 id="hero-title">The right part.<br><span>Back on the road.</span></h1>
            <p class="hero-intro">Find dependable motor spares with a team that knows the road ahead. Proudly serving drivers and workshops in Rusape.</p>
            <a class="primary-link" href="{{ route('shop.index') }}">Browse motor spares <span aria-hidden="true">→</span></a>
            <div class="hero-note"><span class="note-icon" aria-hidden="true">✳</span><span>Local knowledge, practical service<br><strong>Right here in Rusape, Zimbabwe</strong></span></div>
        </div>
        <div class="hero-art" role="img" aria-label="Illustration of a motor wheel and precision tools">
            <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
            <div class="wheel"><div class="wheel-inner"><span class="wheel-hub"></span><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
            <div class="art-card"><span class="art-card-icon" aria-hidden="true">✓</span><span><strong>Ready for the road</strong><small>Parts picked with care</small></span></div>
            <span class="art-label">TMS <b>·</b> RUSAPE</span>
        </div>
        <div class="hero-index" aria-hidden="true">01 <span></span> 03</div>
    </section>
    <section class="trust-strip" aria-label="What to expect">
        <p><span>01</span> A local team that listens</p><p><span>02</span> Practical parts knowledge</p><p><span>03</span> Service built around you</p>
    </section>
    <section id="why-taziva" class="intro-section">
        <div><p class="eyebrow">A better kind of parts run</p><h2>Good service moves<br>everything forward.</h2></div>
        <div class="intro-body"><p>Every vehicle depends on the details. We’re building a more helpful way to find the parts you need, backed by the people and experience of Taziva Motor Spares.</p><a href="{{ rtrim(config('app.frontend_url'), '/') }}/login" class="text-link">Team member? Sign in <span aria-hidden="true">↗</span></a></div>
    </section>
</main>
<footer class="site-footer"><a class="brand brand-small" href="{{ route('public.home') }}"><img class="brand-logo" src="{{ asset('tms_logo_transparent.png') }}" alt="TMS home"></a><p>Locally focused. Road ready.</p><span>© {{ date('Y') }} Taziva Motor Spares</span></footer>
</body>
</html>
