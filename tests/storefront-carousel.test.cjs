const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

function element(properties = {}) {
    return Object.assign({
        attributes: {}, listeners: {}, hidden: false,
        addEventListener(name, handler) { this.listeners[name] = handler; },
        setAttribute(name, value) { this.attributes[name] = value; },
        fire(name, event = {}) { this.listeners[name]?.(event); },
        click() { this.fire('click'); },
    }, properties);
}

function fixture({ count = 3, reducedMotion = false } = {}) {
    let now = 0;
    let nextId = 0;
    const timers = new Map();
    const slides = Array.from({ length: count }, () => element());
    const dots = Array.from({ length: count }, () => element());
    const prev = element();
    const next = element();
    const controls = { '[data-carousel-prev]': prev, '[data-carousel-next]': next };
    const carousel = element({
        querySelector: (selector) => controls[selector],
        querySelectorAll: (selector) => selector === '[data-slide]' ? slides : dots,
        contains: (target) => Boolean(target && Object.values(controls).includes(target)),
    });
    const document = element({
        hidden: false, activeElement: null, documentElement: { dataset: { theme: 'light' } },
        querySelectorAll: (selector) => selector === '[data-carousel]' ? [carousel] : [],
        querySelector: () => null, getElementById: () => null,
    });
    const preference = element({ matches: reducedMotion });
    const window = {
        addEventListener() {}, matchMedia: () => preference,
        clearTimeout: (id) => timers.delete(id),
        setTimeout: (action, delay) => { const id = ++nextId; timers.set(id, { at: now + delay, action }); return id; },
    };
    runInNewContext(readFileSync(join(__dirname, '../public/js/storefront.js'), 'utf8'), { document, window });
    const tick = (duration) => {
        const end = now + duration;
        while (true) {
            const pending = [...timers].sort((a, b) => a[1].at - b[1].at)[0];
            if (!pending || pending[1].at > end) { break; }
            now = pending[1].at;
            timers.delete(pending[0]);
            pending[1].action();
        }
        now = end;
    };
    return { slides, dots, prev, next, carousel, document, preference, timers, tick,
        visible: () => slides.findIndex((slide) => !slide.hidden) };
}

test('headlines change at exactly six seconds and loop to the first slide', () => {
    const ui = fixture();
    assert.equal(ui.visible(), 0);
    ui.tick(5999);
    assert.equal(ui.visible(), 0);
    ui.tick(1);
    assert.equal(ui.visible(), 1);
    assert.equal(ui.dots[1].attributes['aria-pressed'], 'true');
    assert.equal(ui.dots[0].attributes['aria-pressed'], 'false');
    ui.tick(12000);
    assert.equal(ui.visible(), 0);
    assert.equal(ui.timers.size, 1);
});

test('manual next, previous and dots work and restart a full six-second interval', () => {
    const ui = fixture();
    ui.tick(5000);
    ui.next.click();
    assert.equal(ui.visible(), 1);
    ui.tick(5999);
    assert.equal(ui.visible(), 1);
    ui.tick(1);
    assert.equal(ui.visible(), 2);
    ui.prev.click();
    assert.equal(ui.visible(), 1);
    ui.dots[0].click();
    ui.prev.click();
    assert.equal(ui.visible(), 2);
    assert.equal(ui.timers.size, 1);
});

test('autoplay continues through multiple loops without a play or pause button', () => {
    const ui = fixture();
    ui.tick(60000);
    assert.equal(ui.visible(), 1);
    ui.tick(6000);
    assert.equal(ui.visible(), 2);
    assert.equal(ui.timers.size, 1);
});

test('hover and keyboard focus pause independently and resume after both leave', () => {
    const ui = fixture();
    ui.carousel.fire('mouseenter');
    ui.tick(12000);
    assert.equal(ui.visible(), 0);
    ui.carousel.fire('focusin');
    ui.carousel.fire('mouseleave');
    ui.carousel.fire('focusout', { relatedTarget: ui.next });
    ui.tick(12000);
    assert.equal(ui.visible(), 0);
    ui.carousel.fire('focusout', { relatedTarget: null });
    ui.tick(5999);
    assert.equal(ui.visible(), 0);
    ui.tick(1);
    assert.equal(ui.visible(), 1);
});

test('hidden browser tabs stop autoplay and returning starts a fresh interval', () => {
    const ui = fixture();
    ui.tick(5000);
    ui.document.hidden = true;
    ui.document.fire('visibilitychange');
    ui.tick(60000);
    assert.equal(ui.visible(), 0);
    ui.document.hidden = false;
    ui.document.fire('visibilitychange');
    ui.tick(5999);
    assert.equal(ui.visible(), 0);
    ui.tick(1);
    assert.equal(ui.visible(), 1);
});

test('reduced-motion preference disables autoplay but preserves manual navigation', () => {
    const ui = fixture({ reducedMotion: true });
    ui.tick(60000);
    assert.equal(ui.visible(), 0);
    ui.next.click();
    ui.tick(12000);
    assert.equal(ui.visible(), 1);
    ui.preference.matches = false;
    ui.preference.fire('change');
    ui.tick(6000);
    assert.equal(ui.visible(), 2);
    ui.preference.matches = true;
    ui.preference.fire('change');
    ui.tick(12000);
    assert.equal(ui.visible(), 2);
});

test('zero slides and a single fallback never create autoplay timers', () => {
    assert.equal(fixture({ count: 0 }).timers.size, 0);
    assert.equal(fixture({ count: 1 }).timers.size, 0);
});
