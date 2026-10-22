import { Controller } from '@hotwired/stimulus';

/*
 * Clipboard controller for copying text to clipboard
 * Uses native Clipboard API
 */
export default class extends Controller {
    static values = {
        text: String,
        successText: { type: String, default: '✓ Copied!' },
        errorText: { type: String, default: '✗ Error' },
        buttonClass: { type: String, default: 'btn-primary' },
        successClass: { type: String, default: 'btn-success' },
        errorClass: { type: String, default: 'btn-danger' },
    }

    async copy(event) {
        event.preventDefault();

        // Read the button before any await: the event's currentTarget is reset once its dispatch is over
        const button = event.currentTarget;
        const originalText = button.textContent;

        try {
            await navigator.clipboard.writeText(this.textValue);

            // Visual feedback
            button.classList.remove(...this.classNames(this.buttonClassValue));
            button.classList.add(...this.classNames(this.successClassValue));
            button.textContent = this.successTextValue;

            // Reset after 2 seconds
            setTimeout(() => {
                button.classList.remove(...this.classNames(this.successClassValue));
                button.classList.add(...this.classNames(this.buttonClassValue));
                button.textContent = originalText;
            }, 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);

            // Show error feedback
            button.classList.remove(...this.classNames(this.buttonClassValue));
            button.classList.add(...this.classNames(this.errorClassValue));
            button.textContent = this.errorTextValue;

            setTimeout(() => {
                button.classList.remove(...this.classNames(this.errorClassValue));
                button.classList.add(...this.classNames(this.buttonClassValue));
                button.textContent = originalText;
            }, 2000);
        }
    }

    // A class value may hold several space-separated classes, or none
    classNames(value) {
        return value.split(/\s+/).filter(Boolean);
    }
}
