import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.startLeft = 0;
        this.startTop = 0;
        this.hasBeenDragged = false;

        this.boundMouseMove = this.onMouseMove.bind(this);
        this.boundMouseUp = this.onMouseUp.bind(this);
    }

    disconnect() {
        document.removeEventListener('mousemove', this.boundMouseMove);
        document.removeEventListener('mouseup', this.boundMouseUp);
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
        if (newTop < 0) {
            newTop = 0;
        }
        if (newTop + windowHeight > viewportHeight) {
            newTop = viewportHeight - windowHeight;
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
