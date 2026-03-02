import '../base';
import './edit.scss';
import observer from "../../component/observer";

import ('../../js/form/index');

observer.once('[data-bs-toggle=modal]', () => {
    import("../../js/bootstrap/modal")
});

observer.once('[data-bs-toggle=tab],[data-bs-toggle=pill]', () => {
    import("../../js/bootstrap/tab")
});

observer.once('[data-bs-toggle="tooltip"]', () => {
    import("../../js/bootstrap/tooltip")
});

observer.once('[data-bs-toggle="button"]', () => {
    import("../../js/bootstrap/button")
});

import('../../js/schemaField/index');
