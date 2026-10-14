# qa-bundle

## Purpose

The README states the problem and the two halves. `docs/how-it-works.md` gives the design in seven
steps, from the two-pass measurement to the prompt template, and `docs/screenshot-sets.md` is the
on-disk contract between the producers and the review. This file contains what those documents do
not: where the code is, the conventions that keep the producers and the review consistent, and
how the test suite is laid out.

## Where the code is

**Capture** - two PHPUnit traits: `src/Test/JourneyScreenshots.php` for a Panther journey and
`src/Test/PlaywrightJourneyScreenshots.php` for a playwright-php journey. Each contains only browser
calls and takes the layout, the sidecar and the overrides from `src/Test/ScreenshotTree.php`. They
do not use the container. `js/` is the Playwright producer of the same tree, and
`resources/capture/metadata.js` is the DOM script that each producer evaluates.

**Review** - `src/Controller/ScreenshotReviewController.php` and `src/Review/*`, the page at
`/_dev/screenshots`. It uses the container and only reads the tree. `templates/prompt.txt.twig`
contains the wording of the brief. `assets/dist/` contains all JavaScript of the page, which the
controller serves; `review.js` is the entry.

## Conventions to keep

- **The DOM metadata script has one source: `resources/capture/metadata.js`.** The PHP traits read
  it at runtime and the npm build copies it into `dist/capture/`. Neither location contains a second
  copy. It must stay an **expression** (no trailing semicolon), because each producer evaluates it
  for its value. The tag set and the attribute list are at the top of the file; the loop body
  applies them. `resources/` is not archive-excluded, so the installed package contains it.
- **The on-disk layout is public API. It is specified in `docs/screenshot-sets.md` and
  `docs/sidecar.schema.json`.** Three producers write it. A change to a grammar, or to the meaning
  of a required field, breaks the other producers and each stored note. An added optional sidecar
  field, mode or viewport does not.
- **Three items change together when the sidecar shape changes:** `MetadataElement`/`ScreenMetadata`,
  `docs/sidecar.schema.json`, and the ajv test in `js/tests/unit/sidecar.spec.ts`. A test enforces
  the schema; it is not only documentation.
- **`tests/Contract/ProducedTreeTest.php` shows that the contract holds.** It runs the PHP reader
  over a tree that a producer wrote, and it skips itself when `QA_SCREENSHOTS_DIR` is not set. If it
  only skips, the contract is not tested, so CI runs it with `--fail-on-skipped` over the tree of
  each producer.
- **`js/package.json` is the only npm package**, `@tacticmedia/qa-capture`, the Playwright
  producer. The review page has no npm package.
- **The five default viewports are one list in two places.** A layout defect usually occurs at one
  width, so the reviewer needs each breakpoint. `ScreenshotTree::screenshotViewports()` and
  `DEFAULT_VIEWPORTS` in `js/src/capture/types.ts` contain the same values in the same order, and
  `tests/Contract/ProducedTreeTest.php` expects ten groups from them. A change goes to both.
- **One clearing producer for each root in each run.** Producers alternate; they do not
  accumulate. `docs/screenshot-sets.md`, "Clearing semantics", is the contract.
- **The review reports the entries it ignored.** `ScreenshotCatalog::ignored()`/`allIgnored()`
  supply a list of ignored entries on the group page and the empty page, and the annotate page
  gives a warning for a sidecar it cannot read. A first error from an external producer must not
  appear as an empty page with no explanation.
- **The prompt is producer-neutral.** It contains no producer name and no framework name. The labels
  are journey and scenario. A host that needs different wording overrides `prompt.txt.twig`.
- **The review page loads no file of the host build, and Composer writes nothing into host assets.**
  Do not add an `assets/package.json`, the `symfony-ux` keyword, or a `require` on a package whose
  Flex recipe changes host assets, such as `symfony/stimulus-bundle`. A host installs the bundle with
  `--dev`, so `composer install --no-dev` removes what such an entry refers to, and the production
  asset build fails. `tests/Host/production-build.sh` shows it.
- **Do not autowire a service that FrameworkBundle registers only for a non-dev package.** A `--dev`
  install makes each dependency of the bundle dev-only, and `ContainerBuilder::willBeAvailable()`
  then reports it as unavailable. For that reason the controller calls
  `Symfony\Component\Clock\now()` and does not inject `ClockInterface`.
- **`symfony/panther` and `playwright-php/playwright` are suggestions and dev dependencies. Do not
  put them in `require`.** Only the capture traits use them. A requirement adds php-webdriver and
  `ext-zip`, or `symfony/process` and a Node bridge, to the container image that serves the review
  page.
- **In the review image, `/data` is the root of the reviewed project.**
  `App\MountedProjectPathsPass` sets the catalog and the cropper to it, so the brief prints paths
  the host can open; `docker/review/README.md`, "The mount contract", gives the host side.
- **`docker/review/composer.lock` is committed.** After a change to the dependencies of the bundle,
  run `composer update` in `docker/review/`. Nothing detects an outdated lock; it breaks the image
  build only where `cache:warmup` or the review page needs the missing package.
- **The capture traits must not use the container.** A project can use the capture without
  registration of the bundle, and that is one of the two functions of the package. Configuration
  comes from `protected static` methods that a test case overrides, and from one environment
  variable. It does not come from bundle configuration.
- **Use one capture trait once, on a shared journey base class.** The clear-once guard is a trait
  static, which PHP copies into each class that uses the trait directly; `docs/host-setup.md`, "The
  capture traits", gives the consequences, including the data-provider limit. Both traits declare
  `captureFullPageScreenshot()`, so one class cannot use both.
- **The playwright-php trait has three library constraints.** The bridge writes the PNG from its own
  working directory, so the path is absolute. A null `colorScheme` is dropped and not sent, so the
  reset is `'no-override'`. A function string runs through `eval()` in the page, which a strict
  content security policy blocks, so each `evaluate()` call passes an expression.
- **The review does not write into the screenshot tree.** Notes and crops are written under
  `review_dir`, and the crops are regenerated on each prompt build.
- **The group grid does not parse a sidecar.** It reads filenames only. A sidecar contains each
  element on the page, so one parse for each card makes a large run slow.
- **The constants of the resolver are in `SelectionResolver`.** `COVERAGE` (0.6), `SLACK` (2 px,
  both the staleness tolerance and the enclosing margin) and the three list caps are what
  `docs/how-it-works.md` step 4 describes. Change the constant and the document together.
- **The prompt prints project-relative paths where it can.** An agent must be able to open them. An
  absolute path is the fallback, not the default.
- **PHP supplies data; templates supply text.** The wording of the prompt is in `prompt.txt.twig`,
  so a host can replace it with its own conventions through a standard bundle template override.
- **Stimulus identifiers use the `qa-` prefix.** `assets/dist/review.js` registers them, and the
  templates write the `data-*` attributes literally. A change to a name breaks the templates and the
  E2E selectors together.
- **`assets/dist/stimulus.js`, `turbo.js` and `csrf_protection.js` are unchanged upstream copies.**
  `docs/development.md` gives the source of each. `csrf_protection.js` selects the token input by
  `data-controller="csrf-protection"`, so each form keeps that attribute; `docs/csrf.md` gives the
  reason.
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

There are three fixture hosts. `tests/Fixtures/app` has no JavaScript build and runs the complete
suite. `tests/Fixtures/encore` and `tests/Fixtures/reprise` each run `BundlerHostTestCase`, which
shows that the default layout works on those hosts with no override.
`RepriseHostTest` skips itself where the package is not installed. In CI that is each matrix job
below PHP 8.4. Locally it skips unless `symfony/reprise` is required.

The `node` CI job builds the npm package, runs its unit specs, captures against the PHP fixture host
and then runs `--group contract` over that tree. The `panther` and `playwright` jobs do the same
with `PantherCaptureE2eTest` and `PlaywrightCaptureE2eTest`, which capture the fixture host with
each PHP trait. The `playwright` job runs `vendor/bin/playwright-install` after `composer update`,
because the library keeps its npm packages in `vendor/`. The `docker` job builds the review image
and tests the empty state on each push and pull request, and publishes to ghcr.io on push. The
`production-build` job runs `tests/Host/production-build.sh` for a host with
`symfony/stimulus-bundle` and for a host without it.

CI runs PHPStan and the non-e2e group on PHP 8.2/Symfony 6.4, 8.3/7.4, 8.4/8.1 and 8.5/8.1. It runs
the e2e group on 8.2/6.4 and 8.5/8.1 only, split between the `panther` job and the `playwright`
job, and installs `symfony/reprise` on the two highest matrix jobs only. A separate style job runs
`composer validate --strict` and `composer cs:check` on 8.5.

To select a branch, use global flex and `composer config extra.symfony.require`, as
`.github/workflows/ci.yaml` does. `composer update` on its own constrains only the packages that
`composer.json` names, and the transitive Symfony packages then resolve to their latest branch. Such
a run does not test the intended versions.

`vendor/bin/phpunit --group e2e` drives Chrome against the same fixture application through the PHP
built-in web server. It tests the behaviour that needs a browser: the selection that records natural
image pixels, the saved marker that returns to the pixels it records when the image is centred or
scaled, the keyboard navigation, and the double-submit of the CSRF token.

Two rules for driving the browser, both used in `tests/E2e/ScreenshotReviewE2eTest.php`:

- Send keys as a W3C key action with no element. `sendKeys` resolves the active element first, and
  that handle goes stale across a Turbo swap; a key action with no element needs no handle. The
  `keyDown()`/`keyUp()` wrappers on `WebDriverActions` in php-webdriver accept modifier keys only,
  so the command is issued directly.
- Wait until the Turbo preview is replaced before you take an element handle. Turbo renders a cached
  preview and then the real response, so a handle taken on a URL match alone can be replaced during
  a drag.

`demo/playwright/` is the Playwright version of the demo journey. It consumes `js/` through a
`file:` dependency with `install-links=true`. A symlinked copy resolves a second `@playwright/test`,
and Playwright does not load twice. After you rebuild `js/`, run `npm install` there again.

`demo/tests/E2e/TacticMediaPlaywrightJourneyE2eTest.php` is the playwright-php version.
`demo/phpunit.dist.xml` has one test suite for each PHP journey, with `panther` as the default,
because each journey empties the tree; `--testsuite playwright` selects the other.

`demo/` is a Symfony 8.1 application on AssetMapper and PHP 8.4 that requires the bundle through a
path repository. Its `vendor/` and `var/` directories are git-ignored, so run `composer install`
there in a new checkout before `demo/bin/console`. The demo shows the result of a Flex install:
`assets/controllers.json` names no controller of the bundle. The demo enables the bundle in each
environment, and it takes the review routes from the `routing.controllers` loader (symfony/routing
7.4 and later), which imports them from the controller service. It therefore does not import
`config/routes.php`, which is the method the README gives to a host. No CI job runs the demo,
PHPStan does not analyse it, and `composer.json` excludes it from the distributed archive.

PHPStan runs at level 8 over `src` and `tests`.
