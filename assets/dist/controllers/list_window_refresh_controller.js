import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    initialize() {
        // A window rendered visible (in a page) has been shown; a hidden one (loaded by the component renderer) is
        // shown by its first opening
        this.shown = !this.element.classList.contains('d-none');
    }

    connect() {
        // Listeners first, before any await: an event fired while the component is looked for is not lost
        this.boundRefresh = this.refresh.bind(this);
        this.boundOpen = this.open.bind(this);
        // Listen for refresh event from form submission
        document.addEventListener('list-window:refresh', this.boundRefresh);
        // Listen for the opening of the window (dispatched by window-dynamic-loader or the application)
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
            console.debug('Could not find the Live Component of the ListWindow:', error);
        });
    }

    disconnect() {
        document.removeEventListener('list-window:refresh', this.boundRefresh);
        this.element.removeEventListener('window:open', this.boundOpen);

        if (this.component) {
            this.component.off('render:started', this.boundKeepStyle);
            this.component.off('render:finished', this.boundRestoreStyle);
            this.component = null;
        }
    }

    async refresh() {
        // A window closed by the user stays closed: it is rendered again when it is opened
        if (this.element.classList.contains('d-none')) {
            return;
        }

        try {
            // Trigger Live Component re-render (not full page reload)
            const component = await getComponent(this.element);
            await component.render();
        } catch (error) {
            console.debug('Could not refresh the ListWindow:', error);
        }
    }

    async open() {
        try {
            const component = await getComponent(this.element);

            // `hidden` is a writable LiveProp: the window is rendered shown by this render and the next ones. One
            // request: `set()` without re-render, then `render()`
            component.set('hidden', false);
            const backendResponse = await component.render();

            if (!this.shown && (!backendResponse.response.ok || backendResponse.response.redirected)) {
                // The content of a window never shown could not be loaded (an error, or a redirection followed by
                // `fetch`, like the login page of an expired session): its page is the fallback
                window.location.assign(component.getData('originalPath'));

                return;
            }
        } catch (error) {
            // Fallback: just show the current element without refresh
            console.debug('Could not refresh the ListWindow, showing as-is:', error);
        }

        this.element.classList.remove('d-none');
        this.shown = true;
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
}
