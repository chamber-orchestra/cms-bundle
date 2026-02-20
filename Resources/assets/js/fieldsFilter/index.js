'use strict';
import './index.scss'

const Events = {
    CLICK: 'click',
}

const Select = {
    DROPDOWN: '#fields_filter_dropdown',
    DROPDOWN_MENU: '.dropdown-menu',
    DROPDOWN_INPUT: 'input[id^="ff"]'
}

class FieldsFilter {
    constructor(el) {
        this.$el = el;
        this.storageKey = this.$el.dataset.storage;
        this.searchParams = new URLSearchParams(window.location.search);
        this.$el.querySelector(Select.DROPDOWN_MENU).addEventListener(Events.CLICK, (e) => {
            e.stopPropagation();
        })
        this._build();
    }

    _build() {
        this._attachListeners();
    }

    _toggle(heading, cells, checked) {
        checked ? heading.classList.remove('d-none') : heading.classList.add('d-none');
        cells.forEach((cell) => {
            checked ? cell.classList.remove('d-none') : cell.classList.add('d-none')
        })
    }

    _attachListeners() {
        this.$el.querySelectorAll(Select.DROPDOWN_INPUT).forEach((el) => {
            const heading = document.querySelector(`[data-heading="${el.value}"]`);
            const cells = document.querySelectorAll(`[data-input="${el.value}"]`);
            let storedValue = localStorage.getItem(`${this.storageKey}_${el.value}`);

            if (this.searchParams.has(el.value)) {
                storedValue = this.searchParams.get(el.value) === 'true' ? 1 : 0;
                localStorage.setItem(`${this.storageKey}_${el.value}`, storedValue);
                this._setCookie(`${this.storageKey}_${el.value}`, storedValue);
            } else if (storedValue !== null) {
                storedValue = parseInt(storedValue);
                this.searchParams.set(el.value, storedValue ? 'true' : 'false');
                history.pushState(null, '', window.location.pathname + '?' + this.searchParams.toString());
            } else {
                storedValue = 1;
            }

            el.checked = storedValue === 1;
            this._toggle(heading, cells, storedValue);

            el.addEventListener('input', (e) => {
                this._toggle(heading, cells, e.target.checked)
                localStorage.setItem(`${this.storageKey}_${e.target.value}`, e.target.checked ? 1 : 0);
                this._setCookie(`${this.storageKey}_${e.target.value}`, e.target.checked ? 1 : 0);
                this.searchParams.set(e.target.value, e.target.checked);
                history.pushState(null, '', window.location.pathname + '?' + this.searchParams.toString());
            });
        })
    }

    _setCookie(cname, cvalue, exdays) {
        const d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        let expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }
}

new FieldsFilter(document.querySelector(Select.DROPDOWN))