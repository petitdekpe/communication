import { Controller } from '@hotwired/stimulus';

/*
 * Vidéo YouTube chargée au clic, en mode « youtube-nocookie » : tant que l'internaute n'a pas cliqué,
 * aucune requête ni cookie vers YouTube. L'identifiant est contrôlé (11 caractères) avant de bâtir l'adresse.
 */
export default class extends Controller {
    static targets = ['stage', 'button'];
    static values = { id: String, title: String };

    play() {
        if (!/^[A-Za-z0-9_-]{11}$/.test(this.idValue)) {
            return;
        }
        const iframe = document.createElement('iframe');
        iframe.src = `https://www.youtube-nocookie.com/embed/${this.idValue}?autoplay=1&rel=0`;
        iframe.title = this.titleValue;
        iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allowFullscreen = true;
        iframe.className = 'size-full';
        this.stageTarget.replaceChildren(iframe);
        iframe.focus();
    }
}
