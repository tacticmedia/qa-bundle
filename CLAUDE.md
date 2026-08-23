# qa-bundle

## What this is for

An end-to-end suite proves that a page works. It cannot tell you that the page looks wrong. A
heading overlapping a badge, a table losing its right gutter at one breakpoint, unreadable contrast
in dark mode - every assertion passes and the screen is still broken.

This bundle closes that gap with a human in the loop. The journeys shoot every settled screen; a
person looks at the images, drags a box around what is wrong and types one sentence; the bundle turns
those sentences into a brief precise enough for a coding agent to act on without asking follow-up
questions.

The whole design follows from one constraint: **a pixel coordinate on a 10000 px tall image does not
locate code.** Everything below exists to close the distance between "this looks wrong here" and the
template that renders it.

## The two halves

**Capture** - `src/Test/JourneyScreenshots.php`, a PHPUnit trait mixed into a Panther journey. It
needs no container and no bundle registration; it runs where PHPUnit runs.

**Review** - `src/Controller/ScreenshotReviewController.php` plus `src/Review/*`, a dev-only page at
`/_dev/screenshots`. It needs the container, and only ever reads the screenshot tree.

They share nothing but the directory layout:

```
<screenshots_dir>/<mode>/<WxH>/<orientation>/<TestCase>-<testMethod>-<NNN>_<label>.png
                                             ... same basename ....................json
```

The basename is the contract. It carries the test class, the method, the capture's position in the
journey and the screen label, so the review can name the origin of a capture without any index, and
a note survives a re-run that replaces every file.

## Why each piece is the way it is

**Five viewports, two colour schemes, one measurement.** A layout bug usually lives at one width.
Capturing at five means the reviewer sees the breakpoint that broke rather than the one they happened
to open. Light and dark share a layout, so the element geometry is measured once per viewport and
written to both sidecars - halving the browser work.

**Full-page, not viewport.** The document height only exists at the device width, so the trait
measures the layout there, grows the viewport to the whole document, and re-measures once because
`vh`-sized elements grow along. `captureBeyondViewport` alone can return a viewport-sized image.

**`deviceScaleFactor` is 1.** That makes the sidecar's CSS pixels the PNG's pixels, which is the only
reason a rectangle drawn on the image can be matched against an element box at all.

**The sidecar.** Capture time is the only moment the live DOM exists. Every element worth naming gets
its box, its text, its CSS selector, its classes and its identifying attributes, plus the page URL and
title and the test file that produced it. That is what turns a rectangle into "the `<a>` inside the
table cell, with these classes, on this route".

**Resolution happens late.** `SelectionResolver` matches the stored rectangle against the *current*
sidecar at prompt-build time, not at save time, so a note stays useful across re-runs. When the page
has changed size since the note was written, the prompt says so rather than pointing confidently at
elements that have moved.

**Smallest distinct elements win.** A drag over a table cell covers the link, the cell, the row, the
table and the page. `SelectionResolver::distinct()` reports the link and drops every box that contains
one already taken, then `enclosing` names the containers separately. Naming the row would send an
agent to the wrong template.

**Crops.** `SelectionCropper` cuts the selection out with 80 px of context and outlines it in red. An
agent reading a 1920x10000 image learns nothing; it reads the crop first and opens the full page only
for context. Crops are regenerated on every prompt build and the coordinates stand on their own, so a
crop that could not be produced degrades the brief rather than breaking the page.

**Notes outlive screenshots.** The tree is emptied at the start of every run; the notes file is not,
and deliberately lives outside it. A note whose capture is gone still names its test and screen, and
the prompt flags it as belonging to an earlier run.

**Discovery, not configuration.** The review page lists whatever directories the tree holds. A journey
that adds a viewport or a third colour scheme needs no change here, and there is no list to keep in
sync between the trait and the catalog.

## Conventions to keep

- **The DOM metadata script has one source: `resources/capture/metadata.js`.** The PHP trait reads
  it at runtime and the npm build copies it into `dist/capture/`; neither holds a copy. It must stay
  an **expression** (no trailing semicolon) because both producers evaluate it for its value, and
  `resources/` is deliberately not archive-excluded so the installed package carries it.
- **The on-disk layout is public API, written down in `docs/screenshot-sets.md` plus
  `docs/sidecar.schema.json`.** Two producers write it now. Changing a grammar or the meaning of a
  required field breaks the other producer and every stored note. Adding an optional sidecar field,
  a mode or a viewport does not.
- **Three things move together when the sidecar shape changes:** `MetadataElement`/`ScreenMetadata`,
  `docs/sidecar.schema.json`, and the ajv test in `js/tests/unit/sidecar.spec.ts`. The schema is not
  documentation, it is a gate.
- **`tests/Contract/ProducedTreeTest.php` is the proof the contract is one.** It runs the real
  reader over a tree the npm package wrote, and skips itself without `QA_SCREENSHOTS_DIR`. If it
  only ever skips, the contract is untested.
- **Two npm packages, two jobs.** `assets/package.json` is `@tacticmedia/qa-bundle`, the Stimulus
  controllers the review page loads. `js/package.json` is `@tacticmedia/qa-capture`, the Playwright
  producer. Neither name is free for the other.
- **One clearing producer per root per run.** Both producers empty the tree at run start and keep
  the root. Running the PHP journey and then the Playwright one replaces the tree; they alternate,
  they never accumulate.
- **The review reports what it ignored.** `ScreenshotCatalog::ignored()`/`allIgnored()` feed a
  footnote on the group and empty pages, and the annotate page warns about an unreadable sidecar. A
  foreign producer's first mistake must never render as an empty page with no explanation.
- **The prompt is producer-neutral.** No `captureFullPageScreenshot`, no test-framework vocabulary:
  journey and scenario. A host that wants its own phrasing overrides `prompt.txt.twig`.
- **`symfony/panther` is a suggestion, not a dependency.** Only the capture trait needs it, and
  requiring it drags php-webdriver and `ext-zip` into the review-only container image.
- **In the review image, `/data` is the reviewed project's root.** The brief prints project-relative
  paths, so each tree is mounted at the path it has on the host and
  `App\MountedProjectPathsPass` points the catalog and the cropper at `/data`. Absolute container
  paths in a brief are worse than useless: the agent reading them runs on the host.
- **`docker/review/composer.lock` is committed and drifts.** A change to the bundle's dependencies
  means `composer update` in `docker/review/`. The `docker` CI job is the detector.
- **The trait must stay container-free.** It is half the package's value that a project can use the
  capture without registering the bundle. Configuration comes from overridable `protected static`
  methods and one environment variable, never from bundle configuration.
- **Use the trait once, on a shared journey base class.** The clear-once guard is a trait static,
  which PHP copies into every class that uses the trait directly, so a second using class would
  re-empty the tree mid-run and discard the first class's captures.
- **The review never writes into the screenshot tree.** Notes and crops go under `review_dir`.
- **The group grid never parses a sidecar.** It reads filenames only. Sidecars carry every element on
  the page; parsing one per card would make a large run crawl.
- **Paths the prompt prints are project-relative when they can be.** An agent has to be able to open
  them. Absolute is the fallback, not the default.
- **PHP emits data; templates emit words.** The prompt's wording lives in `prompt.txt.twig` precisely
  so a host can replace it with its own conventions through a standard bundle template override.
- **Stimulus identifiers are `qa-*`.** They are set in `assets/package.json` under
  `symfony.controllers[*].name`, which overrides the name the loader would otherwise derive from the
  package. Without it every target attribute would read
  `data-tacticmedia--qa-bundle--annotate-target`. Changing a name breaks the templates and the
  E2E selectors together.
- **The `symfony-ux` keyword in composer.json is load-bearing.** Flex's `PackageJsonSynchronizer`
  rebuilds the host's `assets/controllers.json` from the installed packages carrying that keyword,
  dropping every entry it cannot account for. Without the keyword a host's block is silently wiped on
  the next composer run and the page goes inert.
- **`peerDependencies` in `assets/package.json` is what reaches a bundler host.** Flex takes its
  `package.json` branch for a Webpack Encore or Reprise host, where `symfony.importmap` is ignored and
  the peers are copied into the host's `devDependencies` instead. Dropping them leaves
  `@hotwired/turbo` unresolved, which kills the whole annotate controller - and only on those hosts,
  while AssetMapper stays green.
- **The `importmap()` call lives in `_importmap.html.twig`, never inline in the layout.** `{% extends %}`
  compiles the parent template whole, so an inline call is a parse error on a host with no
  AssetMapper even when that host overrides `{% block scripts %}`. `include()` resolves at render
  time, which is the only reason the documented override works. `EncoreHostTest` and
  `RepriseHostTest` fail the moment it moves back.
- **`symfony/asset-mapper` is a dev dependency, deliberately.** In `require` it would install and
  enable AssetMapper in every host, including ones that build with Encore or Reprise. As a dev
  dependency `ContainerBuilder::willBeAvailable()` correctly reports it unavailable here, so
  TwigBundle never registers `importmap()` - which is why `tests/Fixtures/app/TestKernel.php` declares
  `twig.extension.importmap` itself to be the AssetMapper host it stands in for.
- **`csrf-protection` is a name-coupled host contract.** The forms reference the recipe controller by
  that literal name. It is not shipped, and renaming it on the host side makes every write silently
  no-op. It only exists on Symfony 7.2 and up, where the token id is registered stateless; below that
  the id is session-backed and the attribute is inert.
- **The floor is PHP 8.2 and Symfony 6.4.** No property hooks, no asymmetric visibility, no typed
  class constants, no `new X()->y()`. `array_all`, `array_any`, `array_find` and `array_find_key`
  come from `symfony/polyfill-php84`; nothing else from 8.3 or 8.4 is available. On the Symfony side
  `#[IsCsrfTokenValid]` (7.1) and `framework.csrf_protection.stateless_token_ids` (7.2) are out of
  reach, so `ScreenshotReviewController::assertCsrfToken()` validates the token itself and
  `TacticMediaQaBundle::prependExtension()` registers the stateless id only where
  `SameOriginCsrfTokenManager` exists.
- **Overlay geometry goes through the CSSOM.** `style-src` gates a `style` attribute parsed from
  markup, not a property write, so `element.style.left = ...` survives a strict content security
  policy where the same geometry written into the template, or through `setAttribute('style', ...)`,
  would not.

## Testing

`vendor/bin/phpunit --exclude-group e2e` covers the domain (temp-directory unit tests), the bundle's
configuration (a real `ContainerBuilder`, no kernel) and the page (`WebTestCase` against
`tests/Fixtures/app`, including the real CSRF path - every write carries the token the page rendered
and a same-origin header).

There are three fixture hosts, one per way a host can build its JavaScript: `tests/Fixtures/app` is
AssetMapper and carries the whole suite, `tests/Fixtures/encore` and `tests/Fixtures/reprise` each
run `BundlerHostTestCase` and exist to prove one thing - that the layout override the README
documents renders the page in a host with no `importmap()` at all. Their
`templates/bundles/TacticMediaQaBundle/layout.html.twig` carries the README snippet's functional
lines byte for byte; keep them identical or the documentation is untested. `RepriseHostTest` skips
itself where the package is absent - in CI that is every leg below PHP 8.4, and locally it always
skips unless `symfony/reprise` is required.

The `node` CI job builds the npm package, runs its unit specs, captures against the PHP fixture host
and then runs `--group contract` over that same tree. The `docker` job builds the review image,
smoke-tests the empty state and publishes to ghcr.io on push.

CI runs PHPStan and the non-e2e group across PHP 8.2/Symfony 6.4, 8.3/7.4, 8.4/8.1 and 8.5/8.1,
the e2e group on 8.2/6.4 and 8.5/8.1 only, and installs `symfony/reprise` on the two top legs
only. A separate style job runs `composer validate --strict` and `composer cs:check` on 8.5.
Pin a branch with
global flex and `composer config extra.symfony.require`, as `.github/workflows/ci.yaml` does: a plain
`composer update` constrains only the packages `composer.json` names and lets the transitive Symfony
packages float to their latest branch, so the run proves nothing.

`vendor/bin/phpunit --group e2e` drives real Chrome against the same fixture app through the PHP
built-in web server. It exists for what only a browser proves: the drag writing natural image pixels,
the saved marker landing back on the pixels it records when the image is centred or scaled, and the
keyboard walk.

Two browser-driving rules learned the hard way, both in `tests/E2e/ScreenshotReviewE2eTest.php`:

- Send keys as a W3C key action with no element. `sendKeys` resolves the active element first, which
  goes stale across a Turbo swap and is refused outright when the focus sits on the body.
  php-webdriver's own `keyDown()`/`keyUp()` wrappers only accept modifier keys, so the command is
  issued directly.
- Wait past the Turbo preview before taking an element handle. Turbo renders a cached preview and then
  the real response, so a handle taken on a URL match alone can be replaced mid-drag.

`demo/playwright/` is the Playwright twin of the demo journey, consuming `js/` through a `file:`
dependency with `install-links=true` - a symlinked copy would resolve a second `@playwright/test`
and Playwright refuses to load twice. Rebuilding `js/` means `npm install` there again.

`demo/` is a stock Symfony 8.1 application on AssetMapper, PHP 8.4, that requires the bundle through
a path repository. Its `vendor/` and `var/` are git-ignored, so a fresh checkout runs `composer
install` there before `demo/bin/console`. It is where a real Flex install is read back:
`assets/controllers.json` carries the four controllers and `importmap.php` the two Hotwired packages.
It enables the bundle in every environment, and takes the review routes from Symfony 8's
`routing.controllers` loader, which imports them from the controller service - so it never imports
`config/routes.php` the way the README tells a host to. Nothing in CI runs it, PHPStan does not
analyse it, and `composer.json` keeps it out of the distributed archive.

PHPStan runs at level 8 over `src` and `tests`.
