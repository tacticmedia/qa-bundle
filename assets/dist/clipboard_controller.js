import { Controller } from './stimulus.js';

/*
 * Copies the text of another element to the clipboard and shows a confirmation
 * on the button. Where the clipboard API is unavailable, for example on an
 * insecure origin, it hides the button and leaves the text on the page for manual
 * selection.
 *
 * Markup: templates/prompt.html.twig.
 */
export default class extends Controller {
    static targets = ['source', 'button'];

    static values = {
        confirmation: { type: String, default: 'Copied' },
    };

    connect() {
        this.timer = null;

        if (!navigator.clipboard && this.hasButtonTarget) {
            this.buttonTarget.hidden = true;
        }
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    async copy() {
        if (!navigator.clipboard || !this.hasSourceTarget) {
            return;
        }

        await navigator.clipboard.writeText(this.sourceTarget.textContent);

        if (!this.hasButtonTarget) {
            return;
        }

        const label = this.buttonTarget.textContent;
        this.buttonTarget.textContent = this.confirmationValue;

        clearTimeout(this.timer);
        this.timer = setTimeout(() => { this.buttonTarget.textContent = label; }, 2000);
    }
}
