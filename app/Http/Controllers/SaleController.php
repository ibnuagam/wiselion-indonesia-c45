<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['product_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'start' => ['nullable', 'date_format:Y-m'], 'end' => ['nullable', 'date_format:Y-m', \Illuminate\Validation\Rule::when($request->filled('start'), 'after_or_equal:start')]]);
        $sales = Sale::query()->filtered($filters)->with(['product' => fn ($q) => $q->withTrashed()->with(['category' => fn ($q) => $q->withTrashed()])])
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->orderByDesc('year')->orderByDesc('month')->orderByDesc('id')->paginate(15)->withQueryString();
        return view('management.sales.index', ['sales' => $sales, 'products' => Product::orderBy('name')->get(), 'categories' => Category::orderBy('name')->get()]);
    }

    public function create(): View { return $this->form(new Sale(['month' => (int) date('n'), 'year' => (int) date('Y')])); }
    public function edit(Sale $sale): View { return $this->form($sale); }

    public function store(SaleRequest $request): RedirectResponse
    {
        Sale::create($this->data($request));
        return redirect()->route('sales.index')->with('success', 'Data penjualan berhasil ditambahkan.');
    }

    public function update(SaleRequest $request, Sale $sale): RedirectResponse
    {
        $sale->update($this->data($request));
        return redirect()->route('sales.index')->with('success', 'Data penjualan berhasil diperbarui.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $sale->delete();
        return back()->with('success', 'Data penjualan dipindahkan ke arsip; tidak disertakan dalam laporan.');
    }

    public function restore(Sale $sale): RedirectResponse
    {
        $sale->restore();
        return redirect()->route('sales.index')->with('success', 'Data penjualan berhasil dipulihkan.');
    }

    private function form(Sale $sale): View { return view('management.sales.form', ['sale' => $sale, 'products' => Product::orderBy('name')->get()]); }

    private function data(SaleRequest $request): array
    {
        $data = $request->safe()->except(['confirmed']);
        $data['reference_label'] = $request->input('reference_label') ?: null;
        $data['label_basis'] = $data['reference_label'] ? $request->input('label_basis') : null;
        $data['labelled_by'] = $data['reference_label'] ? $request->user()->id : null;
        $data['labelled_at'] = $data['reference_label'] ? now() : null;
        $priceInCents = (int) round((float) $data['unit_price'] * 100);
        $totalCents = $priceInCents * (int) $data['units_sold'];
        $data['total_revenue'] = intdiv($totalCents, 100).'.'.str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT);
        return $data;
    }
}
