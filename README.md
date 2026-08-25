# QA bundle

[![Packagist](https://img.shields.io/packagist/v/tacticmedia/qa-bundle)](https://packagist.org/packages/tacticmedia/qa-bundle)
[![PHP](https://img.shields.io/packagist/dependency-v/tacticmedia/qa-bundle/php)](https://packagist.org/packages/tacticmedia/qa-bundle)
[![License](https://img.shields.io/packagist/l/tacticmedia/qa-bundle)](LICENSE)

## TL;DR

Sometimes a broken page doesn't cause an error that can be captured by a machine. A heading that
overlaps a badge; a table that loses its right gutter at one breakpoint; text with too little
contrast in dark mode, and so on. Every assertion passes, and yet the user experience is bad.

Only a person finds those defects, and that person's job is not easy: if you're testing 10 pages for
four different resolutions, two different rendering engines and both dark and light mode, it's 160
screens to review, every single time. How will you scale it to 100 pages?

This bundle is meant to ease the pain: Use the trait to capture all the screenshots and metadata at
the points that matter to you, and use the UI to review and annotate all screens. Once you're done,
generate a prompt for your favourite agent and let the computer compute.

<details>
  <summary>▶️ <b>Click to see it in action</b></summary>
  <br>
  <img src="docs/assets/tacticmedia.webp" alt="Demo Animation" width="100%">
</details>

## How it works

The bundle has two halves. They operate independently.

**Capture** writes a full-page PNG of each settled screen, at five viewports in light and dark, with
a JSON sidecar of element geometry beside each image. `TacticMedia\QaBundle\Test\JourneyScreenshots`
is a PHPUnit trait for a Symfony Panther journey. It does not use the container and does not need
bundle registration. [`@tacticmedia/qa-capture`](js/) performs the same capture from Playwright, for
a project that has no PHP.

**Review** is a page at `/_dev/screenshots` that the host enables in `dev`. It lists the captures,
takes a rectangle and a note on any of them, and generates a plain-text prompt. The page runs in
your application, or in a container over the tree of another project.

The two halves share one on-disk contract, and nothing else: the directory layout and the sidecar
shape, specified in [docs/screenshot-sets.md](docs/screenshot-sets.md) with a JSON Schema at
[docs/sidecar.schema.json](docs/sidecar.schema.json). The review page reads the output of any tool
that writes that layout.

Why is that important? This bundle doesn't dictate how you'll create the screenshots.

## Requirements to run the UI

PHP 8.2 or later, `ext-gd`, and Symfony 6.4, 7.4, or 8.1 and later.

## Quick start

```
composer require --dev tacticmedia/qa-bundle symfony/panther
```

Only the capture trait uses `symfony/panther`, so the bundle lists it as a suggestion and not as a
dependency. A project that needs the review page only can omit it.

Enable it for `dev` only, in `config/bundles.php`:

```php
TacticMedia\QaBundle\TacticMediaQaBundle::class => ['dev' => true],
```

Import the routes under `when@dev`, in `config/routes/qa.yaml`:

```yaml
when@dev:
    qa:
        resource: '@TacticMediaQaBundle/config/routes.php'
        type: php
```

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
| [docs/development.md](docs/development.md) | Test gates, CI legs, the npm package, the review image, the demo. |
| [js/README.md](js/README.md) | `@tacticmedia/qa-capture`, the Playwright producer. |
| [docker/review/README.md](docker/review/README.md) | `ghcr.io/tacticmedia/qa-review`, the review image. |
| [demo/README.md](demo/README.md) | A Symfony 8.1 host that runs both producers and the container. |

## Contributions

Non-LLM-slop contributions and issues are most definitely welcome.

## License

MIT. See [LICENSE](LICENSE).

## One more thing

This package is brought to you by [Tactic Media, a South Australian software development
business](https://tacticmedia.com.au).

We love to help businesses become more efficient by automating tasks that shouldn't have been done
by a human in the first place.

Head over to our website to check out what we do, and if you think we can help you give your
employees more time to spend on something more creative,
[let's talk](https://tacticmedia.com.au/contact.html).
