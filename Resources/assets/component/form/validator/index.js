'use strict';
import i18n from '../../i18n/index'
import ru from '../i18n/ru';
import en from '../i18n/en';
import {closest, createElementFromHtml, next} from '../../utils';

i18n.add(ru, en);

const PROPERTY_PATH_SEPARATOR = '.';
const Class = {
    VALIDATED: 'was-validated',
    INVALID: "is-invalid",
    VALID: "is-valid",
};

const Selector = {
    FEEDBACK: ".invalid-feedback, .invalid-tooltip",
    GROUP: '.form-group',
    INPUT: 'input,select,textarea',
};

const Aria = {
    DESCRIBED: 'aria-describedby'
};

const Prototype = {
    ELEMENT_ERROR: '<span class="mb-0 d-block"><span class="form-error-message">__error__</span></span>',
    FORM_ERROR: "<div class='alert alert-danger'></div>"
};


export class Validator {

    constructor(el) {
        this.$el = el;
        this.name = el.name;
        this._errors = [];
    }

    reset() {
        this.$el.classList.remove(Class.VALIDATED);
        this._errors = [];
        this._resetRootValidationConstraint();

        [...this.$el.querySelectorAll(Selector.GROUP)]
            .forEach((el) => el.classList.remove([Class.INVALID, Class.VALID]));

        [...this.$el.querySelectorAll(Selector.INPUT)]
            .filter((el) => !el.validity.valid)
            .forEach((el) => this._resetCustomValidityMessage(el));
    }

    isValid() {
        return this._errors.length === 0 && this.$el.checkValidity();
    }

    performNativeValidationOnElement(el) {
        this._resetCustomValidityMessage(el);

        if (el.checkValidity()) {
            return;
        }

        this._setElementCustomValidityMessage(el, [Validator.getErrorMessage(el)]);
    }

    performNativeValidation() {
        this.reset();
        [...this.$el.querySelectorAll(Selector.INPUT)]
            .filter((el) => !el.validity.valid)
            .forEach((el) => this._setElementCustomValidityMessage(el, [Validator.getErrorMessage(el)]));
    }

    showValidityMessages() {
        this.$el.classList.add(Class.VALIDATED);

        if (this._errors.length) {
            //scroll to root errors
            requestAnimationFrame(() => {
                this._getElement(this.$el.getAttribute(Aria.DESCRIBED)).scrollIntoView();
            });
            return;
        }

        const invalidElements = [...this.$el.querySelectorAll(Selector.INPUT)].filter((el) => !el.validity.valid);
        if (invalidElements.length) {
            requestAnimationFrame(() => {
                const el = invalidElements[0];
                el.scrollIntoView();
            });
        }
    }


    transpileApiErrors(violations, parent = '') {

        const errors = {};
        violations.forEach(({id, title, parameters, propertyPath}) => {
            errors[propertyPath] = errors[propertyPath] ? errors[propertyPath].push(title) : [title];
        })

        for (let path in errors) {
            if (path === this.$el.name) {
                this._setRootValidationConstraints(errors[path])
                return;
            }

            const elem = this._getElementByPath(path);
            if (elem) {
                this._setElementCustomValidityMessage(elem, errors[path])
            }
        }
    }

    _getElement(id) {
        return document.getElementById(id);
    }

    _getElementByPath(path) {

        const name = path.split(PROPERTY_PATH_SEPARATOR).reduce((name, path) => {
            return name ? `${name}[${path}]` : path;
        }, '');

        const el = this.$el.querySelector(`[name="${name}"]`);
        if (null !== el) {
            return el;
        }

        //do search by id

        const id = path.split(PROPERTY_PATH_SEPARATOR).reduce((name, path) => {
            return name ? `${name}_${path}` : path;
        }, '');

        return this.$el.querySelector(`#${id}`);

    }

    _setRootValidationConstraints(errors) {

        this._errors = errors;
        const html = errors
            .map(error => Prototype.ELEMENT_ERROR.replace(/__error__/, i18n.trans(error)))
            .join('');

        if (this.$el.hasAttribute(Aria.DESCRIBED)) {
            this._getElement(this.$el.getAttribute(Aria.DESCRIBED)).innerHTML = html;
            return;
        }

        const $wrapper = createElementFromHtml(Prototype.FORM_ERROR);
        $wrapper.id = `${this.$el.name}_error_${(new Date()).getTime()}`;
        $wrapper.innerHTML = html;
        this.$el.prepend($wrapper);
        this.$el.setAttribute(Aria.DESCRIBED, $wrapper.id);
    }

    _resetRootValidationConstraint() {
        if (!this.$el.hasAttribute(Aria.DESCRIBED)) {
            return;
        }

        this._getElement(this.$el.getAttribute(Aria.DESCRIBED)).remove();
        this.$el.removeAttribute(Aria.DESCRIBED);

        return;
    }

    setApiBadResponseError() {
        this._setRootValidationConstraints([i18n.trans('validator.smth_went_wrong')]);
    }

    setInternalServerResponseError(response) {
        this._setRootValidationConstraints([i18n.trans(response.message)]);
    }

    setAcessDeniedResponseError() {
        this._setRootValidationConstraints([i18n.trans('validator.access_denied')]);
    }


    setApiNetworkError() {
        this._setRootValidationConstraints([i18n.trans('validator.network_error')]);
    }

    _resetCustomValidityMessage(elem) {
        elem.setCustomValidity('');
        const $group = closest(elem, Selector.GROUP);
        $group.classList.remove(Class.INVALID);
    }

    _setElementCustomValidityMessage(elem, errors = []) {

        const message = errors.map((error) => Prototype.ELEMENT_ERROR.replace(/__error__/g, error)).join('');
        elem.setCustomValidity(message);
        const $group = closest(elem, Selector.GROUP);

        // if already set $wrapper
        if (elem.hasAttribute(Aria.DESCRIBED)) {
            $group.classList.add(Class.INVALID);
            this._getElement(elem.getAttribute(Aria.DESCRIBED)).innerHTML = message;
            return;
        }

        // attach if not attached yet
        const $wrapper = next(elem, Selector.FEEDBACK) || $group.querySelector(Selector.FEEDBACK);
        if (!$wrapper) {
            return;
        }

        $wrapper.innerHTML = message;
        if (!$wrapper.id) {
            $wrapper.id = `${elem.name}_error_${(new Date()).getTime()}`;
        }
        elem.setAttribute(Aria.DESCRIBED, $wrapper.id);
        $group.classList.add(Class.INVALID);
    }


    static getErrorMessage(field) {
        // Don't validate submits, buttons, file and reset inputs, and disabled fields
        if (field.disabled || field.type === 'reset' || field.type === 'submit' || field.type === 'button') return;
        const validity = field.validity;
        if (validity.valid) return;

        const {message, attr} = Validator.getDefaultMessage(field, validity);
        return i18n.trans(message, attr);
    }

    static getDefaultMessage(field, validity) {
        // see https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#the-constraint-validation-api
        if (validity.valueMissing) return {message: 'validator.missed', attr: {}};
        if (validity.typeMismatch) {
            if (field.type === 'email') return {message: 'validator.not_valid_email', attr: {}};
            if (field.type === 'url') return {message: 'validator.not_valid_url', attr: {}};
            return {message: 'validator.not_valid_type', attr: {}};
        }
        if (validity.patternMismatch) {
            if (field.type === 'tel') return {message: 'validator.not_valid_tel', attr: {}};

            return {
                message: 'validator.not_match_pattern',
                attr: {
                    pattern: field.getAttribute('pattern')
                }
            };
        }
        if (validity.tooLong) return {
            message: 'validator.too_long',
            attr: {
                maxlength: field.getAttribute('maxlength'),
            }
        };
        if (validity.tooShort) return {
            message: 'validator.too_short',
            attr: {
                minlength: field.getAttribute('minlength'),
            }
        };
        if (validity.rangeUnderflow) return {
            message: 'validator.underflow',
            attr: {
                min: field.getAttribute('min')
            }
        };
        if (validity.rangeOverflow) return {
            message: 'validator.overflow',
            attr: {
                max: field.getAttribute('max')
            }
        };
        if (validity.stepMismatch) return {
            message: 'validator.step_mismatch',
            attr: {
                step: field.getAttribute('step')
            }
        };
        if (validity.badInput) return {message: 'validator.bad_input', attr: {}};
        if (validity.customError) return {message: validity.customError, attr: {}};
        return {message: 'validator.not_valid', attr: {}};
    }

}