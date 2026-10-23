<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(): View
    {
        return view('management.promotions.index', ['products' => Product::with('category')->orderByDesc('sale_enabled')->orderBy('name')->paginate(15)]);
    }

    public function edit(Product $product): View
    {
        return view('management.promotions.form', compact('product'));
    }

    public function update(PromotionRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('promotions.index')->with('success', 'Informasi sale berhasil diperbarui.');
    }
}
