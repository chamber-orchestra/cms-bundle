import { Controller } from '@hotwired/stimulus';

// The search box of the bar: it widens when it is used. Under it: while it is empty, hints of what can be searched (from `?format=json` with no words: items that
// carry `fill`, a text to put in the box) and the last searches (kept in the browser); while typing, the suggestions (orders, products...). Arrows and Enter to
// choose, Esc to close. Without JavaScript the box is a plain form that opens the page with all results.
export default class extends Controller {
    static targets = ['input', 'list'];
    static values = { url: String };

    connect() {
        this.onOutside = (event) => {
            if (!this.element.contains(event.target)) this.close();
        };
        document.addEventListener('click', this.onOutside);
        // "/" jumps to the box, as in many tools (not while something is being typed).
        this.onSlash = (event) => {
            const typing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]');
            if (event.key === '/' && !typing && !event.metaKey && !event.ctrlKey && !event.altKey) {
                event.preventDefault();
                this.inputTarget.focus();
            }
        };
        document.addEventListener('keydown', this.onSlash);
    }

    // The box is used: it widens (CSS, :focus-within) and, while it is empty, offers the hints and the last searches.
    focus() {
        if (this.inputTarget.value.trim().length < 2) this.showIdle();
    }

    async showIdle() {
        const recent = this.recent();
        let groups = [];
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('q', '');
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.ok) groups = await response.json();
        } catch {
            groups = [];
        }
        if (this.inputTarget.value.trim().length >= 2) return; // the visitor has typed meanwhile
        if (recent.length > 0) {
            groups = [{ group: 'recent', title: this.element.dataset.recentTitle ?? 'Recent', items: recent.map((text) => ({ label: text, sub: '', fill: text })) }, ...groups];
        }
        this.render(groups);
    }

    recent() {
        try {
            const list = JSON.parse(window.localStorage.getItem('cms-search-recent') ?? '[]');

            return Array.isArray(list) ? list.filter((x) => typeof x === 'string').slice(0, 5) : [];
        } catch {
            return [];
        }
    }

    remember() {
        const q = this.inputTarget.value.trim();
        if (q.length < 2) return;
        try {
            const list = [q, ...this.recent().filter((x) => x !== q)].slice(0, 5);
            window.localStorage.setItem('cms-search-recent', JSON.stringify(list));
        } catch {
            // no storage (a private window): nothing is remembered
        }
    }

    // a hint or a recent search puts its words in the box and searches
    fill(event) {
        event.preventDefault();
        this.inputTarget.value = event.currentTarget.dataset.fill ?? '';
        this.inputTarget.focus();
        this.suggest();
    }

    disconnect() {
        document.removeEventListener('click', this.onOutside);
        document.removeEventListener('keydown', this.onSlash);
        clearTimeout(this.timer);
        this.controller?.abort();
    }

    suggest() {
        clearTimeout(this.timer);
        const q = this.inputTarget.value.trim();
        if (q.length < 2) return this.showIdle();
        this.timer = setTimeout(() => this.load(q), 180);
    }

    async load(q) {
        this.controller?.abort();
        this.controller = new AbortController();
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('q', q);
            const response = await fetch(url, { signal: this.controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.ok) this.render(await response.json());
        } catch {
            // a newer request replaced this one, or the network failed: the form still works
        }
    }

    render(groups) {
        this.listTarget.replaceChildren();
        for (const { group, title, items } of groups) {
            const heading = document.createElement('div');
            heading.className = 'cms-search-group';
            heading.textContent = title ?? group;
            this.listTarget.append(heading);
            for (const item of items) {
                const link = document.createElement('a');
                link.className = 'cms-search-item';
                if (item.fill !== undefined) {
                    link.href = '#';
                    link.dataset.fill = item.fill; // a hint: it fills the box
                    link.addEventListener('click', (event) => this.fill(event));
                } else {
                    link.href = item.url;
                    link.addEventListener('click', () => this.remember());
                }
                const label = document.createElement('strong');
                label.textContent = item.label;
                const sub = document.createElement('span');
                sub.textContent = item.sub;
                link.append(label, sub);
                this.listTarget.append(link);
            }
        }
        this.listTarget.hidden = groups.length === 0;
        this.current = -1;
    }

    keys(event) {
        const links = [...this.listTarget.querySelectorAll('a')];
        if (event.key === 'Escape') return this.close();
        if (links.length === 0 || this.listTarget.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            this.current = (this.current + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
            links.forEach((link, i) => link.classList.toggle('active', i === this.current));
        } else if (event.key === 'Enter' && this.current >= 0) {
            event.preventDefault();
            links[this.current].click();
        }
    }

    submit() {
        this.remember();
    }

    close() {
        this.listTarget.hidden = true;
        this.current = -1;
    }
}
