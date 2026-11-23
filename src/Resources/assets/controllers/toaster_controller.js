import { Controller } from '@hotwired/stimulus';
import Toast from 'bootstrap/js/dist/toast';

// The messages the server flashed (success and error) appear as toasts.
export default class extends Controller {
    connect() {
        this.element.querySelectorAll('.toast').forEach((el) => Toast.getOrCreateInstance(el).show());
    }
}
