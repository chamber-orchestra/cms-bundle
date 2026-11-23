import { Controller } from '@hotwired/stimulus';

// A button that copies the value of a field: `data-controller="copy" data-copy-field-value="#id" data-action="click->copy#copy"` (without the value: the field in the same `.copyable`). The icon turns into a tick for a moment.
// A disabled field cannot be selected, so the value is read from the element and written with the Clipboard API.
export default class extends Controller {
    static values = { field: String };

    async copy(event) {
        event.preventDefault();
        const field = this.hasFieldValue && this.fieldValue ? document.querySelector(this.fieldValue) : this.element.closest('.copyable')?.querySelector('input, textarea');
        if (!field) return;
        try {
            await navigator.clipboard.writeText(field.value);
        } catch {
            return; // no clipboard (an insecure page): nothing to show
        }
        const icon = this.element.querySelector('i');
        if (!icon) return;
        const before = icon.className;
        icon.className = 'fas fa-fw fa-check';
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            icon.className = before;
        }, 1200);
    }

    disconnect() {
        clearTimeout(this.timer);
    }
}
