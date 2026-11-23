import { Controller } from '@hotwired/stimulus';

// A text field with suggestions from the server: `<input data-controller="lookup" data-lookup-url-value="/path" list="id">` and `<datalist id="id">`.
// The url gets `?q=<text>` and answers `[{value, label}]`; the choices of the datalist are those (the value goes into the field, the label is shown beside it).
export default class extends Controller {
    static values = { url: String, min: { type: Number, default: 2 } };

    connect() {
        this.onInput = () => this.ask();
        this.element.addEventListener('input', this.onInput);
    }

    disconnect() {
        this.element.removeEventListener('input', this.onInput);
        clearTimeout(this.timer);
    }

    ask() {
        clearTimeout(this.timer);
        const query = this.element.value.trim();
        if (query.length < this.minValue) return;
        this.timer = setTimeout(async () => {
            const list = document.getElementById(this.element.getAttribute('list'));
            if (!list) return;
            try {
                const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) return;
                const rows = await response.json();
                list.replaceChildren(
                    ...rows.map((row) => {
                        const option = document.createElement('option');
                        option.value = row.value;
                        option.label = row.label;
                        return option;
                    }),
                );
            } catch {
                // no suggestions: the field still works as a plain one
            }
        }, 200);
    }
}
