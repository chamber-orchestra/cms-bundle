import {Validator} from './validator/index';
import {button} from "../button/index";
import '../phone/index'
import {on} from '../utils'
import observer from "../observer";

const DATA_KEY = 'dv.form';
const NAMESPACE = `.${DATA_KEY}`;

export const Selector = {
    FORM: 'form',
    SUBMIT: '[type=submit]',
    FILE: 'input[type=file]',
    FEEDBACK: ".invalid-feedback, .invalid-tooltip",
    GROUP: '.form-group',
    INPUT: 'input,select,textarea',
};

export const Events = {
    SUBMIT: 'submit',
    INPUT: 'input',
    CHANGE: 'change',
    INVALID: 'valid',
    ERROR: `error`,
    COMPLETE: `complete`,
    SUCCESS: `success`,
    ABORTED: `aborted`,
    VALIDATE: `validate`,
    BEFORE_SUBMIT: `before_submit`,
};


class Form {
    constructor(el) {
        this.$el = el;
        this.busy = false;
        this.validator = new Validator(el);

        this._attachEvents();
    }

    _attachEvents() {

        const _this = this;
        on(this.$el, Events.INPUT, Selector.INPUT, function (e) {
            _this.validator.performNativeValidationOnElement(this);
        });

        this.$el.addEventListener(Events.SUBMIT, (e) => this.submit(e));
    }

    onSuccess(e, request) {
        // const data = request.response || {};
        // const code = request.status;

        const data = request.response.data || {};
        const code = request.response.data.status;
        const event = new CustomEvent(Events.SUCCESS, {cancelable: true, detail: data});
        this.$el.dispatchEvent(event);
        if (event.defaultPrevented) return;

        if ([201, 301, 302, 303, 307, 308].indexOf(code) >= 0) {
            if (!data.location) {
                console.error('RedirectResponse must contain location header');
                return;
            }

            location.href = data.location;
            return
        }

        if ([200].indexOf(code) >= 0) {
            if (data.html !== undefined) {
                requestAnimationFrame(() => {
                    this.$el.innerHTML = data.html;
                });
            }
        }
    }

    onError(e, request, network = false) {

        this.validator.reset();

        if (network) {
            this.validator.setApiNetworkError();
            this.validator.showValidityMessages();
            return;
        }

        const event = new CustomEvent(Events.ERROR, {cancelable: true, detail: request});
        this.$el.dispatchEvent(event);
        if (event.defaultPrevented) return;


        if (request.status === 401) {
            this.validator.setAcessDeniedResponseError();
            this.validator.showValidityMessages();
            return;
        }


        if (!request.response) {
            //better for developer
            this.validator.setApiBadResponseError();
            this.validator.showValidityMessages();
            return;
        }

        if (request.status === 500 && request.response) {
            this.validator.setInternalServerResponseError(request.response);
            this.validator.showValidityMessages();
            return;
        }


        const data = request.response || {};
        //default behaviour
        if (data.violations !== undefined) {
            this.validator.transpileApiErrors(data.violations);
        }

        this.validator.showValidityMessages();
    }


    block(e, request) {
        this.$el.disabled = true;
        [...this.$el.querySelectorAll(Selector.INPUT)].forEach(el => el.disabled = true);
        [...this.$el.querySelectorAll(Selector.SUBMIT)].forEach((el) => button(el, 'loading'));
    }

    unblock(e, request) {
        this.$el.disabled = false;
        [...this.$el.querySelectorAll(Selector.INPUT)].forEach(el => el.disabled = false);
        [...this.$el.querySelectorAll(Selector.SUBMIT)].forEach((el) => button(el, 'reset'));
    }

    send(data) {
        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.open(this.$el.method, this.$el.action, true);
            request.responseType = 'json';
            request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            request.onload = (e) => {
                if (request.status >= 200 && request.status < 400) {
                    resolve({event: e, request: request});
                    return;
                }
                reject({event: e, request: request})
            };
            request.onerror = (e) => reject({event: e, request: request, network: true});
            request.send(data);
        });
    }

    buildOptions() {
        const data = new FormData(this.$el);
        const event = new CustomEvent(Events.BEFORE_SUBMIT, {cancelable: true, detail: data});
        this.$el.dispatchEvent(event);
        if (event.defaultPrevented) return null;

        return data;
    }


    submit(e) {
        e.preventDefault();
        e.stopPropagation();

        if (this.busy) return;
        this.busy = true;

        this.validator.performNativeValidation();
        if (!this.validator.isValid()) {
            this.validator.showValidityMessages();
            this.busy = false;
            return;
        }

        const options = this.buildOptions();
        if (!options) {
            this.busy = false;
            return;
        }

        this.block();
        this.send(options)
            .then(({e, request}) => this.onSuccess(e, request))
            .catch(({e, request, network = false}) => this.onError(e, request, network))
            .finally(() => {
                this.busy = false;
                this.unblock()
            });
    }
}

observer.observe(Selector.FORM, (el) => {
    if (el.dataset.type === 'manual') {
        return;
    }

    if (!el[NAMESPACE]) {
        el[NAMESPACE] = new Form(el);
    }
});

