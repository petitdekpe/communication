import { Controller } from '@hotwired/stimulus';

/*
 * Notifications (toasts) : se retirent seules après quelques secondes, ou à la demande (bouton Fermer). Les toasts
 * ajoutés par un Turbo Stream sont pris en charge dès leur insertion.
 */
export default class extends Controller {
    static targets = ['toast'];

    toastTargetConnected(element) {
        window.setTimeout(() => element.remove(), 8000);
    }

    dismiss(event) {
        event.currentTarget.closest('[data-toasts-target="toast"]').remove();
    }
}
