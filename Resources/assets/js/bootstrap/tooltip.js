import Tooltip from 'bootstrap/js/src/tooltip';

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new Tooltip(el, {
        container: 'body',
        boundary: 'viewport'
    });
});
