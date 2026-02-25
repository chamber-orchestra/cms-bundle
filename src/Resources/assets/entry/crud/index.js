import '../base';
import './index.scss';
import './bulkOperation'
import '../../js/fieldsFilter'
import observer from "../../component/observer";

observer.once('.modal', () => {
    import("../../js/bootstrap/modal")
});

observer.once('[data-bs-toggle=dropdown]', () => {
    import("../../js/bootstrap/dropdown")
});

observer.once('[data-bs-toggle="tooltip"]', () => {
    import("../../js/bootstrap/tooltip")
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

observer.once('[data-update-url]', () => import("./update.fragment"));
