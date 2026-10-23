<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Headline;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999', Rule::when($request->filled('min_price'), 'gte:min_price')],
            'collection' => ['nullable', Rule::in(['new', 'featured', 'sale'])],
            'budget' => ['nullable', Rule::in(['under250', '250to500', 'over500'])],
            'sort' => ['nullable', Rule::in(['name', 'latest', 'price_asc', 'price_desc'])],
        ]);
        $today = now('Asia/Jakarta')->toDateString();
        $bindings = [$today, $today];
        $priceSql = Product::catalogPriceSql();
        $query = Product::publicCatalog()->with('category:id,name')
            ->select('products.*')->selectRaw($priceSql.' as effective_price', $bindings)
            ->when($filters['q'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id));
        if (isset($filters['min_price'])) {
            $query->whereRaw('('.$priceSql.') >= CAST(? AS DECIMAL(15,2))', [...$bindings, $filters['min_price']]);
        }
        if (isset($filters['max_price'])) {
            $query->whereRaw('('.$priceSql.') <= CAST(? AS DECIMAL(15,2))', [...$bindings, $filters['max_price']]);
        }
        match ($filters['budget'] ?? null) {
            'under250' => $query->whereRaw('('.$priceSql.') < ?', [...$bindings, 250000]),
            '250to500' => $query->whereRaw('('.$priceSql.') >= ? AND ('.$priceSql.') < ?', [...$bindings, 250000, ...$bindings, 500000]),
            'over500' => $query->whereRaw('('.$priceSql.') >= ?', [...$bindings, 500000]),
            default => null,
        };
        match ($filters['collection'] ?? null) {
            'new' => $query->where('is_new', true),
            'featured' => $query->where('is_featured', true),
            'sale' => $query->whereRaw('('.$priceSql.') < price', $bindings),
            default => null,
        };
        match ($filters['sort'] ?? 'name') {
            'latest' => $query->orderByDesc('created_at'),
            'price_asc' => $query->orderBy('effective_price'),
            'price_desc' => $query->orderByDesc('effective_price'),
            default => $query->orderBy('name'),
        };
        $hasFilters = collect($filters)->contains(fn ($value) => $value !== null && $value !== '');

        return view('catalog', [
            'products' => $query->orderBy('id')->paginate(12)->withQueryString(),
            'categories' => Category::whereHas('products', fn (Builder $q) => $q->where('is_active', true))->orderBy('name')->get(['id', 'name']),
            'headlines' => Headline::visible()->with('product.category')->orderBy('sort_order')->orderBy('id')->get(),
            'newProducts' => $hasFilters ? collect() : Product::publicCatalog()->with('category')->where('is_new', true)->latest()->limit(4)->get(),
            'featuredProducts' => $hasFilters ? collect() : Product::publicCatalog()->with('category')->where('is_featured', true)->orderBy('name')->limit(4)->get(),
            'hasFilters' => $hasFilters,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->category()->exists(), 404);
        $product->load(['category', 'images', 'colors' => fn ($query) => $query->where('is_active', true)]);

        return view('product', compact('product'));
    }
}
