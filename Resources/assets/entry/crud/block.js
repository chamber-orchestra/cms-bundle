import '../base';
import './block.scss';

import observer from "../../component/observer";

observer.once('[data-bs-toggle=modal]', () => {
    import("../../js/bootstrap/modal")
});
observer.once('[data-form][data-values]', () => {
    import("../../js/plugins/jquery.values")
});

observer.once('form', () => {
    import("../../js/form/index")
});
observer.once('[data-toggle="move"][data-direction][href]', () => {
    import("../../component/sort/index")
});
