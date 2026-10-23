<?php

namespace App\Http\Controllers;

use App\Http\Requests\HeadlineRequest;
use App\Models\Headline;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class HeadlineController extends Controller
{
    public function index(Request $request): View
    {
        return view('management.headlines.index', ['headlines' => Headline::query()->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())->orderBy('sort_order')->orderByDesc('id')->paginate(12)->withQueryString()]);
    }

    public function create(): View
    {
        return $this->form(new Headline(['theme' => 'dark', 'sort_order' => 0, 'button_text' => 'Jelajahi koleksi', 'is_published' => false]));
    }

    public function edit(Headline $headline): View
    {
        return $this->form($headline);
    }

    public function store(HeadlineRequest $request): RedirectResponse
    {
        $this->persist($request, new Headline);

        return redirect()->route('headlines.index')->with('success', 'Headline berhasil ditambahkan.');
    }

    public function update(HeadlineRequest $request, Headline $headline): RedirectResponse
    {
        $this->persist($request, $headline);

        return redirect()->route('headlines.index')->with('success', 'Headline berhasil diperbarui.');
    }

    public function destroy(Headline $headline): RedirectResponse
    {
        $headline->delete();

        return back()->with('success', 'Headline diarsipkan dan tidak tampil di beranda.');
    }

    public function restore(Headline $headline): RedirectResponse
    {
        $headline->restore();

        return redirect()->route('headlines.index')->with('success', 'Headline dipulihkan. Periksa status dan jadwal tayangnya.');
    }

    private function form(Headline $headline): View
    {
        return view('management.headlines.form', ['headline' => $headline, 'products' => Product::publicCatalog()->orderBy('name')->get()]);
    }

    private function persist(HeadlineRequest $request, Headline $headline): void
    {
        $data = $request->safe()->except(['image', 'mobile_image', 'remove_image', 'remove_mobile_image']);
        $uploads = [];
        try {
            foreach (['image' => 'image_path', 'mobile_image' => 'mobile_image_path'] as $input => $column) {
                if ($request->hasFile($input)) {
                    $path = $request->file($input)->store('headlines', 'public');
                    abort_if($path === false, 500, 'Gambar gagal disimpan.');
                    $uploads[] = $path;
                    $data[$column] = $path;
                } elseif ($request->boolean('remove_'.$input)) {
                    $data[$column] = null;
                }
            }
            DB::transaction(fn () => $headline->fill($data)->save());
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($uploads);
            throw $exception;
        }
    }
}
