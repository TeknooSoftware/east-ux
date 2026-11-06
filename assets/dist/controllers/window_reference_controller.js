import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/**
 * Listens to the Mercure topic of the reference of a Live window, and runs the methods published there as Live actions.
 * A message is only the name of a method: the window reads its data itself, through its Live re-render.
 *
 * The calls run one at a time. Two Live actions sent together would leave as one `_batch` request, which the East
 * bridge does not run; a burst of the same method runs it once, after the running call.
 */
export default class extends Controller {
    static values = {
        url: String,
    };

    connect() {
        this.methods = new Set();
        this.running = false;

        if (!this.urlValue) {
            return;
        }

        this.eventSource = new EventSource(this.urlValue);
        this.eventSource.onmessage = (event) => this.enqueue(event.data);
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }

        this.methods.clear();
    }

    enqueue(method) {
        this.methods.add(method);
        this.run();
    }

    async run() {
        if (this.running) {
            return;
        }

        this.running = true;

        while (this.methods.size > 0) {
            const [method] = this.methods;
            this.methods.delete(method);
            await this.call(method);
        }

        this.running = false;
    }

    async call(method) {
        // A window closed by the user stays closed: it is rendered again when it is opened
        if (this.element.classList.contains('d-none')) {
            return;
        }

        try {
            // The component is looked for at each call, live may have created a new one
            const component = await getComponent(this.element);
            await component.action(method);
        } catch (error) {
            console.debug('Could not call the method of the window:', error);
        }
    }
}
