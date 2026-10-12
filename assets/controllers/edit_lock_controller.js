import { Controller } from '@hotwired/stimulus';

/*
 * Verrou d'édition. Tant que l'utilisateur écrit, un signal est envoyé au serveur (une fois par minute au plus) pour
 * prolonger son verrou, qui expire après 15 minutes sans activité. Si quelqu'un d'autre a pris la main, l'avertissement
 * apparaît avec son nom. Le verrou est libéré quand l'utilisateur quitte la page (le rechargement après un enregistrement le reprend aussitôt).
 */
export default class extends Controller {
    static targets = ['warning', 'message'];
    static values = { touchUrl: String, releaseUrl: String, token: String, interval: { type: Number, default: 60000 } };

    connect() {
        this.active = false;
        this.onActivity = () => {
            this.active = true;
        };
        this.onLeave = () => this.release();
        ['input', 'keydown', 'change', 'pointerdown'].forEach((name) => document.addEventListener(name, this.onActivity, { passive: true }));
        window.addEventListener('pagehide', this.onLeave);
        document.addEventListener('turbo:before-visit', this.onLeave);
        this.timer = window.setInterval(() => this.ping(), this.intervalValue);
    }

    disconnect() {
        window.clearInterval(this.timer);
        ['input', 'keydown', 'change', 'pointerdown'].forEach((name) => document.removeEventListener(name, this.onActivity));
        window.removeEventListener('pagehide', this.onLeave);
        document.removeEventListener('turbo:before-visit', this.onLeave);
    }

    async ping() {
        if (!this.active) {
            return;
        }
        this.active = false;
        try {
            const response = await fetch(this.touchUrlValue, { method: 'POST', body: this.body(), credentials: 'same-origin' });
            if (!response.ok) {
                return;
            }
            const { holder } = await response.json();
            this.show(holder);
        } catch (error) {
            // Réseau indisponible : le prochain signal réessaiera.
        }
    }

    show(holder) {
        if (!this.hasWarningTarget) {
            return;
        }
        if (holder) {
            this.messageTarget.textContent = this.warningTarget.dataset.template.replace('%name%', holder);
            this.warningTarget.hidden = false;
        } else {
            this.warningTarget.hidden = true;
        }
    }

    release() {
        if (this.released) {
            return;
        }
        this.released = true;
        navigator.sendBeacon(this.releaseUrlValue, this.body());
    }

    body() {
        const data = new FormData();
        data.append('_token', this.tokenValue);

        return data;
    }
}
