import { Controller } from '@hotwired/stimulus';
import Collapse from 'bootstrap/js/dist/collapse';

// The menu. On a wide screen it is docked and open, or closed to a rail of icons (the choice is kept in the cookie `aside-closed`, which the server reads to render it,
// so there is no flash); in the rail the label of an item and the sub-items of a group show beside it while the pointer is on it (CSS, fixed-position: this controller
// only tells where the item is). On a phone it is an overlay, closed until the burger is pressed. A link of a group opens its sub menu.
const WIDE = '(min-width: 1024px)';

export default class extends Controller {
    connect() {
        this.onClick = (event) => this.toggleItem(event);
        this.onKey = (event) => event.key === 'Escape' && this.close();
        this.onPoint = (event) => this.place(event);
        this.element.addEventListener('click', this.onClick);
        this.element.addEventListener('mouseover', this.onPoint);
        this.element.addEventListener('focusin', this.onPoint);
        document.addEventListener('keydown', this.onKey);
    }

    disconnect() {
        this.element.removeEventListener('click', this.onClick);
        this.element.removeEventListener('mouseover', this.onPoint);
        this.element.removeEventListener('focusin', this.onPoint);
        document.removeEventListener('keydown', this.onKey);
    }

    toggle() {
        if (window.matchMedia(WIDE).matches) {
            const closed = !this.element.classList.contains('aside-closed');
            this.element.classList.toggle('aside-closed', closed);
            document.cookie = `aside-closed=${closed ? 1 : 0};path=/;max-age=31536000;samesite=lax`;
        } else {
            this.element.classList.toggle('open');
        }
    }

    close() {
        if (window.matchMedia(WIDE).matches) return;
        this.element.classList.remove('open');
    }

    rail() {
        return window.matchMedia(WIDE).matches && this.element.classList.contains('aside-closed');
    }

    // In the rail: where the pointed item is, for its fly-out.
    place(event) {
        if (!this.rail()) return;
        const item = event.target.closest('#nav-aside > .nav-item, #nav-aside .nav-section > .nav > .nav-item');
        if (!item) return;
        const box = item.querySelector(':scope > .nav-link').getBoundingClientRect();
        const room = window.innerHeight - 8 * 16; // a group's list needs some room below
        item.style.setProperty('--fly-top', `${Math.min(box.top, Math.max(room, 0))}px`);
        item.style.setProperty('--fly-h', `${box.height}px`);
    }

    toggleItem(event) {
        if (this.rail()) return; // in the rail a link just goes where it points
        const link = event.target.closest('[data-bs-toggle=collapse]');
        const item = link?.closest('.nav-item');
        if (!link || !item) return;

        if (item.classList.contains('open') && link.getAttribute('href')) {
            window.location.href = link.getAttribute('href');
            return;
        }
        event.preventDefault();
        item.classList.add('open');
        const sub = item.querySelector(':scope > .nav');
        if (sub) Collapse.getOrCreateInstance(sub).toggle();
    }
}
