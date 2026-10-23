<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Jam tangan modern') · Wiselion Indonesia</title>
    <link rel="stylesheet" href="{{ asset('css/wiselion.css') }}?v=2">
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/catalog-details.css') }}?v=1">
    <script src="{{ asset('js/storefront.js') }}?v=5" defer></script>
    <link rel="stylesheet" href="{{ asset('css/color-options.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/sale-labels.css') }}?v=1">
</head>
<body class="watch-store">
    <a href="#main" class="skip-link">Lewati ke konten</a>
    <div class="store-utility"><span>WISELION INDONESIA</span><a href="#tentang">Modern watches. Everyday style.</a></div>
    <header class="store-header">
        <a href="{{ route('catalog') }}" class="brand-link" aria-label="Wiselion — Beranda"><x-brand /></a>
        <nav class="store-nav" id="store-navigation" aria-label="Navigasi koleksi">
            <a href="{{ route('catalog') }}" @if(request()->routeIs('catalog') && !request('collection')) aria-current="page" @endif>Beranda</a>
            <a href="{{ route('catalog') }}#koleksi">Koleksi</a>
            <a href="{{ route('catalog', ['collection' => 'new']) }}#koleksi" @if(request('collection') === 'new') aria-current="page" @endif>Produk terbaru</a>
            <a href="{{ route('catalog', ['collection' => 'featured']) }}#koleksi" @if(request('collection') === 'featured') aria-current="page" @endif>Pilihan Wiselion</a>
            <a class="sale-link" href="{{ route('catalog', ['collection' => 'sale']) }}#koleksi" @if(request('collection') === 'sale') aria-current="page" @endif>Sale</a>
        </nav>
        <div class="store-tools"><button type="button" class="store-icon" data-search-toggle aria-controls="store-search" aria-expanded="false" aria-label="Buka pencarian"><x-icon name="search" /></button><button type="button" class="store-icon theme-toggle" data-store-theme-toggle aria-label="Aktifkan mode gelap" aria-pressed="false"><span data-store-theme-label aria-hidden="true">☾</span></button>
        @auth
            @if(in_array(auth()->user()->role, ['admin', 'manager'], true))<a class="account-link" href="{{ route('dashboard') }}">Ruang kerja ↗</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="account-link" type="submit">Keluar</button></form>
        @else<a class="account-link" href="{{ route('login') }}">Akun <span aria-hidden="true">↗</span></a>@endauth
        <button type="button" class="store-icon menu-toggle" data-menu-toggle aria-controls="store-navigation" aria-expanded="false" aria-label="Buka menu"><span aria-hidden="true">☰</span></button></div>
    </header>
    <form id="store-search" class="store-search" method="GET" action="{{ route('catalog') }}#koleksi" hidden><label for="global-search">Temukan jam tangan Anda</label><input id="global-search" type="search" name="q" maxlength="120" placeholder="Cari model atau kode produk…" value="{{ request('q') }}"><button class="store-button">Cari</button></form>
    <main id="main">@if($errors->any())<div class="store-error" role="alert"><strong>Periksa filter Anda.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif @yield('content')</main>
    <footer class="store-footer" id="tentang"><div><x-brand /><p>Jam tangan modern untuk setiap ritme hidup.</p></div><div><strong>Jelajahi Wiselion</strong><a href="{{ route('catalog') }}#koleksi">Semua koleksi</a><a href="{{ route('catalog', ['collection' => 'sale']) }}#koleksi">Promo & sale</a><a href="{{ route('login') }}">Akses akun</a></div><div><strong>Informasi koleksi</strong><p>Harga dan ketersediaan dapat berubah.<br>Foto serta detail produk diperbarui secara berkala.</p></div><small>© {{ date('Y') }} PT Wiselion Trading Indonesia</small></footer>
</body>
</html>
