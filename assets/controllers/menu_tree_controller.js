import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/*
 * Arbre de menu réordonnable : glisser-déposer (SortableJS) et clavier (boutons Monter, Descendre, Indenter, Désindenter
 * portés par chaque ligne, qui déplacent l'entrée dans le DOM). Chaque changement est enregistré aussitôt (POST JSON) ;
 * le résultat est annoncé dans la région « status ». La profondeur maximale est vérifiée ici et de nouveau côté serveur.
 *
 * Structure attendue : <li data-id="12"> <div data-menu-tree-row>…boutons…</div> <ul data-menu-tree-list>…</ul> </li>
 */
export default class extends Controller {
    static targets = ['status'];
    static values = { url: String, token: String, maxDepth: Number, saved: String, error: String };

    connect() {
        this.queue = Promise.resolve();
        this.sortables = [...this.element.querySelectorAll('[data-menu-tree-list]')].map((list) => Sortable.create(list, {
            group: 'menu',
            handle: '[data-menu-tree-handle]',
            draggable: 'li[data-id]',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onMove: (event) => this.listDepth(event.to) + this.height(event.dragged) <= this.maxDepthValue,
            onEnd: () => {
                this.refresh();
                this.save();
            },
        }));
        this.refresh();
    }

    disconnect() {
        this.sortables.forEach((sortable) => sortable.destroy());
    }

    up(event) {
        const item = this.item(event);
        const previous = item.previousElementSibling;
        if (previous) {
            item.parentElement.insertBefore(item, previous);
            this.moved(item, 'up');
        }
    }

    down(event) {
        const item = this.item(event);
        const next = item.nextElementSibling;
        if (next) {
            item.parentElement.insertBefore(next, item);
            this.moved(item, 'down');
        }
    }

    indent(event) {
        const item = this.item(event);
        const previous = item.previousElementSibling;
        if (previous && this.listDepth(previous.querySelector(':scope > [data-menu-tree-list]')) + this.height(item) <= this.maxDepthValue) {
            previous.querySelector(':scope > [data-menu-tree-list]').append(item);
            this.moved(item, 'indent');
        }
    }

    outdent(event) {
        const item = this.item(event);
        const parent = item.parentElement.closest('li[data-id]');
        if (parent) {
            parent.after(item);
            this.moved(item, 'outdent');
        }
    }

    item(event) {
        return event.currentTarget.closest('li[data-id]');
    }

    moved(item, action) {
        this.refresh();
        this.save();
        // Le bouton utilisé peut être devenu inactif (première position, dernier niveau…) : le focus reste sur la ligne.
        const row = item.querySelector(':scope > [data-menu-tree-row]');
        (row.querySelector(`[data-action$="menu-tree#${action}"]:not(:disabled)`) ?? row.querySelector('a')).focus();
    }

    /** Profondeur d'une liste : 1 pour la liste racine, 2 pour les enfants d'une entrée, etc. */
    listDepth(list) {
        let depth = 1;
        for (let item = list.closest('li[data-id]'); item; item = item.parentElement.closest('li[data-id]')) {
            depth += 1;
        }
        return depth;
    }

    /** Hauteur du sous-arbre d'une entrée (1 = sans enfant). */
    height(item) {
        const children = [...item.querySelectorAll(':scope > [data-menu-tree-list] > li[data-id]')];
        return 1 + Math.max(0, ...children.map((child) => this.height(child)));
    }

    refresh() {
        this.element.querySelectorAll('li[data-id]').forEach((item) => {
            const row = item.querySelector(':scope > [data-menu-tree-row]');
            const previous = item.previousElementSibling;
            const set = (action, disabled) => {
                const button = row.querySelector(`[data-action$="menu-tree#${action}"]`);
                if (button) {
                    button.disabled = disabled;
                }
            };
            set('up', !previous);
            set('down', !item.nextElementSibling);
            set('indent', !previous || this.listDepth(previous.querySelector(':scope > [data-menu-tree-list]')) + this.height(item) > this.maxDepthValue);
            set('outdent', !item.parentElement.closest('li[data-id]'));
        });
    }

    serialize(list) {
        return [...list.children]
            .filter((element) => element.matches('li[data-id]'))
            .map((item) => ({ id: Number(item.dataset.id), children: this.serialize(item.querySelector(':scope > [data-menu-tree-list]')) }));
    }

    save() {
        const body = JSON.stringify({ tree: this.serialize(this.element.querySelector('[data-menu-tree-list]')) });
        // Les enregistrements partent dans l'ordre des déplacements : le dernier état est celui qui reste en base.
        this.queue = this.queue
            .then(() => fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
                body,
                credentials: 'same-origin',
            }))
            .then((response) => this.announce(response.ok ? this.savedValue : this.errorValue))
            .catch(() => this.announce(this.errorValue));
    }

    announce(message) {
        this.statusTarget.textContent = message;
    }
}
