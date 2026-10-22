import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        redirectUrl: String
    };

    close(event) {
        if (event) {
            event.preventDefault();
        }

        // If a redirection URL is defined, redirect to it
        if (this.hasRedirectUrlValue && this.redirectUrlValue) {
            window.location.href = this.redirectUrlValue;
        } else {
            // Otherwise, just hide the window
            this.element.classList.add('d-none');
        }
    }
}
