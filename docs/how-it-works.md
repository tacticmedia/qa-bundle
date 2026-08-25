# How it works

The steps below connect a rectangle drawn on a screenshot to the code that renders it. The
on-disk layout that the steps refer to is specified in [screenshot-sets.md](screenshot-sets.md).

1. **Measure, capture, measure again.** The document height is known only after layout at the device
   width. The producer sets the viewport, measures, increases the viewport to the full document
   height, then measures a second time, because `vh`-sized elements grow with it.
   `deviceScaleFactor` is 1, so one CSS pixel is one image pixel. That equality is the condition
   that lets a rectangle drawn on the image match an element box.

2. **The sidecar records the DOM.** Capture time is the only point at which the live DOM is
   available. The producer stores each element that has an id, a semantic tag, its own text, a
   `data-controller` or a `role`, and a box of 2x2 px or more. For each one it writes the box in
   document-absolute CSS pixels, the text, the CSS selector, the classes and a fixed set of
   identifying attributes. It also writes the page URL, the title, the page size and the test file.
   Light and dark use the same layout, so it measures once for each viewport and writes the result
   to both sidecars.

3. **The basename is the index.** A capture is
   `<mode>/<WxH>/<orientation>/<journey>-<scenario>-<NNN>_<label>.png`, with the sidecar beside it
   under the same basename. The review derives the origin of a capture from that path, so it keeps
   no database, and a note stays valid after a re-run replaces every file.

4. **The selection resolves at prompt-build time.** A note stores its rectangle in natural image
   pixels, and the resolver matches it against the *current* sidecar when it builds the prompt. It
   reports the smallest distinct elements: a rectangle over a table cell covers the link, the cell,
   the row, the table and the page, so it keeps the link and lists the containers separately as
   `enclosing`. A rectangle that covers no complete element falls back to the elements it
   intersects. Where the page size changed by more than two pixels since the note was written, the
   prompt gives the coordinates only and names no element.

5. **Each item carries a crop.** The brief refers to a cropped view of the selection, with 80 px of
   context and a red outline. An image of 1920x10000 px tells an agent little, so the agent reads
   the crop first and opens the full page for context. The coordinates in the prompt are complete
   without the crop.

6. **Notes have a longer life than screenshots.** Each run empties the screenshot tree. The notes
   file is outside it. A note whose capture no longer exists still gives its journey, scenario and
   screen, and the prompt marks it as a note from an earlier run.

7. **The wording is a template.** `prompt.txt.twig` holds the text of the brief. A host overrides it
   to state its own conventions, document paths and test command.
