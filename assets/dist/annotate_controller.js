import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

/*
 * Screenshot review: drag a rectangle over the screenshot to bind a note to one
 * area, or click without dragging to note the whole screen. Coordinates are
 * written to the note form in natural image pixels, so a note stays correct at
 * each width the page renders the screenshot at.
 *
 * It also drives the review from the keyboard: left/right move through the group,
 * up/down open the same screen in the adjacent group, Escape cancels or goes back,
 * and Cmd/Ctrl+Enter saves the note being written. Each destination is a value,
 * because the header links are outside this element.
 *
 * Geometry is assigned through the CSSOM: style-src controls a style attribute
 * parsed from markup, not a property write.
 *
 * Usage (always via the Twig helpers, never hand-written data-* attributes):
 *   <div {{ stimulus_controller('qa-annotate', {previousUrl: ..., nextUrl: ..., aboveUrl: ..., belowUrl: ..., backUrl: ...})
 *            |stimulus_action('qa-annotate', 'previous', 'keydown.left@window')
 *            |stimulus_action('qa-annotate', 'next', 'keydown.right@window')
 *            |stimulus_action('qa-annotate', 'above', 'keydown.up@window')
 *            |stimulus_action('qa-annotate', 'below', 'keydown.down@window')
 *            |stimulus_action('qa-annotate', 'cancel', 'keydown.esc@window')
 *            |stimulus_action('qa-annotate', 'submit', 'keydown.meta+enter')
 *            |stimulus_action('qa-annotate', 'submit', 'keydown.ctrl+enter') }}>
 *       <div {{ stimulus_target('qa-annotate', 'stage')
 *                |stimulus_action('qa-annotate', 'start', 'pointerdown')
 *                |stimulus_action('qa-annotate', 'move', 'pointermove')
 *                |stimulus_action('qa-annotate', 'end', 'pointerup') }}>
 *           <img {{ stimulus_target('qa-annotate', 'image') }} src="..." />
 *           <div hidden {{ stimulus_target('qa-annotate', 'selection') }}></div>
 *           <div hidden data-rect-x="10" ... {{ stimulus_target('qa-annotate', 'marker') }}></div>
 *       </div>
 *       <input type="hidden" {{ stimulus_target('qa-annotate', 'x') }} />
 *   </div>
 */
export default class extends Controller {
    static targets = [
        'stage', 'image', 'selection', 'marker', 'note', 'summary',
        'x', 'y', 'width', 'height', 'imageWidth', 'imageHeight',
    ];

    static values = {
        previousUrl: String,
        nextUrl: String,
        aboveUrl: String,
        belowUrl: String,
        backUrl: String,
    };

    // A drag shorter than this on both axes is a click: a whole-screen note.
    static DRAG_THRESHOLD = 4;

    connect() {
        this.origin = null;
        this.observer = new ResizeObserver(() => this.reposition());
        this.observer.observe(this.stageTarget);
        this.observer.observe(this.imageTarget);
    }

    disconnect() {
        this.origin = null;
        this.observer?.disconnect();
        this.observer = null;
    }

    /**
     * Each event that moves or resizes the image must redraw the overlays: the
     * image load, and a stage resize that re-centres or rescales it. Observation
     * of both also covers the first draw, because a ResizeObserver fires once on
     * observe.
     */
    reposition() {
        this.markerTargets.forEach((marker) => this.placeMarker(marker));

        if (this.pending()) {
            this.repaintPending(); // the form was re-rendered after a failed save
        }
    }

    start(event) {
        if (0 !== event.button || !this.ready()) {
            return;
        }

        event.preventDefault(); // suppress the native image drag and text selection

        this.origin = this.pointerPosition(event);
        this.stageTarget.setPointerCapture(event.pointerId);
        this.paint(this.origin, this.origin);
    }

    move(event) {
        if (null === this.origin) {
            return;
        }

        this.paint(this.origin, this.pointerPosition(event));
    }

    end(event) {
        if (null === this.origin) {
            return;
        }

        const area = this.area(this.origin, this.pointerPosition(event));
        const bounds = this.imageTarget.getBoundingClientRect();

        this.origin = null;

        if (this.stageTarget.hasPointerCapture(event.pointerId)) {
            this.stageTarget.releasePointerCapture(event.pointerId);
        }

        const threshold = this.constructor.DRAG_THRESHOLD;

        if (area.width < threshold && area.height < threshold) {
            this.clear();
        } else {
            this.store(area, this.imageTarget.naturalWidth / bounds.width);
        }

        this.noteTarget.focus();
    }

    previous(event) {
        this.step(event, this.previousUrlValue);
    }

    next(event) {
        this.step(event, this.nextUrlValue);
    }

    above(event) {
        this.step(event, this.aboveUrlValue);
    }

    below(event) {
        this.step(event, this.belowUrlValue);
    }

    /**
     * Escape cancels one level at a time: the pending selection, then the focus,
     * then the page.
     */
    cancel(event) {
        if (this.pending()) {
            this.clear(); // end() focuses the note box after a drag, so this must work from there
            return;
        }

        if (this.writing(event)) {
            event.target.blur();
            return;
        }

        this.step(event, this.backUrlValue);
    }

    /**
     * requestSubmit(), never submit(): the stateless screenshot-review token is
     * double-submitted by a listener on the submit event, which submit() skips.
     */
    submit(event) {
        const form = event.target instanceof Element ? event.target.closest('form') : null;

        if (null === form) {
            return;
        }

        event.preventDefault();
        form.requestSubmit();
    }

    /** Arrow keys move the caret while a note is being written; they never navigate. */
    step(event, url) {
        if ('' === url || this.writing(event)) {
            return;
        }

        event.preventDefault(); // the arrows would otherwise scroll the page as it swaps
        visit(url);
    }

    writing(event) {
        return event.target instanceof Element
            && null !== event.target.closest('input, textarea, select, [contenteditable]');
    }

    pending() {
        return this.hasXTarget && '' !== this.xTarget.value;
    }

    clear() {
        this.origin = null;
        this.selectionTarget.hidden = true;
        this.summaryTarget.textContent = 'Whole screen';

        [this.xTarget, this.yTarget, this.widthTarget, this.heightTarget, this.imageWidthTarget, this.imageHeightTarget]
            .forEach((input) => { input.value = ''; });
    }

    ready() {
        return this.hasImageTarget && 0 < this.imageTarget.naturalWidth;
    }

    /** Pointer position in displayed pixels from the image's top-left, clamped to it. */
    pointerPosition(event) {
        const bounds = this.imageTarget.getBoundingClientRect();

        return {
            x: Math.min(Math.max(event.clientX - bounds.left, 0), bounds.width),
            y: Math.min(Math.max(event.clientY - bounds.top, 0), bounds.height),
        };
    }

    area(from, to) {
        return {
            x: Math.min(from.x, to.x),
            y: Math.min(from.y, to.y),
            width: Math.abs(to.x - from.x),
            height: Math.abs(to.y - from.y),
        };
    }

    /** A drag is already in displayed pixels, so it needs no rescaling. */
    paint(from, to) {
        this.position(this.selectionTarget, this.area(from, to), { x: 1, y: 1 });
    }

    /**
     * An overlay is positioned against the stage, which is not the image: a
     * narrower screenshot is centred in it, and a wider one is scaled down by the
     * preflight `max-width: 100%`. The offset of the image is the origin, and each
     * length is in pixels, because a percentage would resolve against the stage.
     */
    position(element, box, scale) {
        element.style.left = `${this.imageTarget.offsetLeft + box.x * scale.x}px`;
        element.style.top = `${this.imageTarget.offsetTop + box.y * scale.y}px`;
        element.style.width = `${box.width * scale.x}px`;
        element.style.height = `${box.height * scale.y}px`;
        element.hidden = false;
    }

    /** Natural image pixels to displayed pixels; null until the image has laid out. */
    displayScale(width, height) {
        const bounds = this.imageTarget.getBoundingClientRect();

        if (!width || !height || 0 === bounds.width || 0 === bounds.height) {
            return null;
        }

        return { x: bounds.width / width, y: bounds.height / height };
    }

    store(area, scale) {
        const natural = {
            x: Math.round(area.x * scale),
            y: Math.round(area.y * scale),
            width: Math.round(area.width * scale),
            height: Math.round(area.height * scale),
        };

        this.xTarget.value = natural.x;
        this.yTarget.value = natural.y;
        this.widthTarget.value = natural.width;
        this.heightTarget.value = natural.height;
        this.imageWidthTarget.value = this.imageTarget.naturalWidth;
        this.imageHeightTarget.value = this.imageTarget.naturalHeight;

        this.summaryTarget.textContent =
            `${natural.width} × ${natural.height} px at ${natural.x}, ${natural.y}`;
    }

    placeMarker(marker) {
        const scale = this.displayScale(Number(marker.dataset.imageWidth), Number(marker.dataset.imageHeight));

        if (null === scale) {
            return;
        }

        this.position(marker, {
            x: Number(marker.dataset.rectX),
            y: Number(marker.dataset.rectY),
            width: Number(marker.dataset.rectWidth),
            height: Number(marker.dataset.rectHeight),
        }, scale);
    }

    repaintPending() {
        const scale = this.displayScale(Number(this.imageWidthTarget.value), Number(this.imageHeightTarget.value));

        if (null === scale) {
            return;
        }

        this.position(this.selectionTarget, {
            x: Number(this.xTarget.value),
            y: Number(this.yTarget.value),
            width: Number(this.widthTarget.value),
            height: Number(this.heightTarget.value),
        }, scale);
    }
}
