import { Controller } from '@hotwired/stimulus';

/*
 * Barre de mise en forme d'un champ de texte : entoure la sélection d'une balise (gras, italique) ou insère
 * un retour à la ligne. Sans JavaScript, les balises restent saisissables à la main.
 */
export default class extends Controller {
    static targets = ['field'];

    wrap({ params: { tag } }) {
        const field = this.fieldTarget;
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? start;
        const selected = field.value.slice(start, end);
        this.replace(start, end, `<${tag}>${selected}</${tag}>`, selected ? null : start + tag.length + 2);
    }

    lineBreak() {
        const field = this.fieldTarget;
        const start = field.selectionStart ?? field.value.length;
        this.replace(start, field.selectionEnd ?? start, '<br>', null);
    }

    replace(start, end, text, caret) {
        const field = this.fieldTarget;
        field.setRangeText(text, start, end, 'end');
        if (null !== caret) {
            field.setSelectionRange(caret, caret);
        }
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
