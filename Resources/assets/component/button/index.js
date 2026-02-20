"use strict";

const DATA_KEY = 'dv.button';
const NAMESPACE = `.${DATA_KEY}`;

const Prototype = {
    LOADING: "<i class='fad fa-sync-alt fa-spin fa-fw'></i>"
};

export const State = {
    LOADING: 'loading',
    RESET: 'reset'
};

export class Button {
    constructor(el) {
        this.$el = el;
        this.defaults = {
            html: el.innerHTML,
            rect: el.getBoundingClientRect(),
            disabled: el.disabled,
            style: {
                minWidth: el.style.minWidth,
                minHeight: el.style.minHeight,
            }
        };

    }

    state(state) {
        switch (state) {
            case State.LOADING:
                this.$el.disabled = true;
                this.$el.style.minWidth = `${this.defaults.rect.width}px`
                this.$el.style.minHeight = `${this.defaults.rect.height}px`;
                this.$el.innerHTML = Prototype.LOADING;

                break;
            case State.RESET:
                this.$el.disabled = this.defaults.disabled;
                this.$el.style.minWidth = this.defaults.style.minWidth;
                this.$el.style.minHeight = this.defaults.style.minHeight;
                this.$el.innerHTML = this.defaults.html;

                break;
        }
    }
}


export const button = (el, state = 'reset') => {

    if (!el[DATA_KEY]) {
        el[DATA_KEY] = new Button(el);
    }

    el[DATA_KEY].state(state);
};
