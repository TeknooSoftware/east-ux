import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async connect() {
        // Get the Live Component instance
        this.component = await getComponent(this.element);

        // Listen for refresh event from form submission
        this.boundRefresh = this.refresh.bind(this);
        document.addEventListener('list-window:refresh', this.boundRefresh);
    }

    disconnect() {
        document.removeEventListener('list-window:refresh', this.boundRefresh);
    }

    refresh() {
        // Trigger Live Component re-render (not full page reload)
        if (this.component) {
            this.component.render();
        }
    }
}
