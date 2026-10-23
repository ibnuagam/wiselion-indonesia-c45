<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Portal') · Wiselion Indonesia</title>
    <link rel="stylesheet" href="{{ asset('css/wiselion.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/color-options.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/analysis.css') }}?v=1">
    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/wiselion.js') }}?v=2" defer></script>
</head>
<body class="portal-page">
    <aside class="sidebar">
        <a class="brand-link" href="{{ route('dashboard') }}"><x-brand /></a>
        <p class="nav-label">RUANG KERJA</p>
        <nav class="side-nav" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" @class(['nav-active' => request()->routeIs('dashboard', 'admin.dashboard', 'manager.dashboard')])><x-icon name="grid" /> Ringkasan bisnis</a>
            <a href="{{ route('analyses.index') }}" @class(['nav-active' => request()->routeIs('analyses.*')])><x-icon name="chart" /> Analisis C4.5</a>
            @if(auth()->user()->isAdmin())
            <a href="{{ route('catalog-review.index') }}" @class(['nav-active' => request()->routeIs('catalog-review.*')])><x-icon name="box" /> Tinjauan katalog</a>
            <a href="{{ route('headlines.index') }}" @class(['nav-active' => request()->routeIs('headlines.*')])><x-icon name="grid" /> Headline beranda</a>
            <a href="{{ route('promotions.index') }}" @class(['nav-active' => request()->routeIs('promotions.*')])><x-icon name="box" /> Promo & Sale</a>
            <a href="{{ route('categories.index') }}" @class(['nav-active' => request()->routeIs('categories.*')])><x-icon name="grid" /> Kategori produk</a>
            <a href="{{ route('products.index') }}" @class(['nav-active' => request()->routeIs('products.*')])><x-icon name="box" /> Kelola produk</a>
            <a href="{{ route('sales.index') }}" @class(['nav-active' => request()->routeIs('sales.*')])><x-icon name="chart" /> Data penjualan</a>
            <a href="{{ route('sales-imports.index') }}" @class(['nav-active' => request()->routeIs('sales-imports.*')])><x-icon name="chart" /> Impor penjualan</a>
            @endif
            <a href="{{ route('catalog') }}"><x-icon name="box" /> Katalog produk <span>↗</span></a>
        </nav>
        <div class="theme-control"><span>TAMPILAN</span><div role="group" aria-label="Tema tampilan"><button type="button" data-set-theme="light" aria-pressed="true">☀ Terang</button><button type="button" data-set-theme="dark" aria-pressed="false">☾ Gelap</button></div></div>
        <div class="sidebar-bottom"><span class="workspace-indicator"></span><div><strong>Wiselion Indonesia</strong><small>Portal {{ ucfirst(auth()->user()->role) }}</small></div></div>
    </aside>
    <div class="portal-body">
        <header class="portal-header"><span>Workspace <span class="breadcrumb-separator">/</span> @yield('title', 'Ringkasan')</span><div class="profile"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }}</small></span><form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Keluar dari akun" title="Keluar"><x-icon name="logout" /></button></form></div></header>
        <main class="portal-content">@if(session('success'))<div class="success-alert" role="status">{{ session('success') }}</div>@endif @if($errors->any())<div class="form-alert" role="alert"><strong>Periksa kembali data Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @yield('content')</main>
        <footer class="portal-footer">© {{ date('Y') }} PT Wiselion Trading Indonesia <span>Sales Intelligence</span></footer>
    </div>
</body>
</html>
