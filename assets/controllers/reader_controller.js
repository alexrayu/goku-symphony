import { Controller } from '@hotwired/stimulus';

// iOS Safari refuses canvases over ~16.7 MP; 4096 rows keeps a 1200 px wide segment far below.
// A multiple of the tile size, so no tile row straddles two segments.
const SEGMENT_HEIGHT = 4096;

// Rebuilds scrambled pages on canvas. Format: order[sourceTile] = slot, row-major; each slot is a
// cell of tile + 2 * gutter pixels, the tile sits at the gutter offset inside it.
// Pages near the viewport are drawn, distant ones release their canvases. Saving is made
// inconvenient, not impossible: the tile order is public.
export default class extends Controller {
    static targets = ['page'];
    static values = { tile: Number, gutter: Number };

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
    }

    disconnect() {
        this.observer.disconnect();
        this.pageTargets.forEach((page) => this.unload(page));
        for (const type of ['contextmenu', 'dragstart']) {
            this.element.removeEventListener(type, this.block);
        }
    }

    block = (event) => event.preventDefault();

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

    draw(bitmap, { width, height, order }) {
        const tile = this.tileValue;
        const gutter = this.gutterValue;
        const cell = tile + 2 * gutter;
        const [w, h] = [Number(width), Number(height)];
        const columns = Math.ceil(w / tile);
        const rows = Math.ceil(h / tile);
        const slots = order.split(',').map(Number);
        if (slots.length !== columns * rows || bitmap.width !== columns * cell || bitmap.height !== rows * cell) {
            throw new Error('Image does not match its tile order.');
        }

        const canvases = [];
        for (let top = 0; top < h; top += SEGMENT_HEIGHT) {
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = Math.min(SEGMENT_HEIGHT, h - top);
            const context = canvas.getContext('2d');
            for (let row = top / tile; row < Math.ceil((top + canvas.height) / tile); row++) {
                for (let column = 0; column < columns; column++) {
                    const slot = slots[row * columns + column];
                    const x = column * tile;
                    const y = row * tile;
                    const tw = Math.min(tile, w - x);
                    const th = Math.min(tile, h - y);
                    context.drawImage(bitmap, (slot % columns) * cell + gutter, Math.floor(slot / columns) * cell + gutter, tw, th, x, y - top, tw, th);
                }
            }
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
