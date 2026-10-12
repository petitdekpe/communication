import { Controller } from '@hotwired/stimulus';

/*
 * Affiche seulement le bloc de champs correspondant au type de lien choisi (aucun, contenu, adresse externe, chemin).
 * Sans JavaScript, tous les blocs restent visibles : le formulaire reste utilisable.
 */
export default class extends Controller {
    static targets = ['section'];

    connect() {
        this.update();
    }

    update() {
        const checked = this.element.querySelector('input[type="radio"][name$="[linkType]"]:checked')?.value;
        this.sectionTargets.forEach((section) => {
            section.hidden = section.dataset.linkType !== checked;
        });
    }
}
