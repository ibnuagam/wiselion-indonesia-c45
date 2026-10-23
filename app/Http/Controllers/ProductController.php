<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'category_id' => ['nullable', 'integer']]);
        $products = Product::query()->with('category')->withCount(['sales', 'colors'])
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->when($request->input('q'), fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->when($request->input('category_id'), fn ($q, $category) => $q->where('category_id', $category))
            ->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();
        return view('management.products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create(): View { return $this->form(new Product(['is_active' => true])); }
    public function edit(Product $product): View { return $this->form($product); }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->persist($request, new Product);
        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->persist($request, $product);
        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->sales()->withTrashed()->exists()) {
            return back()->withErrors(['product' => 'Produk memiliki riwayat penjualan. Ubah status menjadi Nonaktif untuk menyembunyikannya dari katalog.']);
        }
        $product->delete();
        return back()->with('success', 'Produk dipindahkan ke arsip dan dapat dipulihkan.');
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->restore();
        return redirect()->route('products.index')->with('success', 'Produk berhasil dipulihkan.');
    }

    private function form(Product $product): View
    {
        $product->load('images');
        return view('management.products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]);
    }

    private function persist(ProductRequest $request, Product $product): void
    {
        $data = $request->safe()->except(['photo', 'remove_photo', 'gallery', 'remove_gallery', 'specifications_text']);
        if ($request->exists('specifications_text')) {
            $data['specifications'] = collect(preg_split('/\R/u', $request->input('specifications_text') ?? ''))->filter(fn (string $line): bool => trim($line) !== '')->mapWithKeys(function (string $line): array {
                [$key, $value] = explode(':', $line, 2);
                return [trim($key) => trim($value)];
            })->all();
        }
        $photo = null;
        $uploads = [];
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo')->store('products', 'public');
            abort_if($photo === false, 500, 'Foto gagal disimpan.');
            $data['photo_path'] = $photo;
            $uploads[] = $photo;
        } elseif ($request->boolean('remove_photo')) { $data['photo_path'] = null; }
        try {
            DB::transaction(function () use ($request, $product, $data, &$uploads): void {
                $product->fill($data)->save();
                if ($request->input('remove_gallery')) {
                    $product->images()->whereIn('id', $request->input('remove_gallery'))->delete();
                }
                $position = (int) $product->images()->max('sort_order');
                foreach ($request->file('gallery', []) as $file) {
                    $path = $file->store('products/gallery', 'public');
                    abort_if($path === false, 500, 'Foto galeri gagal disimpan.');
                    $uploads[] = $path;
                    $product->images()->create(['path' => $path, 'alt_text' => $product->name, 'sort_order' => ++$position]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($uploads);
            throw $exception;
        }
    }
}
