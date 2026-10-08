# QA bundle

[![Packagist](https://img.shields.io/packagist/v/tacticmedia/qa-bundle)](https://packagist.org/packages/tacticmedia/qa-bundle)
[![PHP](https://img.shields.io/packagist/dependency-v/tacticmedia/qa-bundle/php)](https://packagist.org/packages/tacticmedia/qa-bundle)
[![License](https://img.shields.io/packagist/l/tacticmedia/qa-bundle)](LICENSE)

## TL;DR

A broken page does not always cause an error that a machine can capture. Examples: a heading that
overlaps a badge, a table that loses its right gutter at one breakpoint, text with too little
contrast in dark mode. Every assertion passes, and the user experience is still bad.

Only a person finds those defects, and the work is large: 10 pages at four resolutions, in two
rendering engines and in dark and light mode, are 160 screens to review on each run, and the count
grows with each page you add.

This bundle reduces that work: use the trait to capture all the screenshots and metadata at the
points that matter to you, and use the UI to review and annotate all screens. When the review is
complete, generate a prompt for a coding agent.

<details>
  <summary><b>Click to see a demonstration</b></summary>
  <br>
  <img src="docs/assets/tacticmedia.webp" alt="Demo Animation" width="100%">
</details>

## How it works

The bundle has two halves. They operate independently.

**Capture** writes a full-page PNG of each settled screen, at five viewports in light and dark, with
a JSON sidecar of element geometry beside each image. Two PHPUnit traits do this:
`TacticMedia\QaBundle\Test\JourneyScreenshots` for a Symfony Panther journey, and
`TacticMedia\QaBundle\Test\PlaywrightJourneyScreenshots` for a
[playwright-php](https://github.com/playwright-php/playwright) journey. They do not use the
container and do not need bundle registration. [`@tacticmedia/qa-capture`](js/) performs the same
capture from Playwright, for a project that has no PHP.

**Review** is a page at `/_dev/screenshots` that the host enables in `dev`. It lists the captures,
takes a rectangle and a note on any of them, and generates a plain-text prompt. The page runs in
your application, or in a container over the tree of another project.

The two halves share one on-disk contract, and nothing else: the directory layout and the sidecar
shape, specified in [docs/screenshot-sets.md](docs/screenshot-sets.md) with a JSON Schema at
[docs/sidecar.schema.json](docs/sidecar.schema.json). The review page reads the output of any tool
that writes that layout.

## Requirements to run the UI

PHP 8.2 or later, `ext-gd`, and Symfony 6.4, 7.4, or 8.1 and later.

## Quick start

```
composer require --dev tacticmedia/qa-bundle symfony/panther
```

To capture with playwright-php instead, which needs Node.js 20 or later:

```
composer require --dev tacticmedia/qa-bundle playwright-php/playwright
vendor/bin/playwright-install chromium
```

Only the capture traits use `symfony/panther` and `playwright-php/playwright`, so the bundle lists
them as suggestions and not as dependencies. A project that needs the review page only can omit
both.

The bundle requires `symfony/security-csrf`. On a host that has no other dependency on that
package, a `--dev` install makes it dev-only, FrameworkBundle then leaves CSRF protection off, and
the review page does not render. [docs/csrf.md](docs/csrf.md) gives the setting.

The Flex recipe writes the next two files. If the host does not install the recipe, write them
yourself.

Enable the bundle for `dev` and `test`, in `config/bundles.php`:

```php
TacticMedia\QaBundle\TacticMediaQaBundle::class => ['dev' => true, 'test' => true],
```

Import the routes under `when@dev`, in `config/routes/qa.yaml`:

```yaml
when@dev:
    qa:
        resource: '@TacticMediaQaBundle/config/routes.php'
        type: php
```

The bundle must be registered in every environment whose container is built while
`assets/controllers.json` names it, and Composer writes that file for every environment. A host
without the line fails with `Could not find an asset mapper path that points to the "annotate"
controller`. [docs/host-setup.md](docs/host-setup.md#the-bundle-and-controllersjson-must-agree)
describes the two host states that satisfy this, and the `--no-scripts` sequence for a host that
has no recipe.

Use the trait once, on your journey base class:

```php
use TacticMedia\QaBundle\Test\JourneyScreenshots;

abstract class JourneyTestCase extends PantherTestCase
{
    use JourneyScreenshots;
}
```

Call `captureFullPageScreenshot($client, 'Cart with two items')` at each point in a journey where
the screen has settled. Run the journeys, start the application, and open `/_dev/screenshots`.

With playwright-php, use the other trait and give the method the page:

```php
use Playwright\Testing\PlaywrightTestCase;
use TacticMedia\QaBundle\Test\PlaywrightJourneyScreenshots;

abstract class JourneyTestCase extends PlaywrightTestCase
{
    use PlaywrightJourneyScreenshots;
}
```

Call `captureFullPageScreenshot($this->page, 'Cart with two items')`. playwright-php does not start
the application, so serve it before the journeys run.
[docs/host-setup.md](docs/host-setup.md#the-capture-traits) gives the details.

Flex writes the Stimulus controllers into `assets/controllers.json` during installation, but only
where that file exists. Without the block the page renders and does not respond to a selection or
to a key. [docs/host-setup.md](docs/host-setup.md) gives the block and the rest of the host setup.

## Documentation

| Document | Contents |
| --- | --- |
| [docs/host-setup.md](docs/host-setup.md) | `controllers.json`, the trait, configuration, firewall, prompt wording. |
| [docs/csrf.md](docs/csrf.md) | CSRF on each Symfony branch: the stateless token id and the `csrf-protection` controller. |
| [docs/frontend.md](docs/frontend.md) | The page's JavaScript on AssetMapper, Encore and Reprise, styling, content security policy. |
| [docs/review.md](docs/review.md) | The review page: directories, keyboard, coordinates. |
| [docs/how-it-works.md](docs/how-it-works.md) | The design: measurement, the sidecar, the basename, selection resolution, crops, notes. |
| [docs/without-php.md](docs/without-php.md) | Capture from Playwright and review in a container. |
| [docs/screenshot-sets.md](docs/screenshot-sets.md) | The on-disk contract between producers and the review, with [sidecar.schema.json](docs/sidecar.schema.json). |
| [docs/development.md](docs/development.md) | Test commands, CI matrix jobs, the npm package, the review image, the demo. |
| [js/README.md](js/README.md) | `@tacticmedia/qa-capture`, the Playwright producer. |
| [docker/review/README.md](docker/review/README.md) | `ghcr.io/tacticmedia/qa-review`, the review image. |
| [demo/README.md](demo/README.md) | A Symfony 8.1 host that runs each producer and the container. |

## Contributions

Contributions and issues that are not LLM slop are welcome.

## License

MIT. See [LICENSE](LICENSE).

## About Tactic Media

[Tactic Media, a South Australian software development business](https://tacticmedia.com.au),
maintains this package.

We help businesses become more efficient by automating tasks that a human should not have to do.

Visit our website to see what we do. If you think we can help your employees spend more time on
creative work, [contact us](https://tacticmedia.com.au/contact.html).
