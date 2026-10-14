import { Controller } from './stimulus.js';

/*
 * Confirmation for destructive forms. It replaces inline onsubmit handlers, which
 * a strict CSP blocks: inline event-handler attributes need 'unsafe-inline' or
 * 'unsafe-hashes', and a nonce permits <script> elements only.
 *
 * Markup: templates/prompt.html.twig.
 *
 * Listening on the form element runs before Turbo's document-level submit
 * handler, so preventDefault() stops both the native and the Turbo submission.
 */
export default class extends Controller {
    static values = {
        message: { type: String, default: 'Are you sure?' },
    };

    check(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
