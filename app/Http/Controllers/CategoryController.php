<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $categories = Category::query()->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->when($request->input('q'), fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->withCount('products')->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();
        return view('management.categories.index', compact('categories'));
    }

    public function create(): View { return view('management.categories.form', ['category' => new Category]); }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));
        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View { return view('management.categories.form', compact('category')); }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));
        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return back()->withErrors(['category' => 'Kategori masih digunakan produk. Pindahkan produk ke kategori lain terlebih dahulu.']);
        }
        $category->delete();
        return back()->with('success', 'Kategori dipindahkan ke arsip dan dapat dipulihkan.');
    }

    public function restore(Category $category): RedirectResponse
    {
        $category->restore();
        return redirect()->route('categories.index')->with('success', 'Kategori berhasil dipulihkan.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($category)], 'description' => ['nullable', 'string', 'max:1000']]);
    }
}
