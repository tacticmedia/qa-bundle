# Screenshot sets: the producer contract

The review page at `/_dev/screenshots` identifies a capture from its path and basename only. You can
therefore review the output of any producer that writes this layout.

This document is the contract. The PHPUnit trait in `tacticmedia/qa-bundle`
(`TacticMedia\QaBundle\Test\JourneyScreenshots`) and the `@tacticmedia/qa-capture` npm package are
two implementations of it.

## Vocabulary

The basename contains two identity parts. The PHP trait sets them from a test class and a test
method. The contract identifies them by function, and a producer can derive them from any source:

- **journey** - the file or suite that supplied the captures. The review sidebar and the generated
  agent brief group the screens by journey.
- **scenario** - one run in that journey.

## Tree layout

```
<root>/<mode>/<WxH>/<orientation>/<journey>-<scenario>-<NNN>_<label>.png
                                  ... same basename ................json
```

The PNG and its sidecar have the same basename. The basename is the complete index: the review
derives the origin of a capture from it, so a note written against a capture stays valid after a
re-run replaces each file on disk.

## Grammars

| Part | Grammar | Notes |
|---|---|---|
| `mode` | `^[a-z][a-z0-9-]*$` | Directory name. The reader discovers it; it is not configured. `light` and `dark` by convention. |
| `WxH` | `^\d+x\d+$` | The **requested viewport** in CSS pixels, not the image size. |
| `orientation` | `portrait` \| `landscape` | Closed set. `height >= width` is portrait, so a square is portrait. One orientation directory for each `WxH`; the reader reads the first that it finds and does not report a second. |
| `journey` | `\w+` | `[A-Za-z0-9_]` in the C locale that PHP starts in. |
| `scenario` | `\w+` | `[A-Za-z0-9_]` in the C locale that PHP starts in. |
| `NNN` | `\d{3}` | The capture's position in the scenario, from `001`. Zero-padded, per scenario. At most 999 per scenario; the producers do not enforce the limit, and the reader lists a fourth digit as an ignored entry. |
| `label` | `^[A-Za-z0-9 _-]+$` | Spaces and underscores are legal. Any other character must be replaced, not dropped, so a label with an illegal character stays distinct from the same label without it. Two labels whose illegal characters differ map to one string and differ only by `NNN`. |

The reader ignores a directory or file that does not match. This is not an error. The review page
reports the entries that it ignored: the complete tree on the empty page, and the directory of the
current group on a group page. It does not report two kinds of entry: an entry whose name starts
with a dot, and a file above an orientation directory.

### The difference between `WxH` and the image size

The document height is only known after layout at the device width. A producer measures at that
width, increases the viewport to the full document height, and captures the complete page. For a
page higher than the viewport, the directory name and the image height are therefore different: the
directory gives the breakpoint, and the image contains the complete page.

## The sidecar

JSON object, UTF-8. Machine-checkable shape: `docs/sidecar.schema.json` (JSON Schema 2020-12).

Required:

| Field | Type | Meaning |
|---|---|---|
| `url` | string | The URL the page was on at capture time. |
| `title` | string | `document.title`. |
| `pageWidth` | integer | Width of the PNG in pixels, which is one CSS pixel per image pixel. |
| `pageHeight` | integer | Height of the PNG in pixels, which is one CSS pixel per image pixel. |

Optional:

| Field | Type | Meaning |
|---|---|---|
| `elements` | array | Element boxes and identities. Absent or empty means annotations resolve to coordinates only. |
| `testClass` | string or null | Producer-side identifier of the journey. The PHP trait writes a fully qualified class name, the npm package writes the journey slug. Stored but not currently rendered; the brief names the journey from the basename. |
| `testFile` | string or null | Path to the source file that produced the capture, project-relative when possible. The agent brief prints it. |

The reader ignores unknown top-level keys, so a producer can add its own keys. It truncates a
fractional page size and accepts any numeric value where the schema demands a positive integer; the
schema is the normative shape.

### Element shape

Required per element: `selector` (string), `tag` (string), `x`, `y`, `width`, `height` (numbers, in
**document-absolute** CSS pixels, so scroll offset is already added). Fractional values are accepted
but truncated to whole pixels by the reader, so round them yourself.

Optional: `text` (string or null), `classes` (string or null), `attributes` (object of string to
string). The shipped producers omit an attribute whose value is empty.

The reader removes an element that does not have the required shape, and loads the remainder of the
sidecar.

## The pixel invariant

**`pageWidth` and `pageHeight` must equal the PNG's pixel dimensions.**

Each operation that the review performs on a rectangle depends on this invariant. The reviewer
selects a rectangle on the rendered image, and the resolver matches that rectangle against the
element coordinates in the sidecar. A match is not possible when image pixels and CSS pixels are
different units.

Consequences for a producer:

- Capture at CSS scale, one image pixel per CSS pixel. Chrome DevTools Protocol reaches that with
  `deviceScaleFactor: 1`; Playwright reaches it with `screenshot({ scale: 'css' })` at any device
  scale factor.
- Stamp the page size from the size you asked the browser for, not by reading the page back
  afterwards. Re-measuring after the final resize can return a value a few pixels short of the
  image.
- Do not resize, re-encode or crop the PNG after the browser produces it. A capture-time clip is
  acceptable if it covers the full page and the stamped size matches it.

A reader accepts a difference of 2 pixels or less on each axis. Above that difference, the review
marks the layout of the note as changed and reports the coordinates without a match against
elements, because the elements can have moved.

The review makes that comparison against the image size recorded when the note was written, not
against the PNG on disk. A producer that does not meet the invariant is therefore not reported as
incorrect; its notes stop matching elements. `tests/Contract/ProducedTreeTest.php` enforces the
invariant.

## Clearing semantics

**The tree contains the latest run.** A producer empties the tree when a run starts, and keeps the
root directory, which is usually a bind-mount target.

**One clearing producer for each root in each run.** If two producers write the same root in one
run, the second removes the output of the first. Run them against separate roots, or accept that
they alternate and do not accumulate.

Notes are outside the screenshot tree and the producer does not remove them. A note whose capture no
longer exists still gives its journey, scenario and screen, and the agent brief marks it as a note
from an earlier run.

## Root resolution

`QA_SCREENSHOTS_DIR` names the root. A producer that layers a root option on top of it must give
the environment variable precedence, so a per-run override gives the same root to the setup step
and to the capture step. The PHP trait's `screenshotRoot()` is an override and not a layered option,
so a host that replaces it also replaces the environment handling.

## Versioning

An added optional sidecar field, `mode` string or viewport is backward compatible and needs no
change to a reader. A change to a grammar, or to the meaning of a required field, is a breaking
change for each producer and for the stored notes.
