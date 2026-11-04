import { Controller } from '@hotwired/stimulus';
import { beginWait, endWaits } from '../wait_cursor.js';

export default class extends Controller {
    static values = {
        url: String, // URL of the component renderer (route _teknoo_east_ux_component_render)
        windowId: String,
        formPath: String,
        formName: String,
        title: String,
        closable: { type: Boolean, default: true },
        draggable: { type: Boolean, default: true },
        closeAriaLabel: { type: String, default: 'Close' },
        objectId: String, // Optional, for editing
        loadErrorText: { type: String, default: 'An error occurred while loading the form.' },
    }

    connect() {
        // A page left during an opening does not keep the wait cursor in the back-forward cache. One listener per
        // controller: a listener shared by all of them would be removed by the first disconnected one
        this.boundEndWaits = () => endWaits();
        window.addEventListener('pagehide', this.boundEndWaits);
    }

    disconnect() {
        window.removeEventListener('pagehide', this.boundEndWaits);
    }

    async open(event) {
        event.preventDefault();

        // Check if the FormWindow already exists
        let windowElement = document.querySelector(`[data-form-window="${this.windowIdValue}"]`);

        if (windowElement) {
            // FormWindow exists, refresh and open it
            await this.refreshAndOpen(windowElement);
        } else {
            // FormWindow doesn't exist, load it dynamically
            await this.loadAndOpen();
        }
    }

    async refreshAndOpen(windowElement) {
        // The wait cursor, until the window is shown
        const endWait = beginWait();

        // Open the window: `form-window` dispatches `window:opened` when it is shown
        windowElement.addEventListener('window:opened', endWait, { once: true });
        windowElement.dispatchEvent(new CustomEvent('form-window:open'));
    }

    async loadAndOpen() {
        // The wait cursor, until the window is shown; ended before a failure is reported
        const endWait = beginWait();

        try {
            if (!this.urlValue) {
                throw new Error('The url value of the form-window-dynamic-loader controller is missing');
            }

            // Build props for the LiveComponent
            const props = {
                windowId: this.windowIdValue,
                formPath: this.formPathValue,
                originalPath: this.formPathValue,
                formName: this.formNameValue,
                title: this.titleValue,
                closable: this.closableValue,
                draggable: this.draggableValue,
                closeAriaLabel: this.closeAriaLabelValue,
                hidden: true
            };

            // Add objectId if present (for editing)
            if (this.hasObjectIdValue) {
                props.objectId = this.objectIdValue;
            }

            // Call component renderer endpoint
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    component: 'FormWindow',
                    props: props
                })
            });

            if (!response.ok) {
                throw new Error(`Failed to load FormWindow: ${response.statusText}`);
            }

            const html = await response.text();

            // Inject into dynamic container
            const container = document.getElementById('dynamic-modals');
            if (!container) {
                console.error('Container #dynamic-modals not found in DOM');
                endWait();
                return;
            }

            container.insertAdjacentHTML('beforeend', html);

            // Get the window element immediately
            const windowElement = document.querySelector(`[data-form-window="${this.windowIdValue}"]`);
            if (!windowElement) {
                console.error(`FormWindow with id ${this.windowIdValue} not found in DOM`);
                endWait();
                return;
            }

            // Wait for MutationObserver to process and Stimulus to connect the controller
            // Use requestAnimationFrame + setTimeout to ensure we're after the next render cycle
            await new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0)));

            // Dispatch open event - form-window controller should now be connected; it dispatches `window:opened`
            // when the window is shown
            windowElement.addEventListener('window:opened', endWait, { once: true });
            windowElement.dispatchEvent(new CustomEvent('form-window:open'));
        } catch (error) {
            console.error('Error loading FormWindow:', error);
            // No wait cursor over the message
            endWait();
            alert(this.loadErrorTextValue);
        }
    }
}
