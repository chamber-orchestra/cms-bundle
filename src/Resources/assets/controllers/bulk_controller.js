import { Controller } from '@hotwired/stimulus';
import Tooltip from 'bootstrap/js/dist/tooltip';

// Bulk operations of a list. The choice is the set of the ids of the rows, kept in the browser (sessionStorage, per list), so it lives through a search, a filter,
// a sort and the pages; the checkbox of the heading has three states: nothing, the rows of this page (indeterminate), every row of the filter over all its pages
// (checked: the server gives the ids, `?format=ids`). The plate at the bottom says how many are chosen, can show only the chosen rows (POSTed, `?selected=1`) and sends the ids (or, for every row of the filter, "all" and the query of the list, always by POST)
// of the links of #bulk-nav through #bulk-operation-form.
export default class extends Controller {
    connect() {
        this.values = [];
        this.full = null; // the filter (the address without the page) whose every row is chosen
        this.toggler = document.querySelector('#bulk-operation-toggler-input');
        this.nav = document.querySelector('#bulk-nav');
        this.form = document.querySelector('#bulk-operation-form');
        if (!this.toggler || !this.nav || !this.form) return;

        this.key = `cms-bulk:${location.pathname}`;
        this.total = parseInt(this.toggler.dataset.total ?? '0', 10);
        this.rows = [...document.querySelectorAll('[name=bulk_operation_input]')];
        this.load();
        this.onToggler = (event) => {
            event.preventDefault();
            this.cycle();
        };
        this.toggler.addEventListener('click', this.onToggler);
        this.rows.forEach((row) => row.addEventListener('input', () => this.rowChanged(row)));
        this.nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', (event) => this.submit(event, link)));
        this.onPageShow = () => {
            this.load();
            this.paint();
        };
        window.addEventListener('pageshow', this.onPageShow);
        this.paint();
    }

    disconnect() {
        window.removeEventListener('pageshow', this.onPageShow);
    }

    load() {
        try {
            const stored = JSON.parse(sessionStorage.getItem(this.key) ?? 'null');
            this.values = Array.isArray(stored?.ids) ? stored.ids.map(String) : [];
            this.full = typeof stored?.full === 'string' ? stored.full : null;
        } catch {
            this.values = [];
            this.full = null;
        }
    }

    save() {
        try {
            this.values.length > 0 ? sessionStorage.setItem(this.key, JSON.stringify({ ids: this.values, full: this.full })) : sessionStorage.removeItem(this.key);
        } catch {
            // no storage (a private window): the choice lives on this page only
        }
    }

    // The address of this list without the page: the "filter" a full choice belongs to.
    signature() {
        const params = new URLSearchParams(location.search);
        params.delete('page');
        params.delete('format');

        return params.toString();
    }

    get everything() {
        return this.values.length > 0 && this.full === this.signature();
    }

    // nothing -> the rows of this page -> every row of the filter -> nothing
    async cycle() {
        this.hideTooltip();
        if (this.everything) {
            this.values = [];
            this.full = null;
        } else if (this.values.length === 0 && this.rows.length < this.total) {
            this.rows.forEach((row) => this.add(row.value));
        } else {
            await this.selectFiltered();
        }
        this.save();
        this.paint();
    }

    async selectFiltered() {
        this.toggler.disabled = true;
        try {
            const params = new URLSearchParams(location.search);
            params.delete('page');
            params.set('format', 'ids');
            const response = await fetch(`${location.pathname}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await response.json();
            (data.ids ?? []).forEach((id) => this.add(String(id)));
            this.full = this.signature();
        } catch {
            this.rows.forEach((row) => this.add(row.value)); // the server did not answer: at least this page
        } finally {
            this.toggler.disabled = false;
        }
    }

    add(id) {
        if (!this.values.includes(id)) this.values.push(id);
    }

    rowChanged(row) {
        const index = this.values.indexOf(row.value);
        if (row.checked && index === -1) {
            this.values.push(row.value);
        } else if (!row.checked && index > -1) {
            this.values.splice(index, 1);
            this.full = null;
        }
        this.save();
        this.paint();
    }

    // Draws the choice: the rows, the checkbox of the heading and the plate.
    paint() {
        const chosen = new Set(this.values);
        this.rows.forEach((row) => (row.checked = chosen.has(row.value)));
        this.toggler.checked = this.everything;
        this.toggler.indeterminate = this.values.length > 0 && !this.everything;
        this.toggler.parentElement.classList.remove('warning');
        this.refresh();
    }

    hasOnly() {
        return new URLSearchParams(location.search).has('selected');
    }

    refresh() {
        this.nav.classList.toggle('show', this.values.length > 0 || this.hasOnly());
        // The bar says what is selected.
        let label = this.nav.querySelector('.bulk-count');
        if (!label) {
            label = document.createElement('span');
            label.className = 'bulk-count';
            this.nav.prepend(label);
        }
        label.textContent = (this.toggler.dataset.selectedTooltip ?? '').replace('{count}', this.values.length);

        // ...can show only the chosen rows (and back to all of them)
        let show = this.nav.querySelector('.bulk-show');
        if (!show) {
            show = document.createElement('button');
            show.type = 'button';
            show.className = 'bulk-show';
            show.addEventListener('click', () => this.toggleOnly());
            label.after(show);
        }
        show.textContent = this.hasOnly() ? (this.toggler.dataset.showAllLabel ?? 'Show all') : (this.toggler.dataset.showSelectedLabel ?? 'Show selected');
        // "everything" is the filtered list itself, so there is nothing more to show
        show.hidden = !this.hasOnly() && (this.values.length === 0 || this.values.length > 5000 || this.everything);

        // ...and has a cross to drop the selection
        if (!this.nav.querySelector('.bulk-clear')) {
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'bulk-clear';
            clear.setAttribute('aria-label', this.toggler.dataset.clearLabel ?? 'Clear');
            clear.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
            clear.addEventListener('click', () => this.clear());
            this.nav.append(clear);
        }
    }

    // Only the chosen rows: the ids are POSTed (not put in the address), the server keeps them and answers with a redirect to `?selected=1`; back to all of them is the address without it.
    toggleOnly() {
        if (this.hasOnly()) {
            const url = new URL(location.href);
            url.searchParams.delete('selected');
            url.searchParams.delete('page');
            this.visit(url);

            return;
        }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = location.pathname;
        form.hidden = true;
        const ids = document.createElement('input');
        ids.type = 'hidden';
        ids.name = 'ids';
        ids.value = this.values.join(',');
        form.append(ids);
        document.body.append(form);
        form.submit();
    }

    visit(url) {
        window.Turbo ? window.Turbo.visit(url.toString()) : location.assign(url.toString());
    }

    clear() {
        this.values = [];
        this.full = null;
        this.save();
        this.hideTooltip();
        this.paint();
        if (this.hasOnly()) {
            const url = new URL(location.href);
            url.searchParams.delete('selected');
            this.visit(url);
        }
    }

    // A link of the plate: a deletion is confirmed first, a change asks for the values; any other action goes at once.
    submit(event, link) {
        event.preventDefault();
        const dialog = (link.dataset.confirm || link.dataset.bulkUpdate) ? document.querySelector(link.dataset.confirm || link.dataset.bulkUpdate) : null;
        if (!dialog) return this.send(link);

        const text = dialog.querySelector('[data-bulk-text]');
        if (text) {
            text.textContent = (text.dataset.text ?? '').replace('{count}', this.values.length);
        }
        if (link.dataset.bulkUpdate) {
            const update = dialog.querySelector('form');
            this.fill((name) => update.querySelector(`[name$="[${name}]"]`));
            update.onsubmit = () => {
                this.values = [];
                this.full = null;
                this.save();
            };
        } else {
            const confirm = dialog.querySelector('[data-bulk-confirm]');
            confirm.onclick = () => {
                dialog.close();
                this.send(link);
            };
        }
        dialog.showModal();
    }

    // What a bulk operation is sent: the ids of the chosen rows, or, when every row of the filter is chosen, "all" and the query of the list (its filter and column search),
    // which the server turns into the ids itself: no list of thousands of ids on the way. Always POST.
    fill(field) {
        const all = this.everything;
        field('entities').value = all ? '' : this.values.join(',');
        field('all').value = all ? '1' : '0';
        const query = field('query');
        if (query) query.value = all ? this.signature() : '';
    }

    send(link) {
        this.form.dataset.manual = 'true';
        this.form.action = link.href;
        const method = (link.dataset.method ?? 'POST').toUpperCase();
        this.form.method = ['GET', 'POST'].includes(method) ? method : 'POST';
        let hidden = this.form.querySelector('[name=_method]');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = '_method';
            this.form.appendChild(hidden);
        }
        hidden.value = method;
        this.fill((name) => this.form.querySelector(`[name=${name}]`));
        // what was done is over: the choice does not outlive it
        this.values = [];
        this.full = null;
        this.save();
        this.form.submit();
    }

    hideTooltip() {
        const parent = this.toggler.parentElement;
        Tooltip.getInstance(parent)?.dispose();
        clearTimeout(parent.timeout);
    }
}
