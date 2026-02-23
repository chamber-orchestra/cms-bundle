'use strict';
import $ from 'jquery';
import Modal from 'bootstrap/js/src/modal';


$.fn.values = function (data) {
    return this.each(function () {
        const $$ = $(this);
        const name = $$.attr('name');

        $.each(data, (key, value) => {
            if (typeof (value) == 'object') {
                value = JSON.stringify(value);
            }

            $$.find('#' + name + "_" + key).val(value);
        });
    });
};


const $doc = $(document);

$doc.on('click', '[data-form][data-values]', function (e) {
    const $$ = $(this);
    const target = $$.data('form');

    const $target = $(target);
    if (!$target.length || !$target.is('form')) {
        console.error('No target form"' + target + '"');
        return;
    }

    const values = $.extend({}, $$.data("values"));
    if (values) {
        $target.values(values);
    }

    // Show modal if button has data-bs-target
    const modalTarget = $$.attr('data-bs-target');
    if (modalTarget) {
        const modalEl = document.querySelector(modalTarget);
        if (modalEl) {
            Modal.getOrCreateInstance(modalEl).show();
        }
    }
});

// $doc.on('click', '[data-target][data-values]', function (e) {
//     e.preventDefault();
//
//     const $$ = $(this);
//     const target = $$.data('target');
//
//     const $target = $(target);
//     if (!$target.length) {
//         console.error('No target "' + target + '"');
//         return;
//     }
//
//
//     const values = $.extend({}, $$.data("values"));
//     if (values) {
//         $target.values(values);
//     }
// });
//
