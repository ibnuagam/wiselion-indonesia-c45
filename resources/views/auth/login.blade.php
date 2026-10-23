<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d1823">
    <title>Masuk · Wiselion Indonesia</title>
    <link rel="stylesheet" href="{{ asset('css/wiselion.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}?v=3">
    <script src="{{ asset('js/wiselion.js') }}?v=3" defer></script>
</head>
<body class="login-page reference-login">
<main class="reference-shell">
    <section class="reference-visual" aria-label="Ilustrasi smartwatch, earbuds, dan analitik penjualan">
        <img src="{{ asset('images/wiselion-login-hd.png') }}" alt="Ilustrasi smartwatch dan earbuds dengan diagram analitik" width="1374" height="1145">
        <span class="reference-caption">Ilustrasi produk · Wiselion Business Portal</span>
    </section>
    <section class="reference-form-side" aria-labelledby="login-title">
        <div class="reference-form-content">
            <a class="reference-brand" href="{{ route('catalog') }}" aria-label="Wiselion Indonesia, lihat katalog"><x-brand /></a>
            <p class="reference-subtitle">Sistem Pendukung Keputusan · Klasifikasi C4.5</p>
            <h1 id="login-title" class="sr-only">Masuk ke akun Anda</h1>
            @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('login.store') }}" data-login-form class="reference-form">
                @csrf
                <label class="sr-only" for="email">Alamat email</label>
                <input id="email" name="email" type="email" autocomplete="username" placeholder="Alamat email" value="{{ old('email') }}" required @error('email') aria-invalid="true" @enderror>
                <label class="sr-only" for="password">Password</label>
                <div class="reference-password"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Password" required><button type="button" class="password-toggle" data-password-toggle aria-label="Tampilkan password" aria-controls="password" aria-pressed="false"><x-icon name="eye" /></button></div>
                <button type="submit" class="button reference-submit"><span>Masuk</span></button>
                <details class="password-help"><summary>Lupa password?</summary><p>Hubungi Admin perusahaan untuk bantuan pemulihan akun Anda. Pemulihan melalui email belum tersedia.</p></details>
                <label class="checkbox-label reference-remember"><input name="remember" type="checkbox" value="1" @checked(old('remember'))> Ingat saya</label>
            </form>
            <div class="reference-public"><a href="{{ route('catalog') }}">Jelajahi katalog tanpa login <span aria-hidden="true">↗</span></a></div>
        </div>
        <footer class="reference-footer">© {{ date('Y') }} PT Wiselion Trading Indonesia</footer>
    </section>
</main>
</body>
</html>
