import { Controller } from '@hotwired/stimulus';

/*
 * Envoie le formulaire dès qu'un filtre change (le bouton « Filtrer » reste disponible, et suffit
 * sans JavaScript). requestSubmit() déclenche la navigation Turbo du cadre.
 */
export default class extends Controller {
    submit() {
        this.element.requestSubmit();
    }
}
