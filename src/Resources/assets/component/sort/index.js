'use strict';

import {button} from '../button/index'

const $doc = $(document);
const selector = '[data-toggle="move"][data-direction][href]';
const sortSelector = '[data-sort-order]';

$doc.on('click', selector, function (e) {
    e.preventDefault();

    const $$ = $(this);
    button($$[0], 'loading');

    const $el = $$.closest(sortSelector);
    const url = $$.attr('href');
    const direction = $$.data('direction');

    const $source = direction === 'up' ? $el : $el.next(sortSelector);
    const $target = direction === 'up' ? $el.prev(sortSelector) : $el;


    $.post(url, () => {
        button($$[0], 'reset');
        $source.insertBefore($target);
    });

});