# Screenshot sets: the producer contract

The review page at `/_dev/screenshots` reads a directory tree and nothing else. It does not know
which tool wrote the tree, what language that tool is written in, or whether a test framework was
involved. Any producer that writes this layout can be reviewed.

This document is the contract. `tacticmedia/qa-bundle`'s PHPUnit trait
(`TacticMedia\QaBundle\Test\JourneyScreenshots`) and the `@tacticmedia/qa-capture` npm package are
two implementations of it.

## Vocabulary

The basename carries two identity parts. The PHP trait fills them from a test class and a test
method, but the contract names them by role, and a producer is free to derive them from anything:

- **journey** - the file or suite the captures came from. One journey groups screens in the review
  sidebar and in the generated agent brief.
- **scenario** - the individual run within that journey.

## Tree layout

```
<root>/<mode>/<WxH>/<orientation>/<journey>-<scenario>-<NNN>_<label>.png
                                 <journey>-<scenario>-<NNN>_<label>.json
```

The PNG and its sidecar share one basename. That basename is the whole index: the review resolves a
capture's origin from it, so a note written against a capture survives a re-run that replaces every
file on disk.

## Grammars

| Part | Grammar | Notes |
|---|---|---|
| `mode` | `^[a-z][a-z0-9-]*$` | Directory name, discovered not configured. `light` and `dark` by convention. |
| `WxH` | `^\d+x\d+$` | The **requested viewport** in CSS pixels, not the image size. |
| `orientation` | `portrait` \| `landscape` | Closed set. `height >= width` is portrait, so a square is portrait. |
| `journey` | `\w+` | `[A-Za-z0-9_]`. |
| `scenario` | `\w+` | `[A-Za-z0-9_]`. |
| `NNN` | `\d{3}` | The capture's position in the scenario, from `001`. Zero-padded, per scenario. |
| `label` | `^[A-Za-z0-9 _-]+$` | Spaces and underscores are legal. Any other character must be replaced, not dropped, so distinct labels stay distinct. |

A directory or file that does not match is ignored by the reader and reported in the review page's
ignored list. It is never an error.

### Why `WxH` is not the image size

The document height only exists once the page is laid out at the device width. A producer measures
there, grows the viewport to the full document height, and captures the whole page. So for any page
taller than the viewport, the directory name and the image height disagree by design: the directory
names the breakpoint, the image is the whole page.

## The sidecar

JSON object, UTF-8. Machine-checkable shape: `docs/sidecar.schema.json` (JSON Schema 2020-12).

Required:

| Field | Type | Meaning |
|---|---|---|
| `url` | string | The URL the page was on at capture time. |
| `title` | string | `document.title`. |
| `pageWidth` | integer | Width of the captured image, in CSS pixels. |
| `pageHeight` | integer | Height of the captured image, in CSS pixels. |

Optional:

| Field | Type | Meaning |
|---|---|---|
| `elements` | array | Element boxes and identities. Absent or empty means annotations resolve to coordinates only. |
| `testClass` | string | Fully qualified producer-side identifier of the journey, when one exists. |
| `testFile` | string | Path to the source file that produced the capture, project-relative when possible. The agent brief prints it. |

Unknown top-level keys are ignored, so a producer may add its own.

### Element shape

Required per element: `selector` (string), `tag` (string), `x`, `y`, `width`, `height` (numbers, in
**document-absolute** CSS pixels, so scroll offset is already added).

Optional: `text` (string or null), `classes` (string or null), `attributes` (object of string to
string).

An element that fails the required shape is dropped silently; the rest of the sidecar still loads.

## The pixel invariant

**`pageWidth` and `pageHeight` must equal the PNG's pixel dimensions.**

This is the one hard invariant, and everything the review does with a rectangle rests on it. The
reviewer drags a box on the rendered image; the resolver matches that box against element
coordinates from the sidecar. If image pixels and CSS pixels are not the same unit, no match is
possible.

Consequences for a producer:

- Capture at `deviceScaleFactor` / `devicePixelRatio` of exactly 1.
- Stamp the page size from the capture clip you asked for, not by reading the page back afterwards.
  Re-measuring after the final resize can return a value a few pixels short of the image.
- Do not scale, crop or post-process the PNG.

Readers tolerate a difference of up to 2 pixels on either axis. Beyond that the review marks the
note's layout as changed and reports coordinates without resolving elements, rather than pointing
confidently at elements that have moved.

## Clearing semantics

**Latest run wins.** A producer empties the tree at the start of a run and keeps the root directory
itself, which is normally a bind-mount target.

**One clearing producer per root per run.** Two producers writing the same root in one run means the
second erases the first. Run them against separate roots, or accept that they alternate rather than
accumulate.

Notes live outside the screenshot tree and are not cleared. A note whose capture is gone still names
its journey, scenario and screen, and the agent brief flags it as belonging to an earlier run.

## Root resolution

`QA_SCREENSHOTS_DIR` names the root. A producer may offer its own option, but the environment
variable takes precedence, so a per-run override cannot disagree with itself between a setup step
and the capture step.

## Versioning

Adding an optional sidecar field, a new `mode` string or a new viewport is backwards compatible and
needs no reader change. Changing a grammar, or the meaning of a required field, is a breaking change
to every producer and to stored notes.
