import { Controller } from '@hotwired/stimulus';
import { drawTiles, tileLayout } from '../tiles.js';

// iOS Safari refuses canvases over ~16.7 MP; 4096 rows keeps a 1200 px wide segment far below.
// A multiple of the tile size, so no tile row straddles two segments.
const SEGMENT_HEIGHT = 4096;

// Rebuilds scrambled pages on canvas (tiles.js). Pages near the viewport are drawn, distant ones release their canvases. Saving is made
// inconvenient, not impossible: the tile order is public.
export default class extends Controller {
    static targets = ['page'];
    static values = { tile: Number, gutter: Number, previous: String, next: String };

    connect() {
        this.pending = new Map();
        this.observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                entry.isIntersecting ? this.load(entry.target) : this.unload(entry.target);
            }
        }, { rootMargin: '1500px 0px' });
        this.pageTargets.forEach((page) => this.observer.observe(page));
        for (const type of ['contextmenu', 'dragstart']) {
            this.element.addEventListener(type, this.block);
        }
        window.addEventListener('keydown', this.navigate);
    }

    disconnect() {
        this.observer.disconnect();
        this.pageTargets.forEach((page) => this.unload(page));
        for (const type of ['contextmenu', 'dragstart']) {
            this.element.removeEventListener(type, this.block);
        }
        window.removeEventListener('keydown', this.navigate);
    }

    block = (event) => event.preventDefault();

    // Left/right arrows go to the previous/next chapter; up/down and space keep scrolling the pages.
    navigate = (event) => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey
            || event.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }
        const url = { ArrowLeft: this.previousValue, ArrowRight: this.nextValue }[event.key];
        if (url) {
            window.location.assign(url);
        }
    };

    async load(page) {
        if (page.querySelector('canvas') || this.pending.has(page)) {
            return;
        }
        const controller = new AbortController();
        this.pending.set(page, controller);
        let bitmap;
        try {
            const response = await fetch(page.dataset.src, { signal: controller.signal });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            bitmap = await createImageBitmap(await response.blob());
            if (!controller.signal.aborted) {
                page.replaceChildren(...this.draw(bitmap, page.dataset));
            }
        } catch (error) {
            if (!controller.signal.aborted) {
                this.fail(page);
            }
        } finally {
            bitmap?.close();
            if (this.pending.get(page) === controller) {
                this.pending.delete(page);
            }
        }
    }

    draw(bitmap, data) {
        const layout = tileLayout(bitmap, data, this.tileValue, this.gutterValue);
        const canvases = [];
        for (let top = 0; top < layout.h; top += SEGMENT_HEIGHT) {
            const canvas = document.createElement('canvas');
            canvas.width = layout.w;
            canvas.height = Math.min(SEGMENT_HEIGHT, layout.h - top);
            drawTiles(canvas.getContext('2d'), bitmap, layout, top / layout.tile, Math.ceil((top + canvas.height) / layout.tile), top);
            canvases.push(canvas);
        }

        return canvases;
    }

    unload(page) {
        this.pending.get(page)?.abort();
        this.pending.delete(page);
        for (const canvas of page.querySelectorAll('canvas')) {
            // Zero size frees the backing store immediately; removal alone waits for GC.
            canvas.width = canvas.height = 0;
        }
        page.replaceChildren();
    }

    fail(page) {
        const message = document.createElement('p');
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.textContent = 'Retry';
        retry.addEventListener('click', () => {
            page.replaceChildren();
            this.load(page);
        });
        message.append('This page could not load. ', retry);
        page.replaceChildren(message);
    }
}
