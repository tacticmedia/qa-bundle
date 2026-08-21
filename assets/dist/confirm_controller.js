import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation guard for destructive forms. Replaces inline onsubmit handlers,
 * which the strict CSP blocks (inline event-handler attributes would need
 * 'unsafe-inline'/'unsafe-hashes'; a nonce only whitelists <script> elements).
 *
 * Usage (always via the Twig helpers, never hand-written data-* attributes):
 *   <form {{ stimulus_controller('qa-confirm', {message: 'Delete this thing?'})
 *            |stimulus_action('qa-confirm', 'check', 'submit') }}>
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
