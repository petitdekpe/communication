import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation avant une action destructrice, dans un <dialog> natif (focus piégé, Échap = annuler, le focus revient au
 * bouton déclencheur à la fermeture). Le formulaire cible n'est envoyé qu'après « Confirmer ».
 */
export default class extends Controller {
    static targets = ['dialog', 'message', 'cancel'];

    ask(event) {
        event.preventDefault();
        const { form, message } = event.params;
        this.form = document.getElementById(form);
        if (!this.form) {
            return;
        }
        if (message) {
            this.messageTarget.textContent = message;
        }
        this.dialogTarget.showModal();
        this.cancelTarget.focus();
    }

    confirm() {
        const form = this.form;
        this.dialogTarget.close();
        this.form = null;
        if (form) {
            form.requestSubmit();
        }
    }

    cancel() {
        this.form = null;
        this.dialogTarget.close();
    }
}
