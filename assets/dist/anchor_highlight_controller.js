import { Controller } from '@hotwired/stimulus';

/*
 * Marks the descendant the URL fragment names with data-anchored, so a Tailwind
 * data-anchored: variant can highlight where the page was entered.
 *
 * CSS :target cannot do this here: Turbo arrives by history.pushState, which
 * leaves the document's target element untouched.
 *
 * Usage (always via the Twig helpers, never hand-written data-* attributes):
 *   <div {{ stimulus_controller('qa-anchor-highlight') }}>
 *       <a id="screen-Foo" class="data-anchored:ring-2">...</a>
 *   </div>
 */
export default class extends Controller {
    connect() {
        // A Turbo cache restore brings back whatever was marked last time.
        this.element.querySelectorAll('[data-anchored]')
            .forEach((marked) => marked.removeAttribute('data-anchored'));

        const id = decodeURIComponent(window.location.hash.slice(1));

        if ('' !== id) {
            this.element.querySelector(`#${CSS.escape(id)}`)?.setAttribute('data-anchored', '');
        }
    }
}
