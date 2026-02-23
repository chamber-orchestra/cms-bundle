const checkbox = document.querySelector('.js-reset-checkbox');
const colorInput = document.querySelector('.js-color-field');

function toggleColorField() {
    if (checkbox.checked) {
        colorInput.setAttribute('disabled', 'true');
    } else {
        colorInput.removeAttribute('disabled');
    }
}

checkbox.addEventListener('change', toggleColorField);
toggleColorField();