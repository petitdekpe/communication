import { Controller } from '@hotwired/stimulus';

/*
 * Avis officiel fermable. La fermeture est mémorisée dans le localStorage du visiteur
 * (clé « mcm:avis:{id} ») ; si le stockage est indisponible (navigation privée, stockage bloqué),
 * l'avis se ferme simplement pour la page courante.
 */
export default class extends Controller {
    static values = { id: String };

    connect() {
        if (this.read() === 'ferme') {
            this.element.hidden = true;
        }
    }

    dismiss() {
        this.write('ferme');
        this.element.hidden = true;

        // Le bouton disparaît avec l'avis : le focus est confié au contenu principal plutôt que perdu.
        document.getElementById('contenu')?.focus();
    }

    get storageKey() {
        return `mcm:avis:${this.idValue}`;
    }

    read() {
        try {
            return window.localStorage.getItem(this.storageKey);
        } catch {
            return null;
        }
    }

    write(value) {
        try {
            window.localStorage.setItem(this.storageKey, value);
        } catch {
            // Stockage indisponible : fermeture non mémorisée.
        }
    }
}
