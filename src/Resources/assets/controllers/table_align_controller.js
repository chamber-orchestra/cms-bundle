import { Controller } from '@hotwired/stimulus';

// A column heading is aligned as the cells under it: the first row of the table says how (`text-center`, `text-end`, else the start), and the heading takes it.
export default class extends Controller {
    connect() {
        const row = this.element.querySelector('tbody tr');
        const heads = this.element.querySelectorAll('thead th');
        if (!row || row.cells.length !== heads.length || row.querySelector('td[colspan]')) return;
        heads.forEach((head, index) => {
            const cell = row.cells[index];
            const align = cell.classList.contains('text-center') ? 'text-center' : cell.classList.contains('text-end') ? 'text-end' : null;
            if (align) head.classList.replace('text-start', align) || head.classList.add(align);
        });
    }
}
