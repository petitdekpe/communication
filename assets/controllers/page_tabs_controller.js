import { Controller } from '@hotwired/stimulus';

/*
 * Onglets d'un bloc de page (motif WAI-ARIA « tabs ») : un clic ou les flèches gauche/droite, Début et Fin changent
 * d'onglet ; un seul onglet est dans l'ordre de tabulation. Sans JavaScript, tous les panneaux restent visibles.
 */
export default class extends Controller {
    static targets = ['list', 'tab', 'panel', 'heading'];

    connect() {
        this.listTarget.hidden = false;
        this.headingTargets.forEach((heading) => {
            heading.hidden = true;
        });
        this.show(0);
    }

    select(event) {
        this.show(this.tabTargets.indexOf(event.currentTarget));
    }

    keydown(event) {
        const last = this.tabTargets.length - 1;
        const current = this.tabTargets.indexOf(event.currentTarget);
        const next = {
            ArrowRight: current === last ? 0 : current + 1,
            ArrowLeft: current === 0 ? last : current - 1,
            Home: 0,
            End: last,
        }[event.key];
        if (next === undefined) {
            return;
        }
        event.preventDefault();
        this.show(next);
        this.tabTargets[next].focus();
    }

    show(index) {
        this.tabTargets.forEach((tab, i) => {
            tab.setAttribute('aria-selected', String(i === index));
            tab.tabIndex = i === index ? 0 : -1;
        });
        this.panelTargets.forEach((panel, i) => {
            panel.hidden = i !== index;
        });
    }
}
