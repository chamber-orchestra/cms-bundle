'use strict';

export const on = (root, event, selector, handler) => {
    root.addEventListener(event, (e) => {
        for (let target = e.target; target && target !== root; target = target.parentNode) {
            if (target.matches(selector)) {
                handler.call(target, e);
                break;
            }
        }
    }, false);
};


export const getElementFromEvent = (e) => {
    return e.target;
};

export const createElementFromHtml = (html) => {
    const template = document.createElement('template');
    template.innerHTML = html.trim();

    return template.content.firstChild;
};

export const createElementsFromHtml = (html) => {
    const template = document.createElement('template');
    template.innerHTML = html.trim();

    return template.content.childNodes;
};

export const createFragmentFromHtml = (html) => {
    const fragment = document.createDocumentFragment();
    fragment.innerHTML = html.trim();

    return fragment;
};


export const next = (element, selector) => {
    do {
        element = element.nextSibling;
    } while (element && !element.matches(selector));

    return element;
};

export const closest = (element, selector) => {
    do {
        element = element.parentNode;
    } while (element && !element.matches(selector));

    return element;
};

export const vars = (element, name) => {
    return [...element.querySelectorAll(`[data-var="${name}"]`)]
}

export const parse = (element, data) => {
    Object.keys(data).forEach((key) => {
        vars(element, key).forEach(el => {
            el.innerHTML = typeof data[key] === 'object' ? JSON.stringify(data[key]) : data[key];
        })
    })
}

export const setCookie = (name, value, expire = 365) => {
    const date = new Date();
    date.setTime(date.getTime() + (expire * 24 * 60 * 60 * 1000));
    document.cookie = name + "=" + value + "; expires=" + date.toGMTString();
}


export const ready = (callback) => {
    if (document.readyState === 'complete') {
        callback();
        return;
    }
    document.addEventListener("DOMContentLoaded", callback);
}


export const element = (selector, parent = document) => {
    return parent.querySelector(selector)
}

export const collection = (selector, parent = document) => {
    return [...parent.querySelectorAll(selector)]
}