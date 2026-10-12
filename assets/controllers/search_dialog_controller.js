import { Controller } from '@hotwired/stimulus';

/*
 * Modale de recherche (<dialog> natif : piège de focus, Échap et arrière-plan inerte fournis par le navigateur).
 * Ouverture : bouton « Rechercher », lien d'évitement « Aller à la recherche », Ctrl+K (Cmd+K sur macOS).
 * À la fermeture, le focus revient à l'élément qui l'a ouverte.
 */
export default class extends Controller {
    static targets = ['dialog', 'input', 'shortcutHint'];

    connect() {
        if (/Mac|iPhone|iPad/.test(navigator.userAgent)) {
            this.shortcutHintTargets.forEach((element) => {
                element.textContent = '⌘ K';
            });
        }
    }

    open() {
        if (this.dialogTarget.open) {
            return;
        }

        this.returnFocusTo = document.activeElement;
        this.dialogTarget.showModal();
        this.inputTarget.focus();
    }

    close() {
        if (this.hasDialogTarget && this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    onClose() {
        this.returnFocusTo?.focus();
        this.returnFocusTo = null;
    }

    shortcut(event) {
        const isShortcut = (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'k';
        if (!isShortcut) {
            return;
        }

        event.preventDefault();
        if (this.dialogTarget.open) {
            this.close();
        } else {
            this.open();
        }
    }

    // Un clic sur l'arrière-plan (hors du contenu) a pour cible le <dialog> lui-même.
    backdropClick(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
