<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Analysis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'start' => ['nullable', 'date_format:Y-m'],
            'end' => ['nullable', 'date_format:Y-m', Rule::when($request->filled('start'), 'after_or_equal:start')],
            'product_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'],
        ]);
        $productQuery = Product::query()->with(['category' => fn (\Illuminate\Database\Eloquent\Relations\BelongsTo $q) => $q->withTrashed()])
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id));
        $productRows = $productQuery->withSum(['sales' => fn (Builder $q) => $q->filtered($filters)], 'units_sold')
            ->withSum(['sales' => fn (Builder $q) => $q->filtered($filters)], 'order_frequency')
            ->withSum(['sales' => fn (Builder $q) => $q->filtered($filters)], 'total_revenue')->orderBy('name')->get();
        $classified = $productRows->map(fn (Product $product): array => [
            'product' => $product,
            'units_sold' => (int) ($product->sales_sum_units_sold ?? 0),
            'order_frequency' => (int) ($product->sales_sum_order_frequency ?? 0),
            'total_revenue' => (float) ($product->sales_sum_total_revenue ?? 0),
        ])->sortByDesc('total_revenue')->values();
        $sales = Sale::query()->filtered($filters)->with(['product' => fn (\Illuminate\Database\Eloquent\Relations\BelongsTo $q) => $q->withTrashed()->with(['category' => fn (\Illuminate\Database\Eloquent\Relations\BelongsTo $q) => $q->withTrashed()])])->get();
        $monthly = $sales->groupBy(fn (Sale $sale): string => sprintf('%04d-%02d', $sale->year, $sale->month))
            ->sortKeys()->map(fn ($rows, $period): array => ['label' => $period, 'revenue' => (float) $rows->sum('total_revenue'), 'units' => (int) $rows->sum('units_sold')])->values();
        $categoryRevenue = $sales->groupBy(fn (Sale $sale): string => $sale->product->category->name)
            ->map(fn ($rows): float => (float) $rows->sum('total_revenue'))->sortDesc();

        return view('dashboard', [
            'products' => $classified->take(10)->all(), 'totalProducts' => $productRows->count(),
            'totalUnits' => $sales->sum('units_sold'), 'totalRevenue' => $sales->sum('total_revenue'), 'totalOrders' => $sales->sum('order_frequency'),
            'monthly' => $monthly->take(-12)->values(), 'monthlyCount' => $monthly->count(), 'categoryRevenue' => $categoryRevenue,
            'latestAnalysis' => Analysis::where('status', 'completed')->where('data_origin', 'real')->whereNotNull('algorithm_version')->latest('id')->first(),
            'yearly' => $sales->groupBy('year')->sortKeys()->map(fn ($rows): array => ['units' => $rows->sum('units_sold'), 'revenue' => $rows->sum('total_revenue')]),
            'categories' => Category::orderBy('name')->get(), 'filterProducts' => Product::orderBy('name')->get(),
        ]);
    }
}
