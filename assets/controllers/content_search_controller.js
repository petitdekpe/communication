import { Controller } from '@hotwired/stimulus';

/*
 * Recherche d'un contenu (page, actualité, démarche) à lier : saisie, résultats sous forme de boutons (atteignables au
 * clavier), choix = identifiant « type:id » déposé dans le champ caché. Les textes viennent du serveur (data-*-value) ;
 * les résultats sont insérés avec textContent, jamais en HTML.
 */
export default class extends Controller {
    static targets = ['input', 'results', 'value', 'selected', 'status'];
    static values = { url: String, empty: String, count: String };

    search() {
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.run(), 250);
    }

    submitGuard(event) {
        // Entrée lance la recherche au lieu d'envoyer le formulaire.
        if (event.key === 'Enter') {
            event.preventDefault();
            this.run();
        } else if (event.key === 'Escape') {
            this.resultsTarget.replaceChildren();
        }
    }

    async run() {
        const query = this.inputTarget.value.trim();
        this.resultsTarget.replaceChildren();
        if (query.length < 2) {
            this.statusTarget.textContent = '';
            return;
        }
        const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) {
            return;
        }
        const found = await response.json();
        this.statusTarget.textContent = found.length === 0 ? this.emptyValue : this.countValue.replace('{count}', String(found.length));
        found.forEach((entry) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'w-full rounded-card px-3 py-2 text-left hover:bg-mist focus-visible:bg-mist';
            button.dataset.action = 'content-search#choose';
            button.dataset.value = `${entry.type}:${entry.id}`;
            button.dataset.label = entry.label;
            const label = document.createElement('span');
            label.className = 'font-semibold text-ink';
            label.textContent = entry.label;
            const detail = document.createElement('span');
            detail.className = 'block text-small text-ink-muted';
            detail.textContent = entry.detail;
            button.append(label, detail);
            item.append(button);
            this.resultsTarget.append(item);
        });
    }

    choose(event) {
        const { value, label } = event.currentTarget.dataset;
        this.valueTarget.value = value;
        this.selectedTarget.textContent = label;
        this.resultsTarget.replaceChildren();
        this.inputTarget.value = '';
        this.inputTarget.focus();
    }
}
