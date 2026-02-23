'use strict';
import '../jquery-global';
import './index.scss';
import $ from 'jquery';
import select2Init from 'select2/dist/js/select2';
import observer from "../../component/observer";

// Select2 UMD CommonJS branch exports a factory function — call it to register $.fn.select2
if (typeof select2Init === 'function') {
    select2Init(window, $);
}

$.fn.select2.defaults.set("theme", "bootstrap-5");

observer.observe('select:not(.flatpickr-monthDropdown-months):not([readonly]):not(.no-plugin):not(.no-auto-select2)', (el) => {
    $(el).select2();
});
