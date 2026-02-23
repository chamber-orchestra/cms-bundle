"use strict";

import {closest} from '../utils'
import observer from "../observer";
import {button} from "../button";

const NAMESPACE = 'dv.file';

const Data = {
    TARGET: 'target',
}

const Selector = {
    ELEMENT: `[data-upload][data-${Data.TARGET}]`,
    DETACH: `[data-detach=file]`,
    INPUT: `input[type=file]`,
    CHECKBOX: `input[type=checkbox]`,
    GROUP: `.form-group`,
    ALERT: `.alert`,
    CONTROL: '.form-control',
    PLACEHOLDER: '.ratio > div',
};


const Events = {
    CLICK: `click`,
    INPUT: `input`,
    LOAD: 'load',
    COMPLETE: `complete`,
    IMAGE: `image`,
    AUDIO: `audio`,
    UNSUPPORTED: `unsupported`,
};

const Class = {
    SHOW: 'show',
    DISABLED: 'disabled'
}


function assert() {
    if (File === undefined || FileList === undefined && FileReader === undefined) {
        throw Error('You need a browser with file reader support, to use this form properly.');
    }
}


class Upload {
    constructor(el) {
        assert();

        this.uploadButton = el;
        this.group = closest(el, Selector.GROUP)
        this.input = document.querySelector(el.dataset[Data.TARGET]);

        this.detachButton = this.group.querySelector(Selector.DETACH);
        if (this.detachButton) {
            this.detachButton.disabled = this.detachButton.hasAttribute('disabled')
            this.detachCheckbox = this.group.querySelector(Selector.CHECKBOX);
        }

        this.fileText = this.group.querySelector(Selector.CONTROL);
        this.imagePlaceholder = this.group.querySelector(Selector.PLACEHOLDER);

        this._attachEvents();
    }

    _attachEvents() {

        this.uploadButton.addEventListener(Events.CLICK, (e) => {
            e.preventDefault();
            e.stopPropagation();

            this.input.click();
        });

        if (this.detachButton) {
            this.detachButton.addEventListener(Events.CLICK, (e) => {
                e.preventDefault();
                e.stopPropagation();

                if (this.detachButton.disabled) {
                    return
                }

                this._blockDetachButton();
                this._detach();
            });
        }

        this.input.addEventListener(Events.INPUT, (e) => {

            const files = [...this.input.files];
            if (!files.length) {
                return;
            }

            this._detach(false);
            button(this.uploadButton, 'loading');
            let ind = 0;

            files.forEach((file) => {
                const reader = new FileReader();
                reader.addEventListener(Events.LOAD, (event) => {
                    this._attach(files[ind], event.target.result, ind);
                    if (ind === files.length - 1) {
                        if (this.detachButton) this._unblockDetachButton();
                        if (this.detachCheckbox) this.detachCheckbox.checked = false;
                        button(this.uploadButton, 'reset');
                    }
                    ind++;
                });

                reader.readAsDataURL(file)
            })

        });
    }


    _blockDetachButton() {
        this.detachButton.disabled = true;
        this.detachButton.classList.add(Class.DISABLED)
    }

    _unblockDetachButton() {
        this.detachButton.disabled = false;
        this.detachButton.classList.remove(Class.DISABLED)
    }

    _attach(file, binary, index) {
        let names = this.fileText.textContent ? this.fileText.textContent.split(',') : [];
        names.push(file.name);
        this.fileText.textContent = names.join(', ');
        if (index === 0 && file.type.match('image') && this.imagePlaceholder) {
            this.imagePlaceholder.style.backgroundImage = `url("${binary}")`;
        }
    }

    _detach(clearInput = true) {
        if (clearInput) {
            this.input.value = null;
        }
        this.fileText.textContent = '';

        if (this.detachCheckbox) {
            this.detachCheckbox.checked = true;
        }

        if (this.imagePlaceholder) {
            this.imagePlaceholder.style.backgroundImage = null;
        }
    }
}

observer.observe(Selector.ELEMENT, (el) => {
    if (!el[NAMESPACE]) {
        el[NAMESPACE] = new Upload(el);
    }
});

