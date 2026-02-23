"use strict";
import './collection.scss'
import observer from '../observer';
import {createElementFromHtml, createFragmentFromHtml, on} from '../utils';

const NAMESPACE = ".dv.collection";

export const Selector = {
    TARGET: '[data-collection]',
    ELEMENT: '[data-collection-element]',
    ADD: "[data-toggle='add']",
    REMOVE: "[data-toggle='remove']",
    MOVE: "[data-move]",
}

function decode(string) {
    const e = document.createElement('div');
    e.innerHTML = string;
    return e.childNodes.length === 0 ? "" : e.childNodes[0].nodeValue;
}

export const Events = {
    SYNC: 'sync',
    CLICK: 'click',
    ADDED: 'added',
    REMOVED: 'removed'
}

export const Data = {
    TAGS: 'widgetTags',
    PROTOTYPE: 'prototype',
    INDEX: 'index',
    MOVE: 'move',
    PROTOTYPE_NAME: 'collectionName',
    PROTOTYPE_ID: 'collectionId',
    MAX: 'max',
}

export const Attr = {
    ELEMENT: 'data-collection-element'
}


const Pattern = {
    NAME: /__name__/g
}

class Collection {
    constructor(el) {
        this.$el = el;
        this.id = el.id;
        this.count = el.childElementCount;
        this.actualCount = el.childElementCount;
        [...this.$el.children].forEach((el, index) => el.index = index);

        this._tags = decode(el.dataset[Data.TAGS]);
        this._prototype = decode(el.dataset[Data.PROTOTYPE]);

        this._attachListeners();
    }


    _attachListeners() {
        this.$el.addEventListener(Events.SYNC, (e) => {
            e.preventDefault();
            this.syncInputs();
        })

        observer.once(`${Selector.ADD}[data-target="#${this.id}"]`, (el) => {
            el.addEventListener(Events.CLICK, (e) => {
                e.preventDefault();
                this._add(e);
            })
        });


        on(document, Events.CLICK, `${Selector.REMOVE}[data-target="#${this.id}"]`, (e) => {
            e.preventDefault();
            [...this.$el.children].forEach((el) => {
                if (el.contains(e.target)) {
                    this._remove(el);
                    return;
                }
            });
        });


        on(document, Events.CLICK, `${Selector.MOVE}[data-target="#${this.id}"]`, (e) => {
            e.preventDefault();
            [...this.$el.children].forEach((el) => {
                if (el.contains(e.target)) {
                    const target = e.target.tagName === 'button' ? e.target : e.target.closest('button');
                    const direction = target.dataset[Data.MOVE] === 'up' ? -1 : 1;

                    this._move(el, direction);
                    return;
                }
            });
        });

        observer.once(`${Selector.MOVE}[data-target="#${this.id}"]`, (el) => {
            el.addEventListener(Events.CLICK, (e) => {
                e.preventDefault();
            })
        })
    }


    _add() {
        const max = parseInt(this.$el.dataset[Data.MAX] ?? -1);
        if (max >= 0 && this.actualCount >= max) {
            return;
        }
        const widget = createElementFromHtml(this._tags);
        widget.index = this.count;
        widget.dataset[Data.INDEX] = this.count;
        widget.setAttribute(Attr.ELEMENT, true)


        const fragment = createFragmentFromHtml(this._prototype.replace(Pattern.NAME, this.count));
        widget.innerHTML = fragment.innerHTML;

        const name = this.$el.dataset[Data.PROTOTYPE_NAME];
        const sortOrder = widget.querySelector(`[name="${name.replace(Pattern.NAME, this.count)}[sortOrder]"]`);
        if (sortOrder) {
            sortOrder.value = this.actualCount + 1;
        }

        this.$el.appendChild(widget);
        this.$el.dispatchEvent(new CustomEvent(Events.ADDED, {
            detail: {element: widget, index: this.count}
        }))
        this.count++;
        this.actualCount++;

        this.syncInputs();
    }

    syncInputs() {
        const max = parseInt(this.$el.dataset[Data.MAX]) || -1;
        const addButton = document.querySelector(`${Selector.ADD}[data-target="#${this.id}"]`);

        if (addButton) {
            addButton.disabled = max >= 0 && this.actualCount >= max;
        }
    }

    _remove(el) {

        let next = el;
        while (next = next.nextSibling) {
            this._changeIndex(next, -1);
        }

        el.remove();
        // do not remove counter, CollectionType depends on name attribute, remove only actuall count
        this.actualCount--;
        this.$el.dispatchEvent(new CustomEvent(Events.REMOVED));
        this.syncInputs();
    }

    _changeIndex(widget, diff) {
        const index = widget.index;

        widget.index = index + diff;
        widget.dataset[Data.INDEX] = index + diff;


        const name = this.$el.dataset[Data.PROTOTYPE_NAME];
        const id = this.$el.dataset[Data.PROTOTYPE_ID];

        const sortOrder = widget.querySelector(`[name$="[sortOrder]"]`);
        if (sortOrder) {
            sortOrder.value = index + diff + 1;
        }

        // do not change name and id, because CollectionType depends on name attribute
    }

    _move(el, direction) {

        const prev = direction < 0 ? el.previousSibling : el;
        const next = direction < 0 ? el : el.nextSibling;

        if (!prev || !next) {
            return;
        }

        next.dispatchEvent(new CustomEvent('before_update.dev.sort'))
        prev.dispatchEvent(new CustomEvent('before_update.dev.sort'))
        this.$el.insertBefore(next, prev);
        this._changeIndex(next, -1)
        this._changeIndex(prev, 1)
        next.dispatchEvent(new CustomEvent('after_update.dev.sort'))
        prev.dispatchEvent(new CustomEvent('after_update.dev.sort'))
    }
}


observer.observe(Selector.TARGET, (el) => {
    if (!el[NAMESPACE]) {
        el[NAMESPACE] = new Collection(el);
    }
})