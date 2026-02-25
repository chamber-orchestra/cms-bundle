import observer from "../../component/observer";

import ('../../component/form/index');

observer.once('input[type=file]', () => {
    import("../../component/upload/index")
});

observer.once('[data-collection]', () => {
    import("../../component/collection/index")
});

observer.once('[data-code-mode]', () => {
    import("../../js/codemirror/index");
});

observer.once('[data-wysiwyg]', () => {
    import("../../js/tinymce/index")
});

observer.once('select', () => {
    import("../../js/select2/index")
});

observer.once('[data-group="date"],[data-group="time"],[data-group="datetime"]', () => {
    import("../../plugins/flatpickr/index")
});

observer.once('.js-color-field', () => {
    import("../../js/background_color/index")
});

observer.once('.js-footer-color-field', () => {
    import("../../js/form_footer_color/index")
});