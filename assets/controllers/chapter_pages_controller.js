import { Controller } from '@hotwired/stimulus';
import { drawTiles, tileLayout } from '../tiles.js';

// Canvas width of a thumbnail: twice the card width, sharp on HiDPI screens.
const THUMB_WIDTH = 240;

// Admin page grid: unscrambled thumbnails drawn as cards scroll into view, and drag-and-drop
// reordering. Cards move live while dragging; each drop sends one move, in order, and a refused
// move reloads the page to show the server's order and its message.
export default class extends Controller {
    static targets = ['page', 'number'];
    static values = { tile: Number, gutter: Number, moveUrl: String, token: String };

    connect() {
        this.saving = Promise.resolve();
        this.observer = new IntersectionObserver((entries) => {
            for (const entry of entries.filter((entry) => entry.isIntersecting)) {
                this.observer.unobserve(entry.target);
                this.thumbnail(entry.target);
            }
        }, { rootMargin: '600px 0px' });
        this.pageTargets.filter((page) => page.dataset.src).forEach((page) => this.observer.observe(page));
    }

    disconnect() {
        this.observer.disconnect();
    }

    async thumbnail(page) {
        let bitmap;
        try {
            const response = await fetch(page.dataset.src);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            bitmap = await createImageBitmap(await response.blob());
            const layout = tileLayout(bitmap, page.dataset, this.tileValue, this.gutterValue);
            const scale = THUMB_WIDTH / layout.w;
            const canvas = document.createElement('canvas');
            canvas.className = 'w-100';
            canvas.width = THUMB_WIDTH;
            canvas.height = Math.round(layout.h * scale);
            drawTiles(canvas.getContext('2d'), bitmap, layout, 0, layout.rows, 0, scale);
            page.querySelector('.page-thumb').replaceChildren(canvas);
        } catch (error) {
            page.querySelector('.page-thumb').textContent = 'Not loaded';
        } finally {
            bitmap?.close();
        }
    }

    start(event) {
        this.dragged = event.currentTarget;
        this.origin = this.dragged.nextElementSibling;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', this.dragged.dataset.id);
        this.dragged.classList.add('opacity-50');
    }

    // The card goes before or after the one under the pointer, by which half the pointer is on.
    over(event) {
        if (!this.dragged) {
            return;
        }
        event.preventDefault();
        const target = event.target.closest('[data-chapter-pages-target="page"]');
        if (target && target !== this.dragged) {
            const box = target.getBoundingClientRect();
            target[event.clientX > box.left + box.width / 2 ? 'after' : 'before'](this.dragged);
        }
    }

    drop(event) {
        event.preventDefault();
    }

    // Dropped outside the grid or cancelled with Escape: the card returns to its place.
    end(event) {
        const page = this.dragged;
        this.dragged = null;
        page.classList.remove('opacity-50');
        if ('none' === event.dataTransfer.dropEffect) {
            page.parentElement.insertBefore(page, this.origin);
            return;
        }
        if (page.nextElementSibling === this.origin) {
            return;
        }

        this.numberTargets.forEach((number, index) => { number.textContent = index + 1; });
        const body = new FormData();
        body.set('_token', this.tokenValue);
        body.set('page', page.dataset.id);
        const previous = page.previousElementSibling;
        if (previous) {
            body.set('after', previous.dataset.id);
        }
        this.saving = this.saving.then(async () => {
            const response = await fetch(this.moveUrlValue, { method: 'POST', body }).catch(() => null);
            if (!response?.ok) {
                window.location.reload();
            }
        });
    }
}
