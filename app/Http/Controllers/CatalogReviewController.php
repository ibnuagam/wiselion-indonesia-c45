<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogReviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'in:pending,reviewed']]);
        $query = Product::query()->whereNotNull('catalog_source');
        $total = (clone $query)->count();
        $pending = (clone $query)->where('catalog_reviewed', false)->count();

        return view('management.catalog-review.index', [
            'products' => $query->withCount('images')->with('category')
                ->when($request->input('status') === 'pending', fn ($q) => $q->where('catalog_reviewed', false))
                ->when($request->input('status') === 'reviewed', fn ($q) => $q->where('catalog_reviewed', true))
                ->orderBy('catalog_reviewed')->orderBy('name')->paginate(15)->withQueryString(),
            'total' => $total, 'pending' => $pending,
        ]);
    }
}
