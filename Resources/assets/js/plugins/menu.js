'use strict';
import Collapse from 'bootstrap/js/src/collapse';
import './menuSearch'

const NAMESPACE = 'dv.menu';
const Selector = {
    ROOT: '#nav-aside',
    LINK: '[data-bs-toggle=collapse]',
    NAV_ITEM: '.nav-item',
    NAV: '.nav',
    SIDEBAR_CLOSE: '#sidebar-close',
    SIDEBAR_OPEN: '#sidebar-open',
    ASIDE: '#aside',
    MAIN: '.main'
};

const ClassName = {
    OPEN: 'open'
};

function setCookie(cname, cvalue, exdays) {
    const d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

document.addEventListener('click', function (e) {
    const link = e.target.closest(Selector.LINK);
    if (!link) return;

    const parent = link.closest(Selector.NAV_ITEM);
    if (!parent) return;

    if (parent.classList.contains(ClassName.OPEN) && link.getAttribute('href')) {
        location.href = link.getAttribute('href');
        return;
    }

    e.preventDefault();
    parent.classList.add(ClassName.OPEN);
    const navEl = parent.querySelector(`:scope > ${Selector.NAV}`);
    if (navEl) {
        Collapse.getOrCreateInstance(navEl).toggle();
    }
});

const aside = document.querySelector(Selector.ASIDE);
const main = document.querySelector(Selector.MAIN)
const sidebarOpen = document.querySelector(Selector.SIDEBAR_OPEN);
const sidebarClose = document.querySelector(Selector.SIDEBAR_CLOSE);

sidebarClose && sidebarClose.addEventListener('click', () => {
    aside.classList.toggle('closed');
    sidebarOpen.classList.toggle('d-none');
    sidebarClose.classList.toggle('d-none');
    setCookie('aside-closed', 1)
})

sidebarOpen && sidebarOpen.addEventListener('click', () => {
    aside.classList.toggle('closed');
    sidebarOpen.classList.toggle('d-none');
    sidebarClose.classList.toggle('d-none');
    setCookie('aside-closed', 0)
})
