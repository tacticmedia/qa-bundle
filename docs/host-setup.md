# Host setup

What a Symfony host must have after `composer require`. The [README](../README.md) gives the
install commands. Two subjects have separate documents: [csrf.md](csrf.md) for the token setup
on each Symfony branch, and [frontend.md](frontend.md) for the JavaScript, the styling and the
content security policy.

## Stimulus controllers

Composer writes the Stimulus controllers into `assets/controllers.json`. The package has the
`symfony-ux` keyword, so the Flex package synchronizer adds the block on `composer require` and
rewrites it on each later `composer update`, `require` or `remove`; `composer install` leaves the
file as committed. Flex writes the block only where the host already has `assets/controllers.json`
and a root `package.json` or `importmap.php`. It does not create the file. Confirm that the block is
present, and write it manually if the file did not exist. **Without the block the page renders, but
it does not respond to a selection or to a key**:

```json
{
    "controllers": {
        "@tacticmedia/qa-bundle": {
            "annotate": { "enabled": true, "fetch": "eager" },
            "clipboard": { "enabled": true, "fetch": "lazy" },
            "confirm": { "enabled": true, "fetch": "lazy" },
            "anchor-highlight": { "enabled": true, "fetch": "lazy" }
        }
    },
    "entrypoints": []
}
```

The synchronizer rebuilds the `controllers` block from the packages in `composer.lock` that have the
keyword and a `package.json` under `vendor/`, keeps the `enabled` and `fetch` values the host set,
and keeps `entrypoints` unchanged. It removes an entry it cannot match to such a package on the next
`composer update`, `require` or `remove`.

## The bundle and controllers.json must agree

`assets/controllers.json` names the package for every environment, because Composer writes it and
Composer does not distinguish environments. The AssetMapper path that each entry resolves against
comes from `TacticMediaQaBundle::prependExtension()`, which runs only where `config/bundles.php`
enables the bundle. Where the two disagree, StimulusBundle stops the container build:

```
Could not find an asset mapper path that points to the "annotate" controller
in package "tacticmedia/qa-bundle", defined in controllers.json.
```

**Wherever `assets/controllers.json` names the package, the bundle must be registered.** Flex
rewrites the file on `composer update`, `require` and `remove` only; `composer install`, with or
without `--no-dev`, leaves it as committed. Two host states satisfy that:

- `composer require` with `['all' => true]`. The block and the registration then exist in every
  environment, and a pipeline can run `asset-map:compile` with or without the development
  dependencies. The demo uses this state.
- `composer require --dev` with `['dev' => true, 'test' => true]`. A production build then finds
  the block while nothing registers the path, so remove it first with a command that runs the
  synchronizer against the reduced vendor tree:

  ```
  composer install --no-dev
  composer update --no-dev --lock
  bin/console asset-map:compile
  ```

  A later `composer install` in a development checkout does not restore the block;
  `composer update --lock` does.

`test` is as necessary as `dev`. A host that opens the page from a `WebTestCase` builds a container
from the same `controllers.json`.

To recover a host that already shows the error, add the missing line to `config/bundles.php` and run
`bin/console cache:clear`. To install into a host that has no recipe, run Composer with no scripts
until the line exists, then run the synchronizer to write the block:

```
composer require --dev --no-scripts tacticmedia/qa-bundle symfony/panther
# add the bundles.php line and the routes
composer update --lock
```

A `composer install --no-dev` after a `--dev` install leaves the block in the committed file after
the package is removed, and the reader then reports the package instead of the path:

```
Could not find package "tacticmedia/qa-bundle" referred to from controllers.json.
```

## The capture traits

There is one trait for each PHP browser driver. Both write the same tree and sidecars, and both
take the configuration below.

| Trait | Driver | Method |
| --- | --- | --- |
| `JourneyScreenshots` | `symfony/panther` | `captureFullPageScreenshot(Client $client, string $label)` |
| `PlaywrightJourneyScreenshots` | `playwright-php/playwright` 1.4 or later | `captureFullPageScreenshot(PageInterface $page, string $label)` |

Use one capture trait on one shared journey base class only. The clear-once guard is a trait
static, which PHP copies into each class that uses the trait directly, so a second such class
empties the tree again during the run. The two traits declare the same method, so one class cannot
use both. A host with a Panther base class and a Playwright base class gives each its own root, as
"Clearing semantics" in [screenshot-sets.md](screenshot-sets.md) requires. A test that uses a data
provider captures under one scenario name, so each data set overwrites the captures of the one
before it.

`captureFullPageScreenshot()` captures the settled screen at each viewport and colour scheme. The
label becomes part of the basename, and the brief names it. A stage label that occurs more than once
in a test is captured once, so a journey helper that is called twice captures its screen once. After
the capture, the page has its original viewport and the browser default colour scheme again.

`JourneyScreenshots` uses the Chrome DevTools protocol, so the journey must run a Chrome client.

`PlaywrightJourneyScreenshots` uses the Playwright page API and is tested in Chromium. It needs:

- Node.js 20 or later, and `vendor/bin/playwright-install chromium` after each `composer install`
  that replaces `vendor/`, because the library installs its npm packages into
  `vendor/playwright-php/playwright/bin/`. In CI, add `--with-deps`.
- A page with a fixed viewport. A browser context created with `'viewport' => null` fails the
  capture.
- A running application. playwright-php does not start a web server the way `PantherTestCase` does,
  so start the application before the journeys and use absolute URLs, or a `baseURL` context option.

`Playwright\Testing\PlaywrightTestCase` from the library gives each test a page in `$this->page`.
The trait accepts any `Playwright\Page\PageInterface`, so a test case that creates its own browser
works too.

In playwright-php 1.5.0, `getByRole()` writes a name that is not exact as a regular expression,
`/name/i`. When that name contains an unpaired quote character (`'`, `"` or a backtick) and the
locator continues with `first()`, `nth()` or a further locator, Playwright cannot parse the
selector. `click()` does not report the parse error: it waits 30 seconds and then reports
`Element not actionable`. Pass `'exact' => true` for such a name:

```php
$page->getByRole('link', ['name' => "Let's Talk", 'exact' => true])->first()->click();
```

## Configuration

```yaml
when@dev:
    qa:
        screenshots_dir: '%kernel.project_dir%/var/screenshots'
        review_dir: '%kernel.project_dir%/var/review'
```

Both options default to the values above. `review_dir` contains the notes file and the generated
crops. It must be outside `screenshots_dir`, because each run empties `screenshots_dir`. The bundle
rejects a configuration in which `review_dir` is inside `screenshots_dir`.

The capture traits do not read this configuration, because they run in PHPUnit with no container.
They take their root from `QA_SCREENSHOTS_DIR`, or from `getcwd().'/var/screenshots'` when that
variable is not set. A relative root resolves against the working directory of PHP. Set both to the
same tree.

Override what is captured on your journey base class:

```php
protected static function screenshotViewports(): array
{
    return [['width' => 1440, 'height' => 900]];
}

protected static function screenshotColorSchemes(): array
{
    return ['light'];
}
```

The orientation directory follows from the shape of each viewport. The review page reads the
directories that the tree contains, so an added viewport or a third colour scheme needs no
configuration, if the directory names agree with the grammar in
[docs/screenshot-sets.md](screenshot-sets.md).

## A firewall that permits `/_dev/`

The page has no authentication and does not use SecurityBundle. Where the host has a firewall:

```yaml
security:
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt|dev)|css|images|js)/
            security: false
            stateless: true
```

## Prompt wording

`prompt.txt.twig` contains general wording. Put the conventions, document paths and test command of
your project in `templates/bundles/TacticMediaQaBundle/prompt.txt.twig`.
