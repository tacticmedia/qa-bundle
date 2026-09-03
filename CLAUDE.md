# qa-bundle

## Purpose

The README states the problem and the two halves. `docs/how-it-works.md` gives the design in seven
steps, from the two-pass measurement to the prompt template, and `docs/screenshot-sets.md` is the
on-disk contract between the producers and the review. This file contains what those documents do
not: where the code is, the conventions that keep the two producers and the review consistent, and
how the test suite is laid out.

## Where the code is

**Capture** - `src/Test/JourneyScreenshots.php`, a PHPUnit trait for a Panther journey. It does not
use the container. `js/` is the Playwright producer of the same tree, and
`resources/capture/metadata.js` is the DOM script that both producers evaluate.

**Review** - `src/Controller/ScreenshotReviewController.php` and `src/Review/*`, the page at
`/_dev/screenshots`. It uses the container and only reads the tree. `templates/prompt.txt.twig`
contains the wording of the brief.

## Conventions to keep

- **The DOM metadata script has one source: `resources/capture/metadata.js`.** The PHP trait reads
  it at runtime and the npm build copies it into `dist/capture/`. Neither location contains a second
  copy. It must stay an **expression** (no trailing semicolon), because both producers evaluate it
  for its value. The tag set and the attribute list are at the top of the file; the loop body
  applies them. `resources/` is not archive-excluded, so the installed package contains it.
- **The on-disk layout is public API. It is specified in `docs/screenshot-sets.md` and
  `docs/sidecar.schema.json`.** Two producers write it. A change to a grammar, or to the meaning of
  a required field, breaks the other producer and each stored note. An added optional sidecar field,
  mode or viewport does not.
- **Three items change together when the sidecar shape changes:** `MetadataElement`/`ScreenMetadata`,
  `docs/sidecar.schema.json`, and the ajv test in `js/tests/unit/sidecar.spec.ts`. A test enforces
  the schema; it is not only documentation.
- **`tests/Contract/ProducedTreeTest.php` shows that the contract holds.** It runs the PHP reader
  over a tree that the npm package wrote, and it skips itself when `QA_SCREENSHOTS_DIR` is not set.
  If it only skips, the contract is not tested.
- **There are two npm packages, with two different functions.** `assets/package.json` is
  `@tacticmedia/qa-bundle`, the Stimulus controllers that the review page loads. `js/package.json`
  is `@tacticmedia/qa-capture`, the Playwright producer. Do not use either name for the other
  package.
- **The five default viewports are one list in two places.** A layout defect usually occurs at one
  width, so the reviewer needs each breakpoint. `JourneyScreenshots::screenshotViewports()` and
  `DEFAULT_VIEWPORTS` in `js/src/capture/types.ts` contain the same values in the same order, and
  `tests/Contract/ProducedTreeTest.php` expects ten groups from them. A change goes to both.
- **One clearing producer for each root in each run.** The two producers alternate; they do not
  accumulate. `docs/screenshot-sets.md`, "Clearing semantics", is the contract.
- **The review reports the entries it ignored.** `ScreenshotCatalog::ignored()`/`allIgnored()`
  supply a list of ignored entries on the group page and the empty page, and the annotate page
  gives a warning for a sidecar it cannot read. A first error from an external producer must not
  appear as an empty page with no explanation.
- **The prompt is producer-neutral.** It contains no producer name and no framework name. The labels
  are journey and scenario. A host that needs different wording overrides `prompt.txt.twig`.
- **`symfony/panther` is a suggestion and a dev dependency. Do not put it in `require`.** Only the
  capture trait uses it, and a requirement adds php-webdriver and `ext-zip` to the container image
  that serves the review page.
- **In the review image, `/data` is the root of the reviewed project.**
  `App\MountedProjectPathsPass` sets the catalog and the cropper to it, so the brief prints paths
  the host can open; `docker/review/README.md`, "The mount contract", gives the host side.
- **`docker/review/composer.lock` is committed.** After a change to the dependencies of the bundle,
  run `composer update` in `docker/review/`. Nothing detects an outdated lock; it breaks the image
  build only where `cache:warmup` or the review page needs the missing package.
- **The trait must not use the container.** A project can use the capture without registration of
  the bundle, and that is one of the two functions of the package. Configuration comes from
  `protected static` methods that a test case overrides, and from one environment variable. It does
  not come from bundle configuration.
- **Use the trait once, on a shared journey base class.** The clear-once guard is a trait static,
  which PHP copies into each class that uses the trait directly; `docs/host-setup.md`, "The capture
  trait", gives the consequences, including the data-provider limit.
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
- **Stimulus identifiers use the `qa-` prefix.** They are set in `assets/package.json` under
  `symfony.controllers[*].name`, which replaces the name that the loader derives from the package
  name. Without them, each target attribute reads
  `data-tacticmedia--qa-bundle--annotate-target`. A change to a name breaks the templates and the
  E2E selectors together.
- **Keep the `symfony-ux` keyword in composer.json.** Flex's `PackageJsonSynchronizer` selects
  packages by it; without it the next `composer update`, `require` or `remove` removes the block
  from the host, and the page stops responding. `docs/host-setup.md`, "Stimulus controllers", gives
  what Flex writes and when.
- **`assets/dist` reaches AssetMapper only from `prependExtension()`.** Nothing else registers the
  path: `StimulusExtension::prepend()` registers only stimulus-bundle's own directory, and there is
  no cache warmer. Composer writes the `controllers.json` block for every environment, so the recipe
  registers `dev` and `test`, and `tests/Functional/StimulusControllersMapTest.php` fails if the
  registration or a controller name moves. `docs/host-setup.md`, "The bundle and controllers.json
  must agree", gives the error and the host states.
- **`peerDependencies` in `assets/package.json` is what Flex copies into a bundler host's
  `devDependencies`.** Without them `@hotwired/turbo` does not resolve on a Webpack Encore or
  Reprise host and the annotate controller does not load; an AssetMapper host takes
  `symfony.importmap` instead. `docs/frontend.md` gives the host side.
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
  controller by that name, and the bundle does not supply it; if the host renames it, each write has
  no effect. `docs/csrf.md` gives which recipe ships it and where it is effective.
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
the `docs/frontend.md` snippet apart from its leading comment. The file in the Reprise fixture
replaces only the entry-tag function that `docs/frontend.md` names for that host. Keep both files
in that state, or the documented override is untested.
`RepriseHostTest` skips itself where the package is not installed. In CI that is each matrix job
below PHP 8.4. Locally it skips unless `symfony/reprise` is required.

The `node` CI job builds the npm package, runs its unit specs, captures against the PHP fixture host
and then runs `--group contract` over that tree. The `docker` job builds the review image and tests
the empty state on each push and pull request, and publishes to ghcr.io on push.

CI runs PHPStan and the non-e2e group on PHP 8.2/Symfony 6.4, 8.3/7.4, 8.4/8.1 and 8.5/8.1. It runs
the e2e group on 8.2/6.4 and 8.5/8.1 only, and installs `symfony/reprise` on the two highest
matrix jobs only. A separate style job runs `composer validate --strict` and `composer cs:check`
on 8.5.

To select a branch, use global flex and `composer config extra.symfony.require`, as
`.github/workflows/ci.yaml` does. `composer update` on its own constrains only the packages that
`composer.json` names, and the transitive Symfony packages then resolve to their latest branch. Such
a run does not test the intended versions.

`vendor/bin/phpunit --group e2e` drives Chrome against the same fixture application through the PHP
built-in web server. It tests the behaviour that needs a browser: the selection that records natural
image pixels, the saved marker that returns to the pixels it records when the image is centred or
scaled, and the keyboard navigation.

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

`demo/` is a Symfony 8.1 application on AssetMapper and PHP 8.4 that requires the bundle through a
path repository. Its `vendor/` and `var/` directories are git-ignored, so run `composer install`
there in a new checkout before `demo/bin/console`. The demo shows the result of a Flex install:
`assets/controllers.json` contains the four controllers and `importmap.php` contains the two
Hotwired packages. The demo enables the bundle in each environment, and it takes the review routes
from the `routing.controllers` loader (symfony/routing 7.4 and later), which imports them from the
controller service. It therefore does not import `config/routes.php`, which is the method the README
gives to a host. No CI
job runs the demo, PHPStan does not analyse it, and `composer.json` excludes it from the distributed
archive.

PHPStan runs at level 8 over `src` and `tests`.
