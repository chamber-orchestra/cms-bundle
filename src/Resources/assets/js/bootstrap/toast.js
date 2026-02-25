import Toast from 'bootstrap/js/src/toast';

document.querySelectorAll('.toast').forEach(el => {
    Toast.getOrCreateInstance(el).show();
});
