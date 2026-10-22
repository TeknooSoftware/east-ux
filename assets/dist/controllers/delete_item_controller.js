import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static values = {
        url: String,
        modalId: String,
        csrfToken: String,
        csrfHeader: { type: String, default: 'X-CSRF-Token' },
        csrfField: { type: String, default: '_token' },
    }

    delete(event) {
        event.preventDefault();

        const modalElement = document.getElementById(this.modalIdValue);
        if (!modalElement) {
            return;
        }

        const modal = Modal.getInstance(modalElement) || new Modal(modalElement);

        // The listeners of this confirmation are removed when the modal is hidden, confirmed or cancelled: the confirm
        // button is shared by every row, a cancelled confirmation must not delete its row on the next confirmation
        const abortController = new AbortController();

        modalElement.addEventListener(
          'shown.bs.modal',
          (e) => {
              let confirmButton = e.target.querySelector('[data-confirm-button]');
              confirmButton.addEventListener(
                'click',
                (e) => {
                    e.stopPropagation();
                    this.executeDelete();
                    modal.hide();
                },
                {
                    once: true,
                    signal: abortController.signal
                }
            )
          },
          {
              once: true,
              signal: abortController.signal
          }
        );

        modalElement.addEventListener('hidden.bs.modal', () => abortController.abort(), { once: true });

        modal.show();
    }

    async executeDelete() {
        try {
            // The token is sent in the body field `csrfField`, read by East Common's `CheckCsrfToken` on a route with
            // `csrfTokenId`, and in the header `csrfHeader`, for an application checking the header
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    [this.csrfHeaderValue]: this.csrfTokenValue,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ [this.csrfFieldValue]: this.csrfTokenValue })
            });

            if (response.ok || response.redirected) {
                const liveComponentElement = this.element.closest('[data-controller~="live"]');

                if (liveComponentElement) {
                    const component = await getComponent(liveComponentElement);
                    await component.render();
                }
            }
        } catch (error) {
            console.error('Delete error:', error);
        }
    }
}
