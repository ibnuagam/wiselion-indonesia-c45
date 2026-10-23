@extends('layouts.storefront')
@section('title', $product->name)
@section('content')
@php
    $gallery = $product->images->map(fn ($image) => ['url' => $image->url, 'alt' => $image->alt_text ?: $product->name]);
    if ($product->photo_url) { $gallery->prepend(['url' => $product->photo_url, 'alt' => $product->name]); }
    $gallery = $gallery->unique('url')->values();
@endphp
<div class="product-detail"><nav class="detail-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('catalog') }}#koleksi">Koleksi</a><span>/</span><span>{{ $product->name }}</span></nav>
<div class="product-detail-grid">
    <section class="product-gallery" aria-label="Galeri {{ $product->name }}" data-product-gallery>
        <div class="detail-photo">
            <img data-gallery-main @if($gallery->isNotEmpty()) src="{{ $gallery[0]['url'] }}" @else hidden @endif alt="{{ $gallery[0]['alt'] ?? $product->name }}" width="900" height="900">
            <button type="button" class="gallery-zoom" data-gallery-zoom @if($gallery->isEmpty()) hidden @endif>Perbesar foto ⤢</button>
            <div class="watch-unavailable color-photo-placeholder" data-gallery-placeholder @if($gallery->isNotEmpty()) hidden @endif><x-icon name="box" /><span>WISELION</span><small data-gallery-placeholder-label>Foto produk segera hadir</small></div>
        </div>
        <div class="gallery-caption"><span data-gallery-caption aria-live="polite">{{ $gallery->isNotEmpty() ? 'Foto 1 dari '.$gallery->count() : 'Foto produk belum tersedia' }}</span><span>Koleksi Wiselion</span></div>
        @if($gallery->isNotEmpty())<div class="gallery-thumbnails">@foreach($gallery as $image)<button type="button" data-gallery-thumb data-src="{{ $image['url'] }}" data-alt="{{ $image['alt'] }}" aria-label="Lihat foto {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><img src="{{ $image['url'] }}" alt="" loading="lazy" width="100" height="100"></button>@endforeach</div>@endif
        @if($product->colors->isNotEmpty())
        <section class="product-color-picker" aria-labelledby="color-picker-title">
            <div class="color-section-heading"><div><p class="color-eyebrow">PERSONALISASI PILIHAN ANDA</p><h2 id="color-picker-title">Pilihan warna <span>{{ $product->colors->count() }}</span></h2></div><button type="button" class="color-reset" data-color-reset hidden>Reset pilihan</button></div>
            <p class="color-selection-label" data-color-selection aria-live="polite">Pilih warna untuk melihat foto khususnya.</p>
            <div class="product-color-options" role="group" aria-label="Pilihan warna produk">
                @foreach($product->colors as $color)
                <button type="button" class="product-color-option" data-color-option data-color-name="{{ $color->name }}" data-src="{{ $color->photo_url ?? '' }}" data-alt="{{ $product->name.' — '.$color->name }}" aria-label="Pilih warna {{ $color->name }}" aria-pressed="false">
                    @if($color->photo_url)<img src="{{ $color->photo_url }}" alt="" loading="lazy" width="48" height="48">@else<span class="color-swatch" style="--swatch:{{ $color->color_hex }}" aria-hidden="true"></span>@endif
                    <span>{{ $color->name }}</span><span class="color-check" aria-hidden="true">✓</span>
                </button>
                @endforeach
            </div>
            <p class="color-picker-note">Foto tiap warna ditambahkan secara bertahap. Pilihan ini untuk melihat tampilan, bukan informasi stok.</p>
            <noscript><p>Aktifkan JavaScript untuk mengganti foto berdasarkan warna.</p></noscript>
        </section>
        @endif
        <dialog class="gallery-lightbox" aria-label="Foto produk diperbesar"><button type="button" aria-label="Tutup foto diperbesar">Tutup ×</button><img @if($gallery->isNotEmpty()) src="{{ $gallery[0]['url'] }}" @endif alt="{{ $gallery[0]['alt'] ?? $product->name }}"></dialog>
    </section>
    <div class="detail-copy"><p class="detail-eyebrow">{{ $product->category->name }}</p><h1>{{ $product->name }}</h1><p class="watch-code">{{ $product->code }}</p><span class="budget-label">{{ $product->price_group }}</span>
        <div class="watch-price">@if($product->is_on_sale)<span class="tag-sale">{{ $product->discount_percent }}% OFF</span><del>Rp {{ number_format($product->price, 0, ',', '.') }}</del>@elseif($product->reference_price_max)<small>Mulai dari</small>@endif<strong>Rp {{ number_format($product->display_price, 0, ',', '.') }}</strong></div>
        @if($product->reference_price_max && !$product->is_on_sale)<p class="price-reference">Rentang varian: Rp {{ number_format($product->price, 0, ',', '.') }} – Rp {{ number_format($product->reference_price_max, 0, ',', '.') }}</p>@endif
        @if($product->price_note)<p class="price-reference">{{ $product->price_note }}</p>@endif
        @if($product->is_on_sale)<div class="detail-sale"><strong>{{ $product->sale_title ?: 'Penawaran spesial Wiselion' }}</strong>@if($product->sale_ends_on)<p>Berlaku sampai {{ $product->sale_ends_on->format('d/m/Y') }} WIB.</p>@endif</div>@endif
        @if($product->specifications)<section class="specifications"><h2>Fitur & spesifikasi</h2><dl>@foreach($product->specifications as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></section>@endif
        <details class="product-description" open><summary>Deskripsi lengkap</summary><div class="detail-description">{{ $product->description ?: 'Informasi lengkap produk akan segera tersedia.' }}</div></details>
        <a href="{{ route('catalog') }}#koleksi" class="store-button">Kembali ke koleksi</a>
    </div>
</div></div>
@endsection
