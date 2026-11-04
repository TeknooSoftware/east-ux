import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

// The shown floating windows of the page: floating ListWindows and FormWindow overlays. The same selector as
// `window-dynamic-loader` (its cascade): change the two together. The window of a page (not floating, or not both
// closable and draggable) is not counted
const SHOWN_FLOATING_WINDOWS = '.window.window-floating:not(.d-none), .window.form-window.form-fixed:not(.d-none)';

export default class extends Controller {
    static values = {
        modalId: String,
    }

    // Bound to `click` on the container of the links (the <nav> of MenuBar): it runs after the actions of the link
    confirm(event) {
        // Already handled: an entry opening a window (`window-dynamic-loader`), a toggle of Bootstrap (its delegated
        // handlers run on `document` in the capture phase), a link whose own action prevents the navigation
        if (event.defaultPrevented) {
            return;
        }

        // The middle button or a modifier key: the browser opens the link (new tab, new window, ...), the page stays
        if (0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        // The target may be an element inside the link (its icon)
        const link = event.target.closest('a[href]');
        if (!link) {
            return;
        }

        // The page stays: a link opened in another browsing context, a download, an anchor of the page
        if ((link.target && '_self' !== link.target) || link.hasAttribute('download')) {
            return;
        }

        if (link.getAttribute('href').startsWith('#')) {
            return;
        }

        // No window to lose
        if (!document.querySelector(SHOWN_FLOATING_WINDOWS)) {
            return;
        }

        // Without its modal, or without its confirm button, the link is followed: never a dead link
        const modalElement = document.getElementById(this.modalIdValue);
        const confirmButton = modalElement?.querySelector('[data-confirm-button]');
        if (!confirmButton) {
            return;
        }

        event.preventDefault();

        const href = link.href;
        const modal = Modal.getInstance(modalElement) || new Modal(modalElement);

        // The listeners of this confirmation are removed when the modal is hidden, confirmed or cancelled: a cancelled
        // confirmation must not follow its link on the next confirmation
        const abortController = new AbortController();
        let confirmed = false;

        confirmButton.addEventListener(
            'click',
            () => {
                confirmed = true;
                modal.hide();
            },
            {
                once: true,
                signal: abortController.signal
            }
        );

        // The link is followed once the modal is hidden: a page restored from the back-forward cache shows no modal
        modalElement.addEventListener(
            'hidden.bs.modal',
            () => {
                abortController.abort();

                if (confirmed) {
                    window.location.assign(href);
                }
            },
            { once: true }
        );

        modal.show();
    }
}
