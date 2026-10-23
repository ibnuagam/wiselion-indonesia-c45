document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.getAttribute('aria-controls'));
        const visible = field.type === 'password';
        field.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
    });
});
document.querySelectorAll('[data-login-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        button.querySelector('span').textContent = 'Memeriksa akun…';
    });
});

const refreshThemeButtons = () => document.querySelectorAll('[data-set-theme]').forEach((button) => {
    button.setAttribute('aria-pressed', String(button.dataset.setTheme === document.documentElement.dataset.theme));
});
refreshThemeButtons();
document.querySelectorAll('[data-set-theme]').forEach((button) => {
    button.addEventListener('click', () => {
        document.documentElement.dataset.theme = button.dataset.setTheme;
        try { localStorage.setItem('wiselion-theme', button.dataset.setTheme); } catch {}
        refreshThemeButtons();
    });
});
document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) { event.preventDefault(); }
}));
document.querySelectorAll('[data-photo-input]').forEach((input) => {
    let previewUrl;
    input.addEventListener('change', () => {
        const preview = document.querySelector('[data-photo-preview]');
        const file = input.files[0];
        if (!file || !preview) { return; }
        if (previewUrl) { URL.revokeObjectURL(previewUrl); }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
    });
});
document.querySelectorAll('[data-sales-form]').forEach((form) => {
    const units = form.querySelector('[name="units_sold"]');
    const price = form.querySelector('[name="unit_price"]');
    const product = form.querySelector('[name="product_id"]');
    const updateRevenue = () => {
        const total = (Number(units.value) || 0) * (Number(price.value) || 0);
        form.querySelector('[data-revenue-preview]').textContent = new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:2}).format(total);
    };
    units.addEventListener('input', updateRevenue);
    price.addEventListener('input', updateRevenue);
    product.addEventListener('change', () => {
        price.value = product.selectedOptions[0]?.dataset.price || '';
        updateRevenue();
    });
    updateRevenue();
});
