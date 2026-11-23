// The admin's front end: Bootstrap 6 (alpha) (its own JavaScript, no jQuery), Font Awesome Free and Stimulus controllers (../controllers).
import '../scss/cms.scss';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'bootstrap/js/dist/dialog';
import 'bootstrap/js/dist/menu';
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/tab';
import 'bootstrap/js/dist/tooltip';
import * as Turbo from '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';

// Turbo Drive: a click on a link or the back button changes the page without a full load (the menu, the bar and the scripts stay, the progress bar shows the load). The forms
// keep their own way (the owner's form components send them), so Turbo does not take them over; a page is not kept in the cache of Turbo (the controllers that wrap a field
// would wrap it twice in a restored snapshot: `turbo-cache-control` in the layout).
Turbo.config.forms.mode = 'off';
Turbo.config.drive.progressBarDelay = 150;

const application = Application.start();
const controllers = import.meta.glob('../controllers/*_controller.js', { eager: true });
for (const [path, module] of Object.entries(controllers)) {
    const name = path.split('/').pop().replace('_controller.js', '').replaceAll('_', '-');
    application.register(name, module.default);
}

// Every single select of the admin is a select you can type into (the `combobox` controller); selects that appear later (a dialog, a collection item) too.
const enhance = (root) => {
    root.querySelectorAll('select:not([multiple]):not([size]):not([data-controller~="combobox"])').forEach((select) => {
        select.setAttribute('data-controller', `${select.getAttribute('data-controller') ?? ''} combobox`.trim());
    });
};
// Every text field of a form (input, textarea; also a read-only or disabled one) has a copy button at its right edge, in the top right corner of a textarea. The field is wrapped
// once (`.copyable`); fields with a controller of their own (editor, select), the search of the bar and the fields of an input group are left as they are.
const COPYABLE = 'input:is([type="text"], [type="email"], [type="tel"], [type="url"], [type="number"], :not([type])), textarea';
const copyable = (root) => {
    root.querySelectorAll(COPYABLE).forEach((field) => {
        if (field.hidden || field.closest('.copyable, .input-group, .cb, .cms-bar, .cms-aside, [data-no-copy]') || field.hasAttribute('data-controller') || field.hasAttribute('data-no-copy')) return;
        const wrap = document.createElement('div');
        wrap.className = field.tagName === 'TEXTAREA' ? 'copyable copyable-area' : 'copyable';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'copy-btn';
        button.title = button.ariaLabel = document.documentElement.dataset.copyLabel ?? 'Copy';
        button.dataset.controller = 'copy';
        button.dataset.action = 'click->copy#copy';
        button.innerHTML = '<i class="fas fa-fw fa-copy"></i>';
        field.before(wrap);
        wrap.append(field, button);
    });
};
enhance(document);
copyable(document);
new MutationObserver((records) => {
    for (const record of records) {
        record.addedNodes.forEach((node) => {
            if (node.nodeType !== 1) return;
            const root = node.matches('select, input, textarea') ? (node.parentElement ?? document) : node;
            enhance(root);
            copyable(root); // what comes later (the form of a dialog) gets the copy buttons too
        });
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// Controllers that are heavy are loaded only when the page has an element for them (assets/controllers/lazy).
const lazy = import.meta.glob('../controllers/lazy/*_controller.js');
for (const [path, load] of Object.entries(lazy)) {
    const name = path.split('/').pop().replace('_controller.js', '').replaceAll('_', '-');
    const selector = `[data-controller~="${name}"]`;
    const register = () => load().then((module) => application.register(name, module.default));
    if (document.querySelector(selector)) {
        register();
    } else {
        new MutationObserver((_, observer) => {
            if (document.querySelector(selector)) {
                observer.disconnect();
                register();
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
}

// The forms (uploads, collections, code fields, dates): their native components load when the page has a form.
if (document.querySelector('form')) import('./forms');

export { application };
