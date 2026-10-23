<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductColorRequest;
use App\Models\Product;
use App\Models\ProductColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ProductColorController extends Controller
{
    public function index(Product $product): View
    {
        return view('management.colors.index', ['product' => $product,
            'colors' => $product->colors()->withTrashed()->get(),
            'color' => new ProductColor(['is_active' => true, 'color_hex' => '#233c69', 'sort_order' => (int) $product->colors()->max('sort_order') + 1])]);
    }

    public function store(ProductColorRequest $request, Product $product): RedirectResponse
    {
        $this->persist($request, $product->colors()->make());

        return to_route('products.colors.index', $product)->with('success', 'Pilihan warna berhasil ditambahkan.');
    }

    public function edit(Product $product, ProductColor $color): View
    {
        return view('management.colors.edit', compact('product', 'color'));
    }

    public function update(ProductColorRequest $request, Product $product, ProductColor $color): RedirectResponse
    {
        $this->persist($request, $color);

        return to_route('products.colors.index', $product)->with('success', 'Pilihan warna berhasil diperbarui.');
    }

    public function destroy(Product $product, ProductColor $color): RedirectResponse
    {
        $color->delete();

        return to_route('products.colors.index', $product)->with('success', 'Warna diarsipkan. Data dan foto tetap tersimpan agar dapat dipulihkan.');
    }

    public function restore(Product $product, ProductColor $color): RedirectResponse
    {
        abort_if($product->trashed() || ! $color->trashed(), 404);
        $color->restore();

        return to_route('products.colors.index', $product)->with('success', 'Pilihan warna berhasil dipulihkan.');
    }

    private function persist(ProductColorRequest $request, ProductColor $color): void
    {
        $data = $request->safe()->except(['photo', 'remove_photo', 'name_key']);
        $path = null;
        try {
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('products/colors', 'public');
                abort_if($path === false, 500, 'Foto warna gagal disimpan.');
                $data['photo_path'] = $path;
            } elseif ($request->boolean('remove_photo')) {
                $data['photo_path'] = null;
            }
            DB::transaction(fn () => $color->fill($data)->save());
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }
    }
}
