import { Controller } from '@hotwired/stimulus';

/*
 * Bouton « Imprimer » : ouvre la boîte d'impression du navigateur. Le bouton est un <button> natif
 * (clavier et lecteurs d'écran), et la page se met elle-même en forme pour le papier (variante print:).
 */
export default class extends Controller {
    print() {
        window.print();
    }
}
