import { Controller } from '@hotwired/stimulus';

// Quick search of a column: the magnifier in a heading opens a dropdown under it with a field; Enter puts `col[field]=text` into the address (and drops the page number),
// Esc or a click outside closes it. The panel is fixed under the heading (the table scrolls inside its own box and would clip it).
export default class extends Controller {
    connect() {
        this.onOutside = (event) => {
            if (!event.target.closest('.th-search, .btn-colsearch')) this.closeAll();
        };
        document.addEventListener('mousedown', this.onOutside);
    }

    disconnect() {
        document.removeEventListener('mousedown', this.onOutside);
    }

    toggle(event) {
        const button = event.currentTarget;
        const box = button.parentElement.querySelector('.th-search');
        const wasHidden = box.hidden;
        this.closeAll();
        if (!wasHidden) return;
        const rect = button.closest('th').getBoundingClientRect();
        box.hidden = false;
        Object.assign(box.style, { top: `${rect.bottom + 2}px`, left: `${Math.max(8, Math.min(rect.left + rect.width / 2 - box.offsetWidth / 2, window.innerWidth - box.offsetWidth - 8))}px` });
        box.querySelector('input').focus();
        box.querySelector('input').select();
    }

    closeAll() {
        this.element.querySelectorAll('.th-search').forEach((box) => {
            box.hidden = true;
        });
    }

    key(event) {
        const input = event.currentTarget;
        if (event.key === 'Escape') {
            input.closest('.th-search').hidden = true;
            return;
        }
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const url = new URL(window.location.href);
        const name = `col[${input.dataset.field}]`;
        input.value.trim() === '' ? url.searchParams.delete(name) : url.searchParams.set(name, input.value.trim());
        url.searchParams.delete('page');
        window.location.assign(url);
    }
}
