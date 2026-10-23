document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = [...carousel.querySelectorAll('[data-slide]')];
    const dots = [...carousel.querySelectorAll('[data-carousel-dot]')];
    if (slides.length < 2) { return; }
    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = 6000;
    let current = 0;
    let timer;
    let hovered = false;
    let focused = carousel.contains(document.activeElement);
    const schedule = () => {
        window.clearTimeout(timer);
        if (motionPreference.matches || hovered || focused || document.hidden) { return; }
        timer = window.setTimeout(() => { show(current + 1); schedule(); }, interval);
    };
    const show = (index) => {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => { slide.hidden = i !== current; });
        dots.forEach((dot, i) => dot.setAttribute('aria-pressed', String(i === current)));
    };
    const navigate = (index) => { show(index); schedule(); };
    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => navigate(current - 1));
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => navigate(current + 1));
    dots.forEach((dot, i) => dot.addEventListener('click', () => navigate(i)));
    carousel.addEventListener('mouseenter', () => { hovered = true; schedule(); });
    carousel.addEventListener('mouseleave', () => { hovered = false; schedule(); });
    carousel.addEventListener('focusin', () => { focused = true; schedule(); });
    carousel.addEventListener('focusout', (event) => { focused = carousel.contains(event.relatedTarget); schedule(); });
    document.addEventListener('visibilitychange', schedule);
    motionPreference.addEventListener('change', schedule);
    show(0);
    schedule();
});
const storeThemeToggle = document.querySelector('[data-store-theme-toggle]');
const updateStoreTheme = () => {
    const dark = document.documentElement.dataset.theme === 'dark';
    storeThemeToggle?.setAttribute('aria-pressed', String(dark));
    storeThemeToggle?.setAttribute('aria-label', dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
    const label = storeThemeToggle?.querySelector('[data-store-theme-label]');
    if (label) { label.textContent = dark ? '☀' : '☾'; }
};
updateStoreTheme();
storeThemeToggle?.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('wiselion-theme', next); } catch {}
    updateStoreTheme();
});
window.addEventListener('storage', (event) => {
    if (event.key === 'wiselion-theme') {
        document.documentElement.dataset.theme = event.newValue === 'dark' ? 'dark' : 'light';
        updateStoreTheme();
    }
});
document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
    const image = gallery.querySelector('[data-gallery-main]');
    const caption = gallery.querySelector('[data-gallery-caption]');
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
    const modal = gallery.querySelector('dialog');
    const zoom = gallery.querySelector('[data-gallery-zoom]');
    const placeholder = gallery.querySelector('[data-gallery-placeholder]');
    const placeholderLabel = gallery.querySelector('[data-gallery-placeholder-label]');
    const colors = [...gallery.querySelectorAll('[data-color-option]')];
    const selection = gallery.querySelector('[data-color-selection]');
    const reset = gallery.querySelector('[data-color-reset]');
    const initial = {src: image.getAttribute('src') || '', alt: image.alt, caption: caption.textContent};
    const showPhoto = (src, alt, text) => {
        image.hidden = !src;
        zoom.hidden = !src;
        placeholder.hidden = Boolean(src);
        if (src) { image.src = src; image.alt = alt; }
        else { image.removeAttribute('src'); placeholderLabel.textContent = text; }
        caption.textContent = text;
    };
    thumbs.forEach((button, index) => button.addEventListener('click', () => {
        showPhoto(button.dataset.src, button.dataset.alt, `Foto ${index + 1} dari ${thumbs.length} · Galeri produk`);
        thumbs.forEach((thumb) => thumb.setAttribute('aria-pressed', String(thumb === button)));
    }));
    colors.forEach((button) => button.addEventListener('click', () => {
        colors.forEach((color) => color.setAttribute('aria-pressed', String(color === button)));
        thumbs.forEach((thumb) => thumb.setAttribute('aria-pressed', 'false'));
        selection.textContent = `Warna: ${button.dataset.colorName}`;
        reset.hidden = false;
        showPhoto(button.dataset.src, button.dataset.alt, button.dataset.src ? `Foto warna ${button.dataset.colorName}` : `Foto warna ${button.dataset.colorName} belum tersedia`);
    }));
    reset?.addEventListener('click', () => {
        colors.forEach((color) => color.setAttribute('aria-pressed', 'false'));
        thumbs.forEach((thumb, index) => thumb.setAttribute('aria-pressed', String(index === 0)));
        selection.textContent = 'Pilih warna untuk melihat foto khususnya.';
        reset.hidden = true;
        showPhoto(initial.src, initial.alt, initial.caption);
    });
    zoom?.addEventListener('click', () => {
        if (image.hidden || !image.getAttribute('src')) { return; }
        modal.querySelector('img').src = image.src;
        modal.querySelector('img').alt = image.alt;
        modal.showModal();
    });
    modal?.querySelector('button')?.addEventListener('click', () => modal.close());
    modal?.addEventListener('click', (event) => { if (event.target === modal) { modal.close(); } });
});
const menuToggle = document.querySelector('[data-menu-toggle]');
const filterPanel = document.querySelector('.watch-filters details');
if (filterPanel && window.matchMedia('(max-width: 760px)').matches) { filterPanel.open = false; }
const menu = document.getElementById('store-navigation');
menuToggle?.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') !== 'true';
    menuToggle.setAttribute('aria-expanded', String(open));
    menu.classList.toggle('is-open', open);
});
const searchToggle = document.querySelector('[data-search-toggle]');
const search = document.getElementById('store-search');
searchToggle?.addEventListener('click', () => {
    search.hidden = !search.hidden;
    searchToggle.setAttribute('aria-expanded', String(!search.hidden));
    if (!search.hidden) { search.querySelector('input').focus(); }
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') { return; }
    if (search && !search.hidden) { search.hidden = true; searchToggle.setAttribute('aria-expanded', 'false'); searchToggle.focus(); }
    if (menu?.classList.contains('is-open')) { menu.classList.remove('is-open'); menuToggle.setAttribute('aria-expanded', 'false'); menuToggle.focus(); }
});
