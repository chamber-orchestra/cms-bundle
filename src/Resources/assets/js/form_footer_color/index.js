const checkbox = document.querySelector('.js-footer-color-checkbox');
const colorInput = document.querySelector('.js-footer-color-field');

function toggleColorField() {
    if (checkbox.checked) {
        //colorInput.setAttribute('disabled', 'true');
        colorInput.value = '#EEECF4';
    } else {
        //colorInput.removeAttribute('disabled');
        colorInput.value = '#FFFFFF';
    }
}

checkbox.addEventListener('change', toggleColorField);
// toggleColorField();
