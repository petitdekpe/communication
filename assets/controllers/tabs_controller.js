import { Controller } from '@hotwired/stimulus';

/*
 * Onglets (motif WAI-ARIA « tabs ») : flèches gauche/droite, Début et Fin déplacent la sélection ; un seul onglet est
 * dans l'ordre de tabulation. Un lien d'ancre vers un champ situé dans un panneau masqué (récapitulatif d'erreurs)
 * ouvre d'abord le bon onglet.
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        this.onAnchor = (event) => {
            const link = event.target.closest('a[href^="#"]');
            if (!link || link.getAttribute('href').length < 2) {
                return;
            }
            const target = document.getElementById(link.getAttribute('href').slice(1));
            const panel = target && target.closest('[role="tabpanel"]');
            if (panel && this.panelTargets.includes(panel)) {
                this.show(panel.dataset.locale);
            }
        };
        document.addEventListener('click', this.onAnchor);
    }

    disconnect() {
        document.removeEventListener('click', this.onAnchor);
    }

    select(event) {
        this.show(event.currentTarget.dataset.locale);
    }

    keydown(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);
        let next = null;
        if (event.key === 'ArrowRight') {
            next = (index + 1) % this.tabTargets.length;
        } else if (event.key === 'ArrowLeft') {
            next = (index - 1 + this.tabTargets.length) % this.tabTargets.length;
        } else if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = this.tabTargets.length - 1;
        }
        if (next !== null) {
            event.preventDefault();
            this.show(this.tabTargets[next].dataset.locale, true);
        }
    }

    show(locale, focus = false) {
        this.tabTargets.forEach((tab) => {
            const selected = tab.dataset.locale === locale;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) {
                tab.focus();
            }
        });
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.locale !== locale;
        });
    }
}
