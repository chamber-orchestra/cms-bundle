import Tooltip from 'bootstrap/js/src/tooltip';

let values = [];
let all = false;
let allOnPage = false;

const block = document.querySelector('#bulk-nav');
const links = [...document.querySelectorAll('#bulk-nav a')];
const elms = [...document.querySelectorAll('[name=bulk_operation_input]')];
const toggler = document.querySelector('#bulk-operation-toggler-input');
const form = document.querySelector('#bulk-operation-form');

const selectAllOnPage = () => {
    all = false;
    allOnPage = true;
    values = [];

    elms.forEach(el => {
        el.checked = true;
        values.push(el.value);
    })
}

const selectAll = () => {
    values = [];
    allOnPage = false;
    all = true;
    elms.forEach(el => {
        el.checked = true;
        values.push(el.value);
    })
}

const reset = () => {
    values = [];
    all = allOnPage = false;
    elms.forEach(el => el.checked = false)
}

const hideTooltip = () => {
    const parent = toggler.parentElement;
    const instance = Tooltip.getInstance(parent);
    if (instance) {
        instance.dispose();
    }
    clearTimeout(parent.timeout);
}

const showTooltip = (title) => {
    const parent = toggler.parentElement;
    const existing = Tooltip.getInstance(parent);
    if (existing) {
        existing.dispose();
    }

    const tooltip = new Tooltip(parent, {
        html: true,
        title: title,
        trigger: 'manual',
        placement: 'right',
        container: 'body',
        boundary: 'viewport'
    });
    tooltip.show();

    parent.timeout = setTimeout(() => {
        const instance = Tooltip.getInstance(parent);
        if (instance) {
            instance.hide();
        }
    }, 3000);
}


const showAllOnPageTooltip = () => {
    const parent = toggler.parentElement;
    toggler.checked = false;
    toggler.indeterminate = true;
    parent.title = toggler.dataset['allOnPageTooltip'];
    parent.classList.remove('warning')
    showTooltip(parent.title);
}

const showAllTooltip = () => {
    const parent = toggler.parentElement;
    toggler.checked = true;
    toggler.indeterminate = false;
    parent.title = toggler.dataset['allTooltip'];
    parent.classList.add('warning');
    showTooltip(parent.title);
}

const showSelectedTooltip = (count) => {
    const parent = toggler.parentElement;
    parent.title = toggler.dataset['selectedTooltip'].replace('{count}', count);
    showTooltip(parent.title);
}


const process = () => {
    const parent = toggler.parentElement;
    hideTooltip();

    if (!allOnPage && !all) {
        selectAllOnPage();
        showAllOnPageTooltip();
        return;
    }

    if (allOnPage && !all) {
        selectAll();
        showAllTooltip();
        return;
    }

    reset();
    toggler.checked = false;
    parent.title = '';
    parent.classList.remove('warning')
}

const onChange = () => {
    const visible = values.length || all;
    visible ? block.classList.add('show') : block.classList.remove('show')
}

toggler && toggler.addEventListener('click', (e) => {
    process();
    onChange();
});


elms.forEach(el => el.addEventListener('input', (e) => {
    all = allOnPage = false;
    toggler.indeterminate = false;
    toggler.checked = false;

    const idx = values.indexOf(e.target.value);
    idx > -1 ? values.splice(idx, 1) : values.push(e.target.value);

    onChange();
    hideTooltip();

    if (values.length === elms.length) {
        allOnPage = true;
        all = false;
        showAllOnPageTooltip();
        return;
    }

    if (values.length > 0) {
        showSelectedTooltip(values.length);
        return;
    }

    hideTooltip();
}));


links.forEach(el => el.addEventListener('click', (e) => {
    e.preventDefault();
    form.dataset['manual'] = true;
    form.action = el.href;

    const method = (el.dataset['method'] ?? 'POST').toUpperCase();
    form.method = ['GET', 'POST'].indexOf(method) > -1 ? method : 'POST';

    let hidden = form.querySelector('[name=_method]');
    if (!hidden) {
        hidden = document.createElement('input')
        hidden.type = 'hidden';
        hidden.name = '_method';
        form.appendChild(hidden);
    }
    hidden.value = method;
    form.querySelector('[name=entities]').value = values.join(',');
    form.querySelector('[name=all]').value = all | 0;
    form.submit();
}));


window.addEventListener("pageshow", () => {
    elms.forEach(el => el.checked = false);
});
