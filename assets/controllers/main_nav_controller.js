import { Controller } from '@hotwired/stimulus';

/*
 * Menu principal desktop, modèle « disclosure navigation » (WAI-ARIA APG).
 *
 * - Bouton de rubrique : Entrée / Espace ouvre ou ferme le sous-menu (aria-expanded).
 * - ← / → : rubrique précédente / suivante (le sous-menu ouvert se referme).
 * - ↓ / ↑ sur une rubrique : ouvre le sous-menu et place le focus sur le premier / dernier lien.
 * - ↓ / ↑ dans un sous-menu : lien suivant / précédent (en boucle). Début / Fin : premier / dernier.
 * - Échap : ferme le sous-menu et rend le focus à son bouton.
 * - Tab reste naturel ; le sous-menu se ferme quand le focus ou un clic sort de la navigation.
 */
export default class extends Controller {
    static targets = ['top', 'panel'];

    disconnect() {
        this.closeAll();
    }

    toggle(event) {
        const button = event.currentTarget;
        const wasOpen = button.getAttribute('aria-expanded') === 'true';

        this.closeAll();
        if (!wasOpen) {
            this.openPanel(button);
        }
    }

    closeAll() {
        this.openButtons().forEach((button) => this.close(button));
    }

    keydown(event) {
        const current = event.target;
        const tops = this.topTargets;
        const panel = current.closest('[data-main-nav-target="panel"]');
        const topIndex = tops.indexOf(panel ? this.triggerFor(panel) : current);

        if (topIndex < 0) {
            return;
        }

        switch (event.key) {
            case 'Escape': {
                const button = tops[topIndex];
                if (button.getAttribute('aria-expanded') === 'true') {
                    event.preventDefault();
                    this.closeAll();
                    button.focus();
                }
                break;
            }
            case 'ArrowRight':
            case 'ArrowLeft': {
                event.preventDefault();
                const delta = event.key === 'ArrowRight' ? 1 : -1;
                this.closeAll();
                tops[(topIndex + delta + tops.length) % tops.length].focus();
                break;
            }
            case 'ArrowDown':
            case 'ArrowUp': {
                const forward = event.key === 'ArrowDown';
                if (panel) {
                    event.preventDefault();
                    this.moveWithin(this.linksIn(panel), current, forward ? 1 : -1);
                } else if (current.hasAttribute('aria-expanded')) {
                    event.preventDefault();
                    this.closeAll();
                    this.openPanel(current);
                    const links = this.linksIn(this.panelFor(current));
                    links[forward ? 0 : links.length - 1]?.focus();
                }
                break;
            }
            case 'Home':
            case 'End': {
                event.preventDefault();
                const list = panel ? this.linksIn(panel) : tops;
                list[event.key === 'Home' ? 0 : list.length - 1]?.focus();
                break;
            }
        }
    }

    // Un sous-menu se referme dès que le focus quitte à la fois le sous-menu et son bouton.
    focusout(event) {
        const next = event.relatedTarget;

        this.openButtons().forEach((button) => {
            if (!button.contains(next) && !this.panelFor(button).contains(next)) {
                this.close(button);
            }
        });
    }

    clickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.closeAll();
        }
    }

    openButtons() {
        return this.topTargets.filter((element) => element.getAttribute('aria-expanded') === 'true');
    }

    close(button) {
        button.setAttribute('aria-expanded', 'false');
        this.panelFor(button).hidden = true;
    }

    openPanel(button) {
        button.setAttribute('aria-expanded', 'true');
        this.panelFor(button).hidden = false;
    }

    moveWithin(items, current, delta) {
        const index = items.indexOf(current);
        items[(index + delta + items.length) % items.length]?.focus();
    }

    panelFor(button) {
        return document.getElementById(button.getAttribute('aria-controls'));
    }

    triggerFor(panel) {
        return this.element.querySelector(`[aria-controls="${panel.id}"]`);
    }

    linksIn(panel) {
        return Array.from(panel.querySelectorAll('a[href]'));
    }
}
