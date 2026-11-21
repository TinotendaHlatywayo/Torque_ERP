<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicCatalogueController extends Controller
{
    public function index(Request $request)
    {
        $branch = $this->branch();
        $query = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($query) > 80, 422, 'Search is limited to 80 characters.');

        $products = $this->products($branch)
            ->when($query !== '', fn ($builder) => $builder->where(function ($search) use ($query) {
                $search->where('products.name', 'like', '%'.$query.'%')
                    ->orWhere('products.public_description', 'like', '%'.$query.'%');
            }))
            ->orderBy('products.name')
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()->where('branch_id', $branch->id)->where('is_active', true)
            ->whereHas('products', fn ($builder) => $builder->where('is_active', true)->where('is_public', true))
            ->orderBy('name')->get(['name', 'slug']);

        return view('public.catalogue', [
            'branch' => $branch,
            'products' => $products,
            'categories' => $categories,
            'query' => $query,
            'category' => null,
            'title' => $query === '' ? 'Motor Spares Catalogue' : 'Search Results',
            'description' => $query === ''
                ? 'Browse published motor spares from Taziva Motor Spares in Rusape. Search our customer-facing catalogue and contact the local team to confirm availability.'
                : Str::limit('Search results for “'.$query.'” in the Taziva Motor Spares catalogue. Contact the local team to confirm current availability.', 155),
            'canonical' => rtrim(config('app.url'), '/').'/shop'.($products->currentPage() > 1 ? '?page='.$products->currentPage() : ''),
        ]);
    }

    public function category(Request $request, string $categorySlug)
    {
        $branch = $this->branch();
        $category = Category::query()->where('branch_id', $branch->id)->where('slug', $categorySlug)
            ->where('is_active', true)->whereHas('products', fn ($builder) => $builder->where('is_active', true)->where('is_public', true))
            ->firstOrFail();
        $query = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($query) > 80, 422, 'Search is limited to 80 characters.');
        $products = $this->products($branch)->where('products.category_id', $category->id)
            ->when($query !== '', fn ($builder) => $builder->where(function ($search) use ($query) {
                $search->where('products.name', 'like', '%'.$query.'%')
                    ->orWhere('products.public_description', 'like', '%'.$query.'%');
            }))
            ->orderBy('products.name')->paginate(12)->withQueryString();

        return view('public.catalogue', [
            'branch' => $branch,
            'products' => $products,
            'categories' => Category::query()->where('branch_id', $branch->id)->where('is_active', true)
                ->whereHas('products', fn ($builder) => $builder->where('is_active', true)->where('is_public', true))->orderBy('name')->get(['name', 'slug']),
            'query' => $query,
            'category' => $category,
            'title' => $category->name,
            'description' => Str::limit($query === ''
                ? 'Browse '.$category->name.' at Taziva Motor Spares in Rusape. Contact the local team to confirm current availability.'
                : 'Search results for “'.$query.'” in '.$category->name.' at Taziva Motor Spares. Contact the local team to confirm availability.', 155),
            'canonical' => rtrim(config('app.url'), '/').'/shop/category/'.$category->slug.($products->currentPage() > 1 ? '?page='.$products->currentPage() : ''),
        ]);
    }

    public function product(string $publicSlug)
    {
        $branch = $this->branch();
        $product = Product::query()->where('branch_id', $branch->id)->where('public_slug', $publicSlug)
            ->with(['category:id,name,slug,is_active', 'publicImage'])->firstOrFail();
        abort_unless($product->is_public && $product->public_description, 404);
        abort_unless($product->is_active, 410);
        abort_if($product->category && ! $product->category->is_active, 410);
        $related = $this->products($branch)
            ->where('products.category_id', $product->category_id)
            ->where('products.id', '<>', $product->id)
            ->orderBy('products.name')->limit(4)->get();
        $price = Money::format($product->selling_price_minor, $product->currency_code);
        $canonical = rtrim(config('app.url'), '/').'/shop/'.$product->public_slug;
        $description = Str::limit($product->public_description, 155);

        return view('public.product', compact('branch', 'product', 'related', 'price', 'canonical', 'description'));
    }

    private function branch(): Branch
    {
        return Branch::query()
            ->where('slug', config('catalog.public_branch_slug'))
            ->where('status', Branch::STATUS_ACTIVE)
            ->firstOrFail();
    }

    private function products(Branch $branch)
    {
        return Product::query()->where('products.branch_id', $branch->id)
            ->where('products.is_active', true)->where('products.is_public', true)
            ->whereNotNull('products.public_slug')->whereNotNull('products.public_description')
            ->where(fn ($builder) => $builder->whereNull('products.category_id')
                ->orWhereHas('category', fn ($category) => $category->where('is_active', true)))
            ->with(['category:id,name,slug', 'publicImage']);
    }
}
