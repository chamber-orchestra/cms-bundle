import { Controller } from '@hotwired/stimulus';

// A select you can type into. The native <select> stays (hidden: it is what the form sends and what other code listens to); in front of it an input shows the chosen
// option, and a list under it narrows to the options that contain what is typed (any case). Arrows, Enter and Esc work; every change fires `change` on the select.
export default class extends Controller {
    connect() {
        const select = this.element;
        if (this.wrap || select.multiple) return;

        this.wrap = document.createElement('div');
        this.wrap.className = 'cb';
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = `${select.className} cb-input`.replace('cb-native', '').trim();
        this.input.autocomplete = 'off';
        this.input.spellcheck = false;
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.disabled = select.disabled;
        if (select.id) {
            this.input.id = select.id; // the <label for> now leads to the input
            select.id = `${select.id}-native`;
        }
        this.list = document.createElement('ul');
        this.list.className = 'cb-list';
        this.list.setAttribute('role', 'listbox');
        this.list.hidden = true;

        // The select itself is not moved (Stimulus would see it leave and come back and reconnect forever): the wrapper goes in front of it.
        this.wrap.append(this.input, this.list);
        select.before(this.wrap);
        select.classList.add('cb-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        this.onFocus = () => this.open('');
        this.onInput = () => this.open(this.input.value);
        this.onKey = (event) => this.key(event);
        this.onBlur = () => this.close();
        this.onChange = () => this.show();
        this.onPick = (event) => {
            const item = event.target.closest('li[data-index]');
            if (!item) return;
            event.preventDefault(); // mousedown: before the input loses focus
            this.pick(Number(item.dataset.index));
        };
        this.input.addEventListener('focus', this.onFocus);
        this.input.addEventListener('click', this.onFocus);
        this.input.addEventListener('input', this.onInput);
        this.input.addEventListener('keydown', this.onKey);
        this.input.addEventListener('blur', this.onBlur);
        this.list.addEventListener('mousedown', this.onPick);
        select.addEventListener('change', this.onChange);
        if (select.dataset.quickCreateUrl) this.addQuickCreate();
        this.show();
    }

    disconnect() {
        if (!this.wrap) return;
        const select = this.element;
        select.removeEventListener('change', this.onChange);
        select.classList.remove('cb-native');
        select.removeAttribute('aria-hidden');
        select.removeAttribute('tabindex');
        if (select.id.endsWith('-native')) select.id = select.id.slice(0, -7);
        this.wrap.remove();
        this.wrap = null;
    }

    // `quick_create` of the form field: a "+" beside the input opens the create form of the listed entity in a dialog; what is saved is added to the select and chosen.
    addQuickCreate() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline cb-add';
        button.innerHTML = '<i class="fas fa-fw fa-plus" aria-hidden="true"></i>';
        button.addEventListener('click', () => this.openQuickCreate());
        this.wrap.classList.add('cb-has-add');
        this.wrap.append(button);
    }

    async openQuickCreate() {
        const select = this.element;
        const url = select.dataset.quickCreateUrl;
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        const dialog = document.createElement('dialog');
        dialog.className = 'dialog dialog-lg cms-quick';
        dialog.setAttribute('data-turbo-temporary', ''); // not kept in a snapshot of a page
        const title = (select.id && document.querySelector(`label[for="${this.input.id}"]`)?.textContent.trim()) || '';
        dialog.innerHTML = '<div class="dialog-header"><h5 class="dialog-title"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div><div class="dialog-body"></div>';
        dialog.querySelector('.dialog-title').textContent = title;
        const body = dialog.querySelector('.dialog-body');
        document.body.append(dialog);
        const close = () => {
            dialog.close();
            dialog.remove();
        };
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            close();
        });
        dialog.addEventListener('click', (event) => event.target.closest('[data-bs-dismiss="dialog"]') && close());
        body.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-quick-create-form]');
            if (!form) return;
            event.preventDefault();
            const response = await fetch(url, { method: 'POST', body: new FormData(form), headers });
            if (response.headers.get('Content-Type')?.includes('json')) {
                const saved = await response.json();
                select.append(new Option(saved.label, saved.id, true, true));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                close();
            } else {
                body.innerHTML = await response.text(); // the form again, with its errors
            }
        });
        body.innerHTML = await (await fetch(url, { headers })).text();
        dialog.showModal();
    }

    // The input shows the label of the chosen option.
    show() {
        const chosen = this.element.selectedOptions[0];
        this.input.value = chosen ? chosen.textContent.trim() : '';
    }

    open(query) {
        const needle = query.trim().toLowerCase();
        this.items = [...this.element.options].filter((option) => !option.disabled && option.textContent.toLowerCase().includes(needle));
        this.list.replaceChildren(
            ...this.items.map((option, index) => {
                const item = document.createElement('li');
                item.dataset.index = index;
                item.setAttribute('role', 'option');
                item.textContent = option.textContent.trim() || '—';
                if (option.selected) item.classList.add('selected');

                return item;
            }),
        );
        if (this.items.length === 0) {
            const none = document.createElement('li');
            none.className = 'cb-none';
            none.textContent = '—';
            this.list.append(none);
        }
        this.active = Math.max(0, this.items.findIndex((option) => option.selected));
        if (needle !== '') this.active = 0;
        this.mark();
        const box = this.input.getBoundingClientRect();
        Object.assign(this.list.style, { top: `${box.bottom + 2}px`, left: `${box.left}px`, minWidth: `${box.width}px` });
        this.list.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
        if (query === '') this.input.select();
    }

    close() {
        this.list.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.show();
    }

    mark() {
        [...this.list.querySelectorAll('li[data-index]')].forEach((item, index) => {
            item.classList.toggle('active', index === this.active);
            if (index === this.active) item.scrollIntoView({ block: 'nearest' });
        });
    }

    key(event) {
        if (event.key === 'Escape') {
            this.close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (this.list.hidden) return this.open('');
            const last = this.items.length - 1;
            this.active = event.key === 'ArrowDown' ? Math.min(last, this.active + 1) : Math.max(0, this.active - 1);
            this.mark();
        } else if (event.key === 'Enter' && !this.list.hidden && this.items.length > 0) {
            event.preventDefault(); // choosing is not sending the form
            this.pick(this.active);
        }
    }

    pick(index) {
        const option = this.items[index];
        if (!option) return;
        const changed = this.element.value !== option.value;
        this.element.value = option.value;
        this.close();
        if (changed) this.element.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
