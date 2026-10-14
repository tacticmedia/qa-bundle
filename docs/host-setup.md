# Host setup

What a Symfony host must have after `composer require`. The [README](../README.md) gives the
install commands. Two subjects have separate documents: [csrf.md](csrf.md) for the token setup
on each Symfony branch, and [frontend.md](frontend.md) for the JavaScript, the styling and the
content security policy.

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
