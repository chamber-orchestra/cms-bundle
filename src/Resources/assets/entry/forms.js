// The behaviour of the create/edit forms. Loaded once, on pages that have a form, and each part only when its field is on the page (observer).
// These are native components (no jQuery). Select2, TinyMCE, CodeMirror and flatpickr are gone: a <select> is a native select, dates are the browser's own
// date/time inputs, JSON/YAML are monospace textareas, the rich text field is the Tiptap controller.
import './forms.scss';
import observer from '../component/observer';

import('../component/form/index');

observer.once('input[type=file]', () => import('../component/upload/index'));
observer.once('[data-collection]', () => import('../component/collection/index'));
observer.once('.js-color-field', () => import('../js/background_color/index'));
observer.once('.js-footer-color-field', () => import('../js/form_footer_color/index'));
