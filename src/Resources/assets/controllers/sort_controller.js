import { Controller } from '@hotwired/stimulus';

// "Move up / down" links of a sortable list: the server is asked, then the row changes its place.
export default class extends Controller {
    connect() {
        this.onClick = (event) => this.move(event);
        this.element.addEventListener('click', this.onClick);
    }

    disconnect() {
        this.element.removeEventListener('click', this.onClick);
    }

    async move(event) {
        const link = event.target.closest('[data-toggle="move"][data-direction][href]');
        if (!link) return;
        event.preventDefault();

        const row = link.closest('[data-sort-order]');
        if (!row) return;
        link.classList.add('disabled');
        const up = link.dataset.direction === 'up';
        const neighbour = up ? row.previousElementSibling : row.nextElementSibling;
        try {
            const response = await fetch(link.getAttribute('href'), { method: 'POST', credentials: 'same-origin' });
            if (response.ok && neighbour?.matches('[data-sort-order]')) {
                up ? neighbour.before(row) : neighbour.after(row);
            }
        } finally {
            link.classList.remove('disabled');
        }
    }
}
