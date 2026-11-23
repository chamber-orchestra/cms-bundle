import { Controller } from '@hotwired/stimulus';

// A button with data-form="#delete" and data-values='{"entity": "<id>"}' puts the values into the fields of that form (the dialog around it is opened by Bootstrap).
export default class extends Controller {
    connect() {
        this.onClick = (event) => this.fill(event);
        this.element.addEventListener('click', this.onClick);
    }

    disconnect() {
        this.element.removeEventListener('click', this.onClick);
    }

    fill(event) {
        const button = event.target.closest('[data-form][data-values]');
        if (!button) return;
        const form = document.querySelector(button.dataset.form);
        if (!form || form.tagName !== 'FORM') return;

        let values = {};
        try {
            values = JSON.parse(button.dataset.values);
        } catch {
            return;
        }
        const name = form.getAttribute('name');
        Object.entries(values).forEach(([key, value]) => {
            const field = form.querySelector(`#${name}_${key}`);
            if (field) field.value = typeof value === 'object' ? JSON.stringify(value) : value;
        });
    }
}
