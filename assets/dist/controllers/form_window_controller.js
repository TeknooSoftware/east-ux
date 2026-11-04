import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    static targets = ['form', 'errors', 'submitButton'];

    static values = {
        errorText: { type: String, default: 'An error occurred while saving.' },
        networkErrorText: { type: String, default: 'A network error occurred.' },
    }

    initialize() {
        // A window rendered visible (in a page) has been shown; a hidden one (loaded by the component renderer) is
        // shown by its first opening
        this.shown = !this.element.classList.contains('d-none');
    }

    connect() {
        // Listen for custom open events: `form-window:open` (form-window-dynamic-loader) and `window:open`
        // (window-dynamic-loader or the application)
        this.boundOpen = this.open.bind(this);
        this.element.addEventListener('form-window:open', this.boundOpen);
        this.element.addEventListener('window:open', this.boundOpen);

        // A Live re-render morphs the root element without re-applying the changes made by JavaScript: its `style`
        // (position after a drag, cascade offset, stacking order) is kept across the renders. The hooks are
        // registered now, so the first render, on opening, keeps the offset set by window-dynamic-loader before
        // `window:open`
        this.boundKeepStyle = this.keepStyle.bind(this);
        this.boundRestoreStyle = this.restoreStyle.bind(this);
        getComponent(this.element).then((component) => {
            this.component = component;
            component.on('render:started', this.boundKeepStyle);
            component.on('render:finished', this.boundRestoreStyle);
        }).catch((error) => {
            console.debug('Could not find the Live Component of the FormWindow:', error);
        });
    }

    disconnect() {
        this.element.removeEventListener('form-window:open', this.boundOpen);
        this.element.removeEventListener('window:open', this.boundOpen);

        if (this.component) {
            this.component.off('render:started', this.boundKeepStyle);
            this.component.off('render:finished', this.boundRestoreStyle);
            this.component = null;
        }
    }

    // Hook `render:started`, before the morph
    keepStyle() {
        this.keptStyle = this.element.getAttribute('style');
    }

    // Hook `render:finished`, after the morph
    restoreStyle() {
        if (null === this.keptStyle) {
            this.element.removeAttribute('style');

            return;
        }

        this.element.setAttribute('style', this.keptStyle);
    }

    async open(event) {
        if (event) {
            event.preventDefault();
        }

        try {
            const component = await getComponent(this.element);
            const backendResponse = await component.render();

            if (!this.shown && (!backendResponse.response.ok || backendResponse.response.redirected)) {
                // The form of a window never shown could not be loaded (an error, or a redirection followed by
                // `fetch`, like the login page of an expired session): the page of the form is the fallback
                window.location.assign(component.getData('originalPath'));

                return;
            }

            this.element.classList.remove('d-none');
            this.shown = true;
            this.hideErrors();
            // Shown: the end of the wait cursor of the loaders (not bubbling, as `window:open`)
            this.element.dispatchEvent(new CustomEvent('window:opened'));
        } catch (error) {
            // Fallback: just show the current element without refresh
            console.debug('Could not refresh FormWindow, showing as-is:', error);
            this.element.classList.remove('d-none');
            this.shown = true;
            this.hideErrors();
            this.element.dispatchEvent(new CustomEvent('window:opened'));
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

            if (response.redirected) {
                // Success (East Common redirects after a creation) - close window and refresh list
                this.close();
                this.dispatchRefreshEvent();
            } else if (response.ok) {
                // East Common answers 200 with the page after an edition and when it refuses the form: the form of
                // the returned page tells them apart
                const returnedForm = this.findReturnedForm(await response.text(), form);

                if (returnedForm && this.isRefused(returnedForm)) {
                    // Refused - show the returned form with its errors, keep the window open
                    this.showRefusedForm(form, returnedForm);
                } else {
                    // Success - close window and refresh list
                    this.close();
                    this.dispatchRefreshEvent();
                }
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

    // The form of the returned page: the one named as the sent form, else the one posted to the same action
    findReturnedForm(html, form) {
        const page = new DOMParser().parseFromString(html, 'text/html');

        const name = form.getAttribute('name');
        if (name) {
            const namedForm = page.querySelector(`form[name="${CSS.escape(name)}"]`);
            if (namedForm) {
                return namedForm;
            }
        }

        const action = form.getAttribute('action');
        if (action) {
            return page.querySelector(`form[action="${CSS.escape(action)}"]`);
        }

        return null;
    }

    // Marked by FormWindow.html.twig when submitted and invalid, or a field marked by the form theme
    isRefused(returnedForm) {
        return returnedForm.hasAttribute('data-form-window-invalid')
            || returnedForm.querySelector('[aria-invalid="true"]') !== null;
    }

    showRefusedForm(form, returnedForm) {
        // The `form` element keeps its attributes; its content (fields, errors, tokens, targets) is the server's
        form.innerHTML = returnedForm.innerHTML;
        form.querySelector('[aria-invalid="true"]')?.focus();
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
