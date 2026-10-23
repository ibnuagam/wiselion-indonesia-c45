@props(['product'])
<article class="watch-card">
    <a href="{{ route('catalog.show', $product) }}" class="watch-image" aria-label="Lihat {{ $product->name }}">
        @if(!$product->is_on_sale && ($product->is_new || $product->is_featured))<div class="watch-tags"><span>{{ $product->is_new ? 'BARU' : 'PILIHAN WISELION' }}</span></div>@endif
        @if($product->photo_url)<img src="{{ $product->photo_url }}" alt="{{ $product->name }}" loading="lazy" width="500" height="550">@else<div class="watch-unavailable"><x-icon name="box" /><span>WISELION</span><small>Foto produk segera hadir</small></div>@endif
        <span class="watch-discover">Lihat detail <span aria-hidden="true">↗</span></span>
    </a>
    <div class="watch-info">
        <small>{{ $product->category?->name ?? 'Wiselion' }}</small>
        <h3><a href="{{ route('catalog.show', $product) }}">{{ $product->name }}</a></h3>
        <p class="watch-code">{{ $product->code }}</p>
        <div class="watch-price">
            @if($product->is_on_sale)<del>Rp {{ number_format($product->price, 0, ',', '.') }}</del>@elseif($product->reference_price_max)<small class="price-start">Mulai</small>@endif
            <strong>Rp {{ number_format($product->display_price, 0, ',', '.') }}</strong>
            @if($product->is_on_sale)<span class="watch-sale-badge">{{ $product->discount_percent }}% OFF</span>@endif
        </div>
        <span class="budget-label">{{ $product->price_group }}</span>
        @if($product->is_on_sale && $product->sale_title)<p class="watch-sale-title">{{ $product->sale_title }}</p>@endif
    </div>
</article>
