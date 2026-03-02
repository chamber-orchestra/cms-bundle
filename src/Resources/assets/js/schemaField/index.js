'use strict';

import $ from 'jquery';

/**
 * Toggles visibility of collection-only fields (schema)
 * based on the value of the [data-schema-type] select.
 *
 * SchemaFieldType renders as:
 *   <fieldset id="...">                 ← form_widget_compound of SchemaFieldType
 *     <div.form-group>name</div>
 *     <div.form-group>type select[data-schema-type]</div>
 *     <div.form-group>required</div>
 *     <fieldset.form-group data-collection-only>schema</fieldset>  ← hidden unless collection
 *   </fieldset>
 */
function toggleCollectionFields(select) {
    const fieldset = select.closest('fieldset');
    if (!fieldset) return;

    const isCollection = select.value === 'collection';
    fieldset.querySelectorAll(':scope > [data-collection-only]').forEach(el => {
        el.style.display = isCollection ? '' : 'none';
    });
}

// Initialise all existing selects on page load
document.querySelectorAll('[data-schema-type]').forEach(select => toggleCollectionFields(select));

// Handle type changes via jQuery delegation.
// Select2 fires 'change' through jQuery's $.trigger() which only notifies jQuery handlers,
// not native DOM listeners. jQuery delegated .on() is the only reliable way to catch it.
$(document).on('change', '[data-schema-type]', function () {
    toggleCollectionFields(this);
});

// Initialise selects added dynamically (collection allow_add clones the prototype)
const observer = new MutationObserver(mutations => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (node.nodeType === Node.ELEMENT_NODE) {
                node.querySelectorAll('[data-schema-type]').forEach(select => toggleCollectionFields(select));
            }
        }
    }
});

observer.observe(document.body, {childList: true, subtree: true});
