import { Controller } from '@hotwired/stimulus';

// The shown floating windows of the page: floating ListWindows and FormWindow overlays
const SHOWN_FLOATING_WINDOWS = '.window.window-floating:not(.d-none), .window.form-window.form-fixed:not(.d-none)';
// The cascade: each new window is shifted by one step more than the previous one, back to 0 after the last step
const CASCADE_STEPS = 6;
const CASCADE_STEP_REM = 1.5;

export default class extends Controller {
    static values = {
        url: String, // URL of the component renderer (route _teknoo_east_ux_component_render)
        component: String,
        title: String,
        props: { type: Object, default: {} },
    }

    initialize() {
        this.loading = false;
    }

    async open(event) {
        // The middle button or a modifier key: the browser opens the link (new tab, new window, ...)
        if (0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        if (!this.element.href) {
            console.error('The window-dynamic-loader controller must be set on a link with an href');

            return;
        }

        event.preventDefault();

        // A second click while the window is loading is ignored
        if (this.loading) {
            return;
        }

        this.loading = true;

        try {
            // Read in the `try`: a malformed props value falls back to the navigation
            const link = new URL(this.element.href);
            const props = { ...this.propsValue, originalPath: link.pathname + link.search, hidden: true };
            if (this.titleValue) {
                props.title = this.titleValue;
            }

            // The window is already in the page (loaded before, or the window of the page of the link): a Live window
            // is reused, it opens itself on `window:open`; a window that is not live can not be opened again, the
            // link is followed
            if (props.windowId) {
                const existing = document.querySelector(`[data-form-window="${CSS.escape(props.windowId)}"]`);
                if (existing) {
                    if (existing.matches('[data-controller~="live"]')) {
                        existing.dispatchEvent(new CustomEvent('window:open'));
                    } else {
                        window.location.assign(this.element.href);
                    }

                    return;
                }
            }

            const container = document.getElementById('dynamic-modals');
            if (!this.urlValue || !container) {
                throw new Error('The url value of the window-dynamic-loader controller or #dynamic-modals is missing');
            }

            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    component: this.componentValue,
                    props: props
                })
            });

            // A redirection followed by `fetch` (like the login page of an expired session) is a failure too
            if (!response.ok || response.redirected) {
                throw new Error(`Failed to load the window: ${response.status} ${response.statusText}`);
            }

            const html = await response.text();
            const previous = container.lastElementChild;
            container.insertAdjacentHTML('beforeend', html);

            const windowElement = this.findInsertedWindow(container, previous);
            if (!windowElement) {
                throw new Error('The loaded window is not found in #dynamic-modals');
            }

            // Wait for MutationObserver to process and Stimulus to connect the controllers (the window's and `live`)
            // Use requestAnimationFrame + setTimeout to ensure we're after the next render cycle
            await new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0)));

            // The cascade: the new window, still hidden, is shifted by the number of the windows already shown. Set
            // after the connection, so the Live window keeps it across its renders
            const shown = document.querySelectorAll(SHOWN_FLOATING_WINDOWS).length;
            windowElement.style.setProperty(
                '--east-ux-window-offset',
                `${(shown % CASCADE_STEPS) * CASCADE_STEP_REM}rem`
            );

            // The Live window opens itself: its re-render runs the recipe of `originalPath`
            windowElement.dispatchEvent(new CustomEvent('window:open'));
        } catch (error) {
            // Fallback: the page of the link
            console.error('Error loading the window:', error);
            window.location.assign(this.element.href);
        } finally {
            this.loading = false;
        }
    }

    // The window appended last to the container: its root element, or the first window inside it. Nothing when the
    // returned HTML holds no element (the last element is still the one before the insertion)
    findInsertedWindow(container, previous) {
        const inserted = container.lastElementChild;
        if (!inserted || inserted === previous) {
            return null;
        }

        if (inserted.matches('[data-form-window]')) {
            return inserted;
        }

        return inserted.querySelector('[data-form-window]');
    }
}
