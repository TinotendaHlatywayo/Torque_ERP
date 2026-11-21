<?php

use App\Http\Controllers\PublicCatalogueController;
use App\Http\Controllers\PublicReservationController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CmsMedia;
use App\Models\CmsPageVersion;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('public.home');

Route::get('/robots.txt', function () {
    $public = config('app.env') === 'production';
    $rules = $public
        ? "User-agent: *\nAllow: /\nDisallow: /app\nDisallow: /platform\nDisallow: /login\nDisallow: /api\nSitemap: ".rtrim(config('app.url'), '/')."/sitemap.xml\n"
        : "User-agent: *\nDisallow: /\n";

    return response($rules, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('public.robots');

Route::get('/sitemap.xml', function () {
    $urls = [rtrim(config('app.url'), '/').'/'];
    $slugs = CmsPageVersion::query()->join('cms_pages', 'cms_pages.id', '=', 'cms_page_versions.cms_page_id')
        ->whereNotNull('cms_pages.published_version')
        ->whereColumn('cms_pages.published_version', 'cms_page_versions.version')
        ->pluck('cms_page_versions.slug');
    foreach ($slugs as $slug) {
        $urls[] = rtrim(config('app.url'), '/').'/'.$slug;
    }
    $branch = Branch::query()->where('slug', config('catalog.public_branch_slug'))->where('status', Branch::STATUS_ACTIVE)->first();
    if ($branch) {
        $urls[] = rtrim(config('app.url'), '/').'/shop';
        Product::query()->where('branch_id', $branch->id)->where('is_active', true)->where('is_public', true)->whereNotNull('public_slug')->whereNotNull('public_description')
            ->where(fn ($query) => $query->whereNull('category_id')->orWhereHas('category', fn ($category) => $category->where('is_active', true)))
            ->pluck('public_slug')->each(function (string $slug) use (&$urls): void {
                $urls[] = rtrim(config('app.url'), '/').'/shop/'.$slug;
            });
        Category::query()->where('branch_id', $branch->id)->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true)->where('is_public', true))
            ->pluck('slug')->each(function (string $slug) use (&$urls): void {
                $urls[] = rtrim(config('app.url'), '/').'/shop/category/'.$slug;
            });
    }
    $entries = collect($urls)->map(fn (string $url) => '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>')->implode('');

    return response('<?xml version="1.0" encoding="UTF-8"?>'.
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</urlset>',
        200,
        ['Content-Type' => 'application/xml; charset=UTF-8'],
    );
})->name('public.sitemap');

Route::get('/shop', [PublicCatalogueController::class, 'index'])->name('shop.index');
Route::get('/shop/category/{categorySlug}', [PublicCatalogueController::class, 'category'])->name('shop.category');
Route::get('/shop/{publicSlug}/reserve', [PublicReservationController::class, 'form'])->name('reservation.form');
Route::get('/shop/{publicSlug}', [PublicCatalogueController::class, 'product'])->name('shop.product');
Route::post('/reservations', [PublicReservationController::class, 'store'])->middleware('throttle:5,1')->name('reservation.store');
Route::get('/reservation/{token}', [PublicReservationController::class, 'status'])->where('token', '[a-f0-9]{64}')->middleware('throttle:30,1')->name('reservation.status');
Route::get('/reservation/{token}/payment-return', [PublicReservationController::class, 'paynowReturn'])->where('token', '[a-f0-9]{64}')->middleware('throttle:12,1')->name('reservation.paynow-return');
Route::post('/reservation/{token}/cancel', [PublicReservationController::class, 'cancel'])->where('token', '[a-f0-9]{64}')->middleware('throttle:5,1')->name('reservation.cancel');

Route::get('/{slug}', function (string $slug) {
    $version = CmsPageVersion::query()->where('slug', $slug)
        ->whereHas('page', fn ($query) => $query->whereNotNull('published_version')
            ->whereColumn('cms_pages.published_version', 'cms_page_versions.version'))
        ->firstOrFail();
    $media = CmsMedia::query()->whereIn('id', $version->media_ids)->get()->keyBy('id');

    return view('public.page', compact('version', 'media'));
})->where('slug', '[a-z0-9-]+')->name('public.cms-page');

require __DIR__.'/auth.php';

// Public self-registration is disabled. This explicit route returns a genuine
// 404 instead of a 405 caused by the GET-only CMS slug route.
Route::post('/register', fn () => abort(404))->name('register.disabled');
