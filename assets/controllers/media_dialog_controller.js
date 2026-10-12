import { Controller } from '@hotwired/stimulus';

/*
 * Sélecteur de médias : fenêtre (<dialog>) qui liste les images de la médiathèque, avec recherche, pagination et envoi
 * d'une nouvelle image. Le choix est écrit dans le champ cible (identifiant du média) et annoncé au Live Component par
 * les événements input et change. Le focus revient au bouton qui a ouvert la fenêtre.
 */
export default class extends Controller {
    static targets = ['dialog', 'search', 'results', 'uploadForm', 'error'];
    static values = { pickerUrl: String, uploadUrl: String };

    open(event) {
        this.targetId = event.params.target;
        this.opener = event.currentTarget;
        this.errorTarget.hidden = true;
        this.dialogTarget.showModal();
        this.load(1, false);
        this.searchTarget.focus();
    }

    close() {
        this.dialogTarget.close();
        this.opener?.focus();
    }

    search(event) {
        event.preventDefault();
        this.load(1, false);
    }

    more(event) {
        event.currentTarget.closest('p')?.remove();
        this.load(event.params.page, true);
    }

    async load(page, append) {
        const url = new URL(this.pickerUrlValue, window.location.origin);
        url.searchParams.set('q', this.searchTarget.value);
        url.searchParams.set('page', String(page));
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
        if (!response.ok) {
            return;
        }
        const html = await response.text();
        if (append) {
            const holder = document.createElement('div');
            holder.innerHTML = html;
            this.resultsTarget.querySelector('ul')?.append(...holder.querySelectorAll('ul > li'));
            const next = holder.querySelector(':scope > p');
            if (next) {
                this.resultsTarget.append(next);
            }
        } else {
            this.resultsTarget.innerHTML = html;
        }
    }

    choose(event) {
        this.write(event.params.id, event.params.name, event.params.thumb);
        this.close();
    }

    clear(event) {
        this.targetId = event.params.target;
        this.write('', '', '');
    }

    /*
     * Écrit l'identifiant dans le champ cible. Si la page prévoit un aperçu (#<cible>-apercu, une <img>) et un nom
     * (#<cible>-nom), ils sont mis à jour ; l'aperçu est masqué quand le choix est retiré.
     */
    write(value, name = '', thumb = '') {
        const input = document.getElementById(this.targetId);
        if (!input) {
            return;
        }
        const label = document.getElementById(`${this.targetId}-nom`);
        if (label) {
            label.textContent = name || label.dataset.empty || '';
        }
        const preview = document.getElementById(`${this.targetId}-apercu`);
        if (preview) {
            preview.hidden = !thumb;
            preview.src = thumb || '';
        }
        document.getElementById(`${this.targetId}-retirer`)?.toggleAttribute('hidden', '' === String(value));
        input.value = String(value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    async upload(event) {
        event.preventDefault();
        this.errorTarget.hidden = true;
        const response = await fetch(this.uploadUrlValue, { method: 'POST', body: new FormData(this.uploadFormTarget), credentials: 'same-origin' });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            this.errorTarget.textContent = data.error || '';
            this.errorTarget.hidden = false;
            return;
        }
        this.uploadFormTarget.reset();
        this.write(data.id, data.name, data.thumb || '');
        this.close();
    }
}
