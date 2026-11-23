import { Controller } from '@hotwired/stimulus';

// "Which columns to show" of a list: the choice is kept in the browser (localStorage) and in a cookie the server can read.
export default class extends Controller {
    connect() {
        this.storage = this.element.dataset.storage ?? 'list';
        this.element.querySelector('.dropdown-menu')?.addEventListener('click', (event) => event.stopPropagation());
        this.element.querySelectorAll('input[id^="ff"]').forEach((input) => {
            const stored = localStorage.getItem(`${this.storage}_${input.value}`);
            input.checked = stored === null ? true : parseInt(stored, 10) === 1;
            this.apply(input);
            input.addEventListener('input', () => {
                this.apply(input);
                localStorage.setItem(`${this.storage}_${input.value}`, input.checked ? '1' : '0');
                document.cookie = `${this.storage}_${input.value}=${input.checked ? 1 : 0};path=/;max-age=31536000;samesite=lax`;
            });
        });
    }

    apply(input) {
        const cells = [document.querySelector(`[data-heading="${input.value}"]`), ...document.querySelectorAll(`[data-input="${input.value}"]`)];
        cells.forEach((cell) => cell?.classList.toggle('d-none', !input.checked));
    }
}
