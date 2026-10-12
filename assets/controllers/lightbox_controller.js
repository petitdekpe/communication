import { Controller } from '@hotwired/stimulus';

/*
 * Visionneuse d'une galerie : un <dialog> natif (focus piégé, Échap pour fermer, focus rendu au bouton qui l'a ouverte).
 * Flèches gauche/droite et boutons Précédent/Suivant pour naviguer ; le compteur est annoncé (aria-live).
 * Chaque vignette porte data-index, data-full, data-alt, data-caption, data-credit.
 */
export default class extends Controller {
    static targets = ['dialog', 'image', 'caption', 'counter', 'close'];
    static values = { counterTemplate: String };

    connect() {
        this.index = 0;
    }

    get items() {
        return Array.from(this.element.querySelectorAll('[data-action~="lightbox#open"]'));
    }

    open(event) {
        this.index = Number(event.currentTarget.dataset.index);
        this.show();
        this.dialogTarget.showModal();
        this.closeTarget.focus();
    }

    close() {
        this.dialogTarget.close();
    }

    next() {
        this.index = (this.index + 1) % this.items.length;
        this.show();
    }

    previous() {
        this.index = (this.index - 1 + this.items.length) % this.items.length;
        this.show();
    }

    keydown(event) {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.next();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.previous();
        }
    }

    // Clic sur le fond (hors du contenu) : ferme la visionneuse.
    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    show() {
        const item = this.items[this.index];
        if (!item) {
            return;
        }
        this.imageTarget.src = item.dataset.full;
        this.imageTarget.alt = item.dataset.alt || '';
        const caption = [item.dataset.caption, item.dataset.credit].filter(Boolean).join(' · ');
        this.captionTarget.textContent = caption;
        this.counterTarget.textContent = this.counterTemplateValue
            .replace('{current}', String(this.index + 1))
            .replace('{total}', String(this.items.length));
    }
}
