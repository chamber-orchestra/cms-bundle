'use strict';
import Popover from 'bootstrap/js/src/popover';
import Tooltip from 'bootstrap/js/src/tooltip';
import '../../js/bootstrap/popover'

const Selector = {
    ROOT: '[data-update-url]',
    ELEMENT: '[data-update-url] [data-input]',
    PRELOAD: '#update-form-container'
};

const Events = {
    CLICK: 'click'
};


function md5(str, seed = 0) {
    let h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed;
    for (let i = 0, ch; i < str.length; i++) {
        ch = str.charCodeAt(i);
        h1 = Math.imul(h1 ^ ch, 2654435761);
        h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ h1 >>> 16, 2246822507) ^ Math.imul(h2 ^ h2 >>> 13, 3266489909);
    h2 = Math.imul(h2 ^ h2 >>> 16, 2246822507) ^ Math.imul(h1 ^ h1 >>> 13, 3266489909);
    return 4294967296 * (2097151 & h2) + (h1 >>> 0);
}

function preloadForm(url, callback) {

    const hash = md5(url);
    const preload = document.querySelector(Selector.PRELOAD);

    if (preload.current === hash) {
        callback(preload);
        return;
    }

    fetch(url)
        .then(response => response.text())
        .then(html => {
            preload.current = hash;
            preload.innerHTML = html;
            callback(preload);
        });
}


function createPopoverContainer(el) {

    const TEMPLATE =
        '<form>__BODY__</form>' +
        '<button class="btn btn-outline-success w-100">save</button>'

    const clone = el.cloneNode(true);
    return TEMPLATE.replace(/__BODY__/, clone.outerHTML)
}

document.addEventListener(Events.CLICK, function (e) {
    const target = e.target.closest(Selector.ELEMENT);
    if (!target) return;

    return;
    const root = target.closest(Selector.ROOT);
    const url = root.dataset.updateUrl;
    const input = target.dataset.input;

    preloadForm(url, (container) => {

        const form = container.querySelector('form');
        const id = form.getAttribute('id');
        const el = form.querySelector(`#${id}_${input}`);

        const template = createPopoverContainer(el);
        const allowList = {...Tooltip.Default.allowList};
        allowList.form = [];
        allowList.button = [];
        allowList.input = ['value'];
        allowList.select = [];
        allowList.option = [];

        const popover = new Popover(target, {
            content: template,
            trigger: 'manual',
            placement: 'bottom',
            container: 'body',
            boundary: 'viewport',
            html: true,
            allowList: allowList
        });
        popover.show();
    });
});
