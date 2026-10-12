import { Controller } from '@hotwired/stimulus';

/*
 * Barre latérale repliable de l'administration. Le bouton est un vrai <button aria-expanded> ; replié, la barre ne montre
 * que les icônes (les libellés restent lus par les lecteurs d'écran). L'état est mémorisé dans le navigateur.
 */
export default class extends Controller {
    static targets = ['toggle'];
    static values = { collapsed: Boolean };

    connect() {
        let stored = null;
        try {
            stored = window.localStorage.getItem('admin-sidebar');
        } catch (e) {
            // Stockage indisponible (navigation privée, blocage) : l'état par défaut s'applique.
        }
        if (stored !== null) {
            this.collapsedValue = stored === 'collapsed';
        } else if (window.matchMedia('(max-width: 1023px)').matches) {
            // Petit écran : replié par défaut, pour laisser la place au contenu.
            this.collapsedValue = true;
        }
        this.render();
    }

    toggle() {
        this.collapsedValue = !this.collapsedValue;
        try {
            window.localStorage.setItem('admin-sidebar', this.collapsedValue ? 'collapsed' : 'expanded');
        } catch (e) {
            // Sans stockage, l'état n'est simplement pas mémorisé.
        }
    }

    collapsedValueChanged() {
        this.render();
    }

    render() {
        this.element.dataset.collapsed = this.collapsedValue ? 'true' : 'false';
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', this.collapsedValue ? 'false' : 'true');
        }
    }
}
