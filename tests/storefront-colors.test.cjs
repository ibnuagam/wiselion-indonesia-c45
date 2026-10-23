const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

function element(properties = {}) {
    return Object.assign({
        hidden: false, dataset: {}, attributes: {}, listeners: {}, textContent: '',
        addEventListener(name, action) { this.listeners[name] = action; },
        setAttribute(name, value) { this.attributes[name] = value; },
        getAttribute(name) { return name === 'src' ? this.src || null : this.attributes[name] || null; },
        removeAttribute(name) { if (name === 'src') { this.src = ''; } else { delete this.attributes[name]; } },
        click() { this.listeners.click?.({target: this}); },
    }, properties);
}

function fixture(hasGallery = true) {
    const image = element({src: hasGallery ? '/base.png' : '', alt: 'Wiselion', hidden: !hasGallery});
    const caption = element({textContent: hasGallery ? 'Foto 1 dari 1' : 'Foto produk belum tersedia'});
    const placeholder = element({hidden: hasGallery});
    const placeholderLabel = element();
    const zoom = element({hidden: !hasGallery});
    const selection = element();
    const reset = element({hidden: true});
    const thumbs = hasGallery ? [element({dataset: {src: '/base.png', alt: 'Wiselion'}})] : [];
    const colors = [element({dataset: {colorName: 'Blue', src: '/blue.png', alt: 'Wiselion — Blue'}}), element({dataset: {colorName: 'Pink', src: '', alt: 'Wiselion — Pink'}})];
    const modalImage = element();
    const close = element();
    const modal = element({querySelector(selector) { return selector === 'img' ? modalImage : close; }, showModal() { this.open = true; }, close() { this.open = false; }});
    const controls = {'[data-gallery-main]': image, '[data-gallery-caption]': caption, '[data-gallery-zoom]': zoom,
        '[data-gallery-placeholder]': placeholder, '[data-gallery-placeholder-label]': placeholderLabel,
        '[data-color-selection]': selection, '[data-color-reset]': reset, dialog: modal};
    const gallery = {querySelector: (selector) => controls[selector], querySelectorAll: (selector) => selector === '[data-gallery-thumb]' ? thumbs : colors};
    const document = {documentElement: {dataset: {theme: 'light'}}, querySelectorAll: (selector) => selector === '[data-product-gallery]' ? [gallery] : [], querySelector: () => null, getElementById: () => null, addEventListener() {}};
    runInNewContext(readFileSync(join(__dirname, '../public/js/storefront.js'), 'utf8'), {document, window: {addEventListener() {}}});
    return {image, caption, placeholder, placeholderLabel, zoom, selection, reset, thumbs, colors, modal, modalImage, close};
}

test('uploaded color changes the main photo and the enlarged photo', () => {
    const ui = fixture();
    ui.colors[0].click();
    assert.equal(ui.image.src, '/blue.png');
    assert.equal(ui.image.hidden, false);
    assert.equal(ui.placeholder.hidden, true);
    assert.equal(ui.caption.textContent, 'Foto warna Blue');
    assert.equal(ui.selection.textContent, 'Warna: Blue');
    assert.equal(ui.colors[0].attributes['aria-pressed'], 'true');
    assert.equal(ui.colors[1].attributes['aria-pressed'], 'false');
    ui.zoom.click();
    assert.equal(ui.modal.open, true);
    assert.equal(ui.modalImage.src, '/blue.png');
    ui.close.click();
    assert.equal(ui.modal.open, false);
});

test('empty color slot never displays an unrelated photo and reset recovers the gallery', () => {
    const ui = fixture();
    ui.colors[1].click();
    assert.equal(ui.image.hidden, true);
    assert.equal(ui.image.src, '');
    assert.equal(ui.placeholderLabel.textContent, 'Foto warna Pink belum tersedia');
    assert.equal(ui.zoom.hidden, true);
    ui.zoom.click();
    assert.notEqual(ui.modal.open, true);
    ui.reset.click();
    assert.equal(ui.image.src, '/base.png');
    assert.equal(ui.image.hidden, false);
    assert.equal(ui.reset.hidden, true);
    assert.equal(ui.colors[1].attributes['aria-pressed'], 'false');
    ui.colors[0].click();
    ui.thumbs[0].click();
    assert.equal(ui.image.src, '/base.png');
    assert.equal(ui.caption.textContent, 'Foto 1 dari 1 · Galeri produk');
});

test('color photos work without a base product photo or gallery', () => {
    const ui = fixture(false);
    ui.colors[0].click();
    assert.equal(ui.image.src, '/blue.png');
    assert.equal(ui.zoom.hidden, false);
    ui.reset.click();
    assert.equal(ui.image.hidden, true);
    assert.equal(ui.placeholder.hidden, false);
    assert.equal(ui.caption.textContent, 'Foto produk belum tersedia');
});
