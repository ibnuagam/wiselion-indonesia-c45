@extends('layouts.portal')
@section('title', 'Pilihan warna produk')
@section('content')
<div class="page-heading"><div><p class="section-label">PRODUCT COLORS</p><h1>Pilihan warna</h1><p>{{ $product->name }} · {{ $product->code }}</p></div><a class="button button-secondary" href="{{ route('products.edit', $product) }}">Kembali ke produk</a></div>
<div class="color-admin-intro"><div><strong>Satu warna, satu foto yang tepat.</strong><p>Kelola pilihan yang muncul di bawah galeri pelanggan. Foto warna diunggah terpisah dari galeri umum produk.</p></div><a class="button button-secondary" href="{{ route('catalog.show', $product) }}">Lihat halaman pelanggan ↗</a></div>
<div class="color-cms-layout">
    <section class="color-admin-list" aria-label="Daftar pilihan warna">
        <div class="color-section-heading"><h2>Warna produk</h2><span>{{ $colors->whereNull('deleted_at')->count() }} pilihan · {{ $colors->whereNotNull('deleted_at')->count() }} arsip</span></div>
        @forelse($colors as $item)
        <article class="color-admin-card {{ $item->trashed() ? 'is-archived' : '' }}">
            <div class="color-admin-photo">@if($item->photo_url)<img src="{{ $item->photo_url }}" alt="{{ $item->name }}" loading="lazy" width="120" height="120">@else<span class="color-swatch" style="--swatch:{{ $item->color_hex }}" aria-hidden="true"></span><small>Belum ada foto</small>@endif</div>
            <div class="color-admin-copy"><h3>{{ $item->name }}</h3><p>{{ $item->color_hex }} · Urutan {{ $item->sort_order }}</p><span class="class-badge {{ !$item->trashed() && $item->is_active ? 'class-high' : 'class-low' }}">{{ $item->trashed() ? 'Diarsipkan' : ($item->is_active ? 'Tampil ke pelanggan' : 'Draf / nonaktif') }}</span><div class="row-actions">
                @if($item->trashed())<form method="POST" action="{{ route('products.colors.restore', [$product, $item]) }}">@csrf @method('PATCH')<button class="text-button">Pulihkan warna {{ $item->name }}</button></form>
                @else<a class="text-button" href="{{ route('products.colors.edit', [$product, $item]) }}">Edit / unggah foto {{ $item->name }}</a><form method="POST" action="{{ route('products.colors.destroy', [$product, $item]) }}" data-confirm="Arsipkan pilihan warna ini? Foto tetap tersimpan.">@csrf @method('DELETE')<button class="text-button danger">Arsipkan {{ $item->name }}</button></form>@endif
            </div></div>
        </article>
        @empty<div class="empty-state">Belum ada pilihan warna. Tambahkan warna pertama melalui formulir di samping.</div>@endforelse
    </section>
    <section class="editor-panel color-add-panel"><p class="section-label">TAMBAH PILIHAN</p><h2>Warna baru</h2><form method="POST" action="{{ route('products.colors.store', $product) }}" enctype="multipart/form-data">@csrf @include('management.colors.fields')</form></section>
</div>
@endsection
