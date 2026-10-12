import { Controller } from '@hotwired/stimulus';

/*
 * Menu mobile plein écran. Le <dialog> natif ouvert avec showModal() fournit le piège de focus,
 * la fermeture par Échap et l'inertie du reste de la page.
 */
export default class extends Controller {
    static targets = ['trigger', 'dialog'];

    connect() {
        // Le menu mobile n'existe plus au-delà de lg (64rem) : on le ferme si la fenêtre s'élargit.
        this.desktopQuery = window.matchMedia('(min-width: 64rem)');
        this.onBreakpointChange = (event) => {
            if (event.matches) {
                this.close();
            }
        };
        this.desktopQuery.addEventListener('change', this.onBreakpointChange);
    }

    disconnect() {
        this.desktopQuery.removeEventListener('change', this.onBreakpointChange);
    }

    open() {
        this.dialogTarget.showModal();
        this.triggerTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    onClose() {
        this.triggerTarget.setAttribute('aria-expanded', 'false');
        this.triggerTarget.focus();
    }

    toggleSection(event) {
        const button = event.currentTarget;
        const expanded = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', String(!expanded));
        document.getElementById(button.getAttribute('aria-controls')).hidden = expanded;
    }
}
