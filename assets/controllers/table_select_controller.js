import { Controller } from '@hotwired/stimulus';

/*
 * Sélection multiple d'un tableau : case « tout sélectionner » (état indéterminé si sélection partielle), compteur
 * annoncé aux lecteurs d'écran, bouton d'action groupée activé seulement quand au moins une ligne est cochée.
 */
export default class extends Controller {
    static targets = ['all', 'row', 'count', 'submit'];

    connect() {
        this.update();
    }

    toggleAll() {
        this.rowTargets.forEach((row) => {
            row.checked = this.allTarget.checked;
        });
        this.update();
    }

    update() {
        const checked = this.rowTargets.filter((row) => row.checked).length;
        if (this.hasAllTarget) {
            this.allTarget.checked = checked > 0 && checked === this.rowTargets.length;
            this.allTarget.indeterminate = checked > 0 && checked < this.rowTargets.length;
        }
        if (this.hasCountTarget) {
            this.countTarget.textContent = this.countTarget.dataset.template
                ? this.countTarget.dataset.template.replace('{count}', String(checked))
                : `${checked} sélectionné(s)`;
        }
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = checked === 0;
        }
    }
}
