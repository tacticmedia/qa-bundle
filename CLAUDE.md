# qa-bundle

## Purpose

An end-to-end suite shows that a page operates correctly. It does not show that the page looks
wrong. A heading that overlaps a badge, a table that loses its right gutter at one breakpoint, or
text with too little contrast in dark mode: each assertion passes and the screen is still incorrect.

This bundle adds a human review step. The journeys capture each settled screen. A person examines
the images, selects a rectangle over the incorrect area and writes one sentence. The bundle converts
those sentences into a brief that is accurate enough for a coding agent to use without more
questions.

The design follows from one constraint: **a pixel coordinate on an image 10000 px high does not
identify code.** The parts below connect the reported area to the template that renders it.

## The two halves

**Capture** - `src/Test/JourneyScreenshots.php`, a PHPUnit trait for a Panther journey. It does not
use the container and does not need bundle registration. It runs where PHPUnit runs.

**Review** - `src/Controller/ScreenshotReviewController.php` and `src/Review/*`, a page at
`/_dev/screenshots` that the host enables in `dev`. It uses the container, and it only reads the
screenshot tree.

The two halves share the directory layout and nothing else:

```
<screenshots_dir>/<mode>/<WxH>/<orientation>/<TestCase>-<testMethod>-<NNN>_<label>.png
                                             ... same basename ...................json
```

The basename is the contract. It contains the test class, the method, the position of the capture in
the scenario and the screen label. The review derives the origin of a capture from the basename, so
it keeps no index, and a note stays valid after a re-run replaces each file.

## Design decisions

**Five viewports, two colour schemes, one measurement.** A layout defect usually occurs at one
width. Five viewports show the reviewer the breakpoint that failed, not only the breakpoint they
opened. Light and dark use the same layout, so the trait measures the element geometry once for each
viewport and writes it to both sidecars. The DOM measurement then runs once for each viewport
instead of twice.

**Full-page capture, not viewport capture.** The document height is only known after layout at the
device width. The trait measures at that width, increases the viewport to the full document height,
and measures a second time because `vh`-sized elements increase with it. `captureBeyondViewport` on
its own can return an image the size of the viewport.

**`deviceScaleFactor` is 1.** The CSS pixels in the sidecar are then the pixels of the PNG. This is
the condition that lets the review match a rectangle drawn on the image against an element box.

**The sidecar.** Capture time is the only point at which the live DOM is available. The trait
records each element that has an id, a semantic tag, its own text, a `data-controller` or a `role`,
and a box of 2x2 px or more. For each such element it records the box, the text, the CSS selector,
the classes and a fixed set of identifying attributes. The filter is at the top of
`resources/capture/metadata.js`. The sidecar also records the page URL, the title, the page size and
the test file. These fields let the review describe a rectangle as the `<a>` in the table cell, with
its classes, on a known route.

**Resolution occurs at prompt-build time.** `SelectionResolver` matches the stored rectangle against
the *current* sidecar when it builds the prompt, not when the note is saved, so a note stays usable
after a re-run. The staleness check compares the page size in the sidecar against the image size
stored on the note. Above a slack of two pixels, the prompt gives the coordinates only and does not
name elements that can have moved.

**The smallest distinct elements are reported.** A selection over a table cell covers the link, the
cell, the row, the table and the page. `SelectionResolver` keeps the link and removes each box that
contains a box already selected. It then reports the containers separately as `enclosing`. A
selection that covers no complete element falls back to the elements it intersects. A report that
named the row would send an agent to the incorrect template.

**Crops.** `SelectionCropper` extracts the selection with 80 px of context and draws a red outline
around it. An image of 1920x10000 px gives an agent little information, so the agent reads the crop
first and opens the full page only for context. The review regenerates the crops for each prompt
build. The coordinates in the prompt are complete without the crop, so a crop that cannot be
produced reduces the detail in the brief but does not cause an error.

**Notes have a longer life than screenshots.** Each run empties the tree. The notes file is outside
the tree and is not emptied. A note whose capture no longer exists still gives its test and screen,
and the prompt marks it as a note from an earlier run.

**Discovery instead of configuration.** The review page lists the directories that the tree
contains. A journey that adds a viewport or a third colour scheme needs no change here, and there is
no list to keep synchronised between the trait and the catalog.

## Conventions to keep

- **The DOM metadata script has one source: `resources/capture/metadata.js`.** The PHP trait reads
  it at runtime and the npm build copies it into `dist/capture/`. Neither location holds a second
  copy. It must stay an **expression** (no trailing semicolon), because both producers evaluate it
  for its value. `resources/` is not archive-excluded, so the installed package contains it.
- **The on-disk layout is public API. It is specified in `docs/screenshot-sets.md` and
  `docs/sidecar.schema.json`.** Two producers write it. A change to a grammar, or to the meaning of
  a required field, breaks the other producer and each stored note. An added optional sidecar field,
  mode or viewport does not.
- **Three items change together when the sidecar shape changes:** `MetadataElement`/`ScreenMetadata`,
  `docs/sidecar.schema.json`, and the ajv test in `js/tests/unit/sidecar.spec.ts`. The schema is a
  gate, not documentation.
- **`tests/Contract/ProducedTreeTest.php` shows that the contract holds.** It runs the PHP reader
  over a tree that the npm package wrote, and it skips itself when `QA_SCREENSHOTS_DIR` is not set.
  If it only skips, the contract is not tested.
- **Two npm packages, two functions.** `assets/package.json` is `@tacticmedia/qa-bundle`, the
  Stimulus controllers that the review page loads. `js/package.json` is `@tacticmedia/qa-capture`,
  the Playwright producer. Do not use either name for the other package.
- **One clearing producer for each root in each run.** Both producers empty the tree when the run
  starts and keep the root. If the PHP journey runs and then the Playwright journey runs, the second
  tree replaces the first. The two producers alternate; they do not accumulate.
- **The review reports the entries it ignored.** `ScreenshotCatalog::ignored()`/`allIgnored()`
  supply a footnote on the group page and the empty page, and the annotate page gives a warning for
  a sidecar it cannot read. A first error from an external producer must not appear as an empty page
  with no explanation.
- **The prompt is producer-neutral.** It contains no producer name and no framework name. The labels
  are journey and scenario. A host that needs different wording overrides `prompt.txt.twig`.
- **`symfony/panther` is a suggestion and a dev dependency. Do not put it in `require`.** Only the
  capture trait uses it, and a requirement adds php-webdriver and `ext-zip` to the container image
  that serves the review page.
- **In the review image, `/data` is the root of the reviewed project.** The brief prints
  project-relative paths, so each tree is mounted at the path it has on the host, and
  `App\MountedProjectPathsPass` sets the catalog and the cropper to `/data`. The agent that reads
  the brief runs on the host, so absolute container paths in a brief are unusable.
- **`docker/review/composer.lock` is committed and can become out of date.** After a change to the
  dependencies of the bundle, run `composer update` in `docker/review/`. The `docker` CI job detects
  the difference.
- **The trait must not use the container.** A project can use the capture without registration of
  the bundle, and that is half the value of the package. Configuration comes from `protected static`
  methods that a test case overrides, and from one environment variable. It does not come from
  bundle configuration.
- **Use the trait once, on a shared journey base class.** The clear-once guard is a trait static,
  which PHP copies into each class that uses the trait directly. A second such class empties the
  tree again during the run and removes the captures of the first class.
- **The review does not write into the screenshot tree.** Notes and crops are written under
  `review_dir`.
- **The group grid does not parse a sidecar.** It reads filenames only. A sidecar contains each
  element on the page, so one parse for each card makes a large run slow.
- **The prompt prints project-relative paths where it can.** An agent must be able to open them. An
  absolute path is the fallback, not the default.
- **PHP supplies data; templates supply text.** The wording of the prompt is in `prompt.txt.twig`,
  so a host can replace it with its own conventions through a standard bundle template override.
- **Stimulus identifiers use the `qa-` prefix.** They are set in `assets/package.json` under
  `symfony.controllers[*].name`, which replaces the name that the loader derives from the package
  name. Without them, each target attribute reads
  `data-tacticmedia--qa-bundle--annotate-target`. A change to a name breaks the templates and the
  E2E selectors together.
- **The `symfony-ux` keyword in composer.json is necessary.** Flex's `PackageJsonSynchronizer`
  rebuilds the host's `assets/controllers.json` from the installed packages that have that keyword,
  and removes each entry it cannot match to one. Without the keyword, the next composer run removes
  the block from the host, and the page stops responding.
- **`peerDependencies` in `assets/package.json` is the data that reaches a bundler host.** For a
  Webpack Encore or Reprise host, Flex uses the `package.json` branch, ignores `symfony.importmap`
  and copies the peers into the host's `devDependencies`. Without them `@hotwired/turbo` does not
  resolve and the annotate controller does not load. This occurs on those hosts only; an AssetMapper
  host is not affected.
- **The `importmap()` call is in `_importmap.html.twig`. Do not put it inline in the layout.**
  `{% extends %}` compiles the complete parent template, so an inline call is a parse error on a host
  that has no AssetMapper, even when that host overrides `{% block scripts %}`. `include()` resolves
  at render time, which is the condition that makes the documented override work. `EncoreHostTest`
  and `RepriseHostTest` fail if the call is moved back.
- **`symfony/asset-mapper` is a dev dependency.** In `require` it would install and enable
  AssetMapper in each host, including hosts that build with Encore or Reprise. As a dev dependency,
  `ContainerBuilder::willBeAvailable()` reports it as unavailable here, so TwigBundle does not
  register `importmap()`. For that reason `tests/Fixtures/app/TestKernel.php` declares
  `twig.extension.importmap` itself, to act as the AssetMapper host.
- **`csrf-protection` is a host contract that depends on the name.** The forms refer to the recipe
  controller by that name. The bundle does not supply the controller. If the host renames it, each
  write has no effect. The controller exists on Symfony 7.2 and later, where the token id is
  registered as stateless. Below 7.2 the id is session-backed and the attribute has no effect.
- **The minimum versions are PHP 8.2 and Symfony 6.4.** Do not use property hooks, asymmetric
  visibility, typed class constants or `new X()->y()`. `array_all`, `array_any`, `array_find` and
  `array_find_key` come from `symfony/polyfill-php84`. No other function from 8.3 or 8.4 is
  available. `#[IsCsrfTokenValid]` (7.1) and `framework.csrf_protection.stateless_token_ids` (7.2)
  are also unavailable, so each write calls `ScreenshotReviewController::assertCsrfToken()`, which
  checks the token with `AbstractController::isCsrfTokenValid()` instead of the attribute, and
  `TacticMediaQaBundle::prependExtension()` registers the stateless id only where
  `SameOriginCsrfTokenManager` exists.
- **Overlay geometry is set through the CSSOM.** `style-src` controls a `style` attribute that the
  parser reads from markup, not a property write. `element.style.left = ...` therefore operates
  under a strict content security policy, where the same geometry in the template, or a call to
  `setAttribute('style', ...)`, does not.

## Testing

`vendor/bin/phpunit --exclude-group e2e` covers three areas: the domain, through unit tests that use
a temporary directory; the configuration of the bundle, through a real `ContainerBuilder` with no
kernel; and the page, through `WebTestCase` against `tests/Fixtures/app`. The page tests use the
real CSRF path, so each write sends the token that the page rendered and a same-origin header.

There are three fixture hosts, one for each method a host can use to build its JavaScript.
`tests/Fixtures/app` uses AssetMapper and runs the complete suite. `tests/Fixtures/encore` and
`tests/Fixtures/reprise` each run `BundlerHostTestCase`, and they test one condition: that the
layout override in `docs/frontend.md` renders the page on a host that has no `importmap()`. The
`templates/bundles/TacticMediaQaBundle/layout.html.twig` file in the Encore fixture is identical to
the `docs/frontend.md` snippet. The file in the Reprise fixture replaces only the entry-tag
function that `docs/frontend.md` names for that host. Keep both files in that state, or the
documented override is untested.
`RepriseHostTest` skips itself where the package is not installed. In CI that is each leg below PHP
8.4. Locally it skips unless `symfony/reprise` is required.

The `node` CI job builds the npm package, runs its unit specs, captures against the PHP fixture host
and then runs `--group contract` over that tree. The `docker` job builds the review image and tests
the empty state on each push and pull request, and publishes to ghcr.io on push.

CI runs PHPStan and the non-e2e group on PHP 8.2/Symfony 6.4, 8.3/7.4, 8.4/8.1 and 8.5/8.1. It runs
the e2e group on 8.2/6.4 and 8.5/8.1 only, and installs `symfony/reprise` on the two highest legs
only. A separate style job runs `composer validate --strict` and `composer cs:check` on 8.5.

To select a branch, use global flex and `composer config extra.symfony.require`, as
`.github/workflows/ci.yaml` does. `composer update` on its own constrains only the packages that
`composer.json` names, and the transitive Symfony packages then resolve to their latest branch. Such
a run does not test the intended versions.

`vendor/bin/phpunit --group e2e` drives Chrome against the same fixture application through the PHP
built-in web server. It tests the behaviour that needs a browser: the selection that records natural
image pixels, the saved marker that returns to the pixels it records when the image is centred or
scaled, and the keyboard navigation.

Two rules for driving the browser, both used in `tests/E2e/ScreenshotReviewE2eTest.php`:

- Send keys as a W3C key action with no element. `sendKeys` resolves the active element first. That
  reference becomes stale after a Turbo swap, and the command is refused when the focus is on the
  body. The `keyDown()`/`keyUp()` wrappers in php-webdriver accept modifier keys only, so the
  command is issued directly.
- Wait until the Turbo preview is replaced before you take an element handle. Turbo renders a cached
  preview and then the real response, so a handle taken on a URL match alone can be replaced during
  a drag.

`demo/playwright/` is the Playwright version of the demo journey. It consumes `js/` through a
`file:` dependency with `install-links=true`. A symlinked copy resolves a second `@playwright/test`,
and Playwright does not load twice. After you rebuild `js/`, run `npm install` there again.

`demo/` is a Symfony 8.1 application on AssetMapper and PHP 8.4 that requires the bundle through a
path repository. Its `vendor/` and `var/` directories are git-ignored, so run `composer install`
there in a new checkout before `demo/bin/console`. The demo shows the result of a Flex install:
`assets/controllers.json` contains the four controllers and `importmap.php` contains the two
Hotwired packages. The demo enables the bundle in each environment, and it takes the review routes
from the Symfony 8 `routing.controllers` loader, which imports them from the controller service. It
therefore does not import `config/routes.php`, which is the method the README gives to a host. No CI
job runs the demo, PHPStan does not analyse it, and `composer.json` excludes it from the distributed
archive.

PHPStan runs at level 8 over `src` and `tests`.
