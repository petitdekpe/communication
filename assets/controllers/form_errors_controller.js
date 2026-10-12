import { Controller } from '@hotwired/stimulus';

/*
 * Place le focus sur le récapitulatif d'erreurs affiché après un envoi refusé : un lecteur d'écran
 * l'annonce, et un utilisateur au clavier peut suivre directement les liens vers les champs.
 */
export default class extends Controller {
    static targets = ['summary'];

    summaryTargetConnected(element) {
        element.focus();
    }
}
