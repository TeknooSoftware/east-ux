import { Controller } from '@hotwired/stimulus';

// The windows stacked by `bringToFront()`: floating ListWindows and FormWindow overlays
const FLOATING_WINDOWS = '.window.window-floating, .window.form-window.form-fixed';
const ORDER_PROPERTY = '--east-ux-window-order';

export default class extends Controller {
    connect() {
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.startLeft = 0;
        this.startTop = 0;
        this.minTop = 0;
        this.hasBeenDragged = false;

        this.boundMouseMove = this.onMouseMove.bind(this);
        this.boundMouseUp = this.onMouseUp.bind(this);

        // A click in the window, the focus of one of its fields or its opening brings it to the front
        this.boundBringToFront = this.bringToFront.bind(this);
        this.element.addEventListener('mousedown', this.boundBringToFront);
        this.element.addEventListener('focusin', this.boundBringToFront);
        this.element.addEventListener('window:open', this.boundBringToFront);
        this.element.addEventListener('form-window:open', this.boundBringToFront);
    }

    disconnect() {
        document.removeEventListener('mousemove', this.boundMouseMove);
        document.removeEventListener('mouseup', this.boundMouseUp);

        this.element.removeEventListener('mousedown', this.boundBringToFront);
        this.element.removeEventListener('focusin', this.boundBringToFront);
        this.element.removeEventListener('window:open', this.boundBringToFront);
        this.element.removeEventListener('form-window:open', this.boundBringToFront);
    }

    // Puts the window above the other shown floating windows of the page: their orders are rewritten from 1 to n,
    // the z-index of east-ux.css follows them. The closed windows (`d-none`) are not counted, so n stays under the
    // cap of the z-index scale; this window is counted even while it is still hidden, on `window:open`
    bringToFront() {
        if (!this.element.matches(FLOATING_WINDOWS)) {
            return;
        }

        const others = Array.from(document.querySelectorAll(FLOATING_WINDOWS))
            .filter((element) => element !== this.element && !element.classList.contains('d-none'))
            .sort((first, second) => this.readOrder(first) - this.readOrder(second));

        const order = this.readOrder(this.element);
        if (0 < order && others.every((element) => this.readOrder(element) < order)) {
            // Already on top
            return;
        }

        others.forEach((element, index) => {
            element.style.setProperty(ORDER_PROPERTY, String(index + 1));
        });
        this.element.style.setProperty(ORDER_PROPERTY, String(others.length + 1));
    }

    readOrder(element) {
        return parseInt(element.style.getPropertyValue(ORDER_PROPERTY), 10) || 0;
    }

    startDrag(event) {
        // Ignore clicks on close/resize buttons
        if (event.target.classList.contains('close') ||
            event.target.classList.contains('resize')) {
            return;
        }

        this.isDragging = true;
        this.startX = event.clientX;
        this.startY = event.clientY;

        // The title bar never goes under the fixed menu bar of the page
        this.minTop = 0;
        const menuBar = document.querySelector('.navbar.fixed-top');
        if (menuBar) {
            this.minTop = menuBar.getBoundingClientRect().bottom;
        }

        // On first drag, convert from centered position to absolute position
        if (!this.hasBeenDragged) {
            const rect = this.element.getBoundingClientRect();
            this.element.style.left = rect.left + 'px';
            this.element.style.top = rect.top + 'px';
            this.element.style.transform = 'none';
            this.hasBeenDragged = true;
        }

        this.startLeft = parseInt(this.element.style.left) || 0;
        this.startTop = parseInt(this.element.style.top) || 0;

        this.element.classList.add('dragging');

        document.addEventListener('mousemove', this.boundMouseMove);
        document.addEventListener('mouseup', this.boundMouseUp);

        event.preventDefault();
    }

    onMouseMove(event) {
        if (!this.isDragging) {
            return;
        }

        const deltaX = event.clientX - this.startX;
        const deltaY = event.clientY - this.startY;

        let newLeft = this.startLeft + deltaX;
        let newTop = this.startTop + deltaY;

        // Constrain to viewport
        const windowWidth = this.element.offsetWidth;
        const windowHeight = this.element.offsetHeight;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        if (newLeft < 0) {
            newLeft = 0;
        }
        if (newLeft + windowWidth > viewportWidth) {
            newLeft = viewportWidth - windowWidth;
        }
        if (newTop + windowHeight > viewportHeight) {
            newTop = viewportHeight - windowHeight;
        }
        // Applied last: the title bar stays reachable, even when the window is higher than the viewport
        if (newTop < this.minTop) {
            newTop = this.minTop;
        }

        this.element.style.left = newLeft + 'px';
        this.element.style.top = newTop + 'px';
    }

    onMouseUp() {
        if (this.isDragging) {
            this.isDragging = false;
            this.element.classList.remove('dragging');

            document.removeEventListener('mousemove', this.boundMouseMove);
            document.removeEventListener('mouseup', this.boundMouseUp);
        }
    }
}
