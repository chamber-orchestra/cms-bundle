'use strict';

import {on} from '../utils'

const Selector = {
    INCREASE: '[data-toggle=increase][data-target]',
    DECREASE: '[data-toggle=decrease][data-target]',
};
const Events = {
    CLICK: 'click',
    INPUT: 'input',
    CHANGE: 'change',
};

on(document, Events.CLICK, `${Selector.INCREASE}, ${Selector.DECREASE}`, function (e) {
    e.preventDefault();

    const target = document.querySelectorAll(this.dataset.target);
    const delta = this.dataset.toggle === 'increase' ? 1 : -1;
    target.forEach((el) => {

        const min = el.hasAttribute('min') ? el.getAttribute('min') : null;
        const max = el.hasAttribute('max') ? el.getAttribute('max') : null;

        let value = parseInt(el.value) + delta;

        if (min !== null) {
            value = Math.max(min, value);
        }

        if (max !== null) {
            value = Math.min(max, value);
        }

        el.value = value;
        el.dispatchEvent(new Event(Events.INPUT, {
            bubbles: true
        }));
    });
});