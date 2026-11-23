import { Controller } from '@hotwired/stimulus';

// Submits its form as soon as a field changes (a file is chosen): `<form data-controller="autosubmit"><input type="file" data-action="change->autosubmit#submit">`.
export default class extends Controller {
    submit() {
        this.element.requestSubmit();
    }
}
