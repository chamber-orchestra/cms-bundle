'use strict';
import flatpickr from 'flatpickr';
import {Events, Selector} from "../../component/collection";

import './index.scss';
import observer from "../../component/observer";

const Format = {
    DATE: 'd.m.Y',
    TIME: 'H:i:s'
};

const options = {
    altInput: false,
    allowInput: true,
    static: true,
    wrap: true,
    minuteIncrement: 1,
    hourIncrement: 1,
};

flatpickr('[data-group="date"]', Object.assign({}, options, {
    enableTime: false,
    altFormat: Format.DATE
}));

flatpickr('[data-group="time"]', Object.assign({}, options, {
    enableTime: true,
    enableSeconds: true,
    enableDate: false,
    altFormat: Format.TIME
}));

flatpickr('[data-group="datetime"]', Object.assign({}, options, {
    enableTime: true,
    enableSeconds: true,
    altFormat: `${Format.DATE} ${Format.TIME}`
}));

observer.observe(Selector.TARGET, (el) => {
    el.addEventListener(Events.ADDED, (e) => {
        const el = e.detail.element;

        flatpickr(el.querySelectorAll('[data-group="date"]'), {
            ...options,
            enableTime: false,
            altFormat: Format.DATE
        });

        flatpickr(el.querySelectorAll('[data-group="time"]'), {
            ...options,
            enableTime: true,
            enableSeconds: true,
            enableDate: false,
            altFormat: Format.TIME
        });

        flatpickr(el.querySelectorAll('[data-group="datetime"]'), {
            ...options,
            enableTime: true,
            enableSeconds: true,
            altFormat: `${Format.DATE} ${Format.TIME}`
        });
    });
})
