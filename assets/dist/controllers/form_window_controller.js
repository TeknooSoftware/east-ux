import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    static targets = ['form', 'errors', 'submitButton'];

    static values = {
        errorText: { type: String, default: 'An error occurred while saving.' },
        networkErrorText: { type: String, default: 'A network error occurred.' },
    }

    connect() {
        // Listen for custom open event
        this.element.addEventListener('form-window:open', () => this.open());
    }

    async open(event) {
        if (event) {
            event.preventDefault();
        }

        try {
            const component = await getComponent(this.element);
            await component.render();

            this.element.classList.remove('d-none');
            this.hideErrors();
        } catch (error) {
            // Fallback: just show the current element without refresh
            console.debug('Could not refresh FormWindow, showing as-is:', error);
            this.element.classList.remove('d-none');
            this.hideErrors();
        }
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }
        this.element.classList.add('d-none');

        // Reset all forms in the window
        if (this.hasFormTarget) {
            this.formTargets.forEach(form => form.reset());
        }
        this.hideErrors();
    }

    async submit(event) {
        event.preventDefault();

        if (!this.hasFormTarget) {
            return;
        }

        const form = this.formTarget;
        const formData = new FormData(form);

        // Disable submit button
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });

            if (response.ok || response.redirected) {
                // Success - close window and refresh list
                this.close();
                this.dispatchRefreshEvent();
            } else if (response.status === 422 || response.status === 400) {
                // Validation error - display errors
                const html = await response.text();
                this.displayErrors(html);
            } else {
                // Other error
                this.displayErrors(this.errorTextValue);
            }
        } catch (error) {
            this.displayErrors(this.networkErrorTextValue);
            console.error('Form submission error:', error);
        } finally {
            // Re-enable submit button
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = false;
            }
        }
    }

    displayErrors(message) {
        if (this.hasErrorsTarget) {
            this.errorsTarget.textContent = message;
            this.errorsTarget.classList.remove('d-none');
        }
    }

    hideErrors() {
        if (this.hasErrorsTarget) {
            this.errorsTarget.textContent = '';
            this.errorsTarget.classList.add('d-none');
        }
    }

    dispatchRefreshEvent() {
        // Emit custom event to refresh the list
        document.dispatchEvent(new CustomEvent('list-window:refresh'));
    }
}
