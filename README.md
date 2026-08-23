# tacticmedia/qa-bundle

Full-page screenshot capture for Symfony Panther journeys, and a dev-only review page that turns a
human's visual feedback into a brief for a coding agent.

Two halves, usable independently:

- **Capture.** `TacticMedia\QaBundle\Test\JourneyScreenshots` - a PHPUnit trait.
  `captureFullPageScreenshot()` shoots the settled screen at every configured viewport and colour
  scheme, and writes a `.json` sidecar of element geometry beside each PNG. No container, no bundle
  needed. [`@tacticmedia/qa-capture`](js/) does the same from Playwright, for projects with no PHP.
- **Review.** `/_dev/screenshots` - the review page. Browse the captures, drag a rectangle over what
  is wrong, write a note, and generate a plain-text prompt that names the journey, the scenario, the
  screen, the page URL, the elements under the selection and a cropped image of it. It runs in this
  application, or as a container over any project's tree.

The two halves share nothing but a directory layout, written down in
[docs/screenshot-sets.md](docs/screenshot-sets.md). Any tool that writes that layout can be
reviewed.

Requires PHP 8.2+, `ext-gd`, and Symfony 6.4, 7.4 or 8.1 - the branches Symfony still supports. A
6.4 or 7.4 application can shoot a baseline before an upgrade and the same screens after it.

## Install

```
composer require --dev tacticmedia/qa-bundle symfony/panther
```

`symfony/panther` is only needed for the capture trait, so it is a suggestion here rather than a
dependency - a project that only wants the review page leaves it out.

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

Composer writes the Stimulus controllers into `assets/controllers.json` for you: the package carries
the `symfony-ux` keyword, so Flex's package synchronizer adds the block on install and keeps it on
every later run. Confirm it landed - **without it the page renders and nothing responds to a drag or
a key**:

```json
{
    "controllers": {
        "@tacticmedia/qa-bundle": {
            "annotate": { "enabled": true, "fetch": "eager" },
            "clipboard": { "enabled": true, "fetch": "lazy" },
            "confirm": { "enabled": true, "fetch": "lazy" },
            "anchor-highlight": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

That synchronizer rewrites the whole file from the installed `symfony-ux` packages, so an entry added
by hand for a package without the keyword is dropped on the next composer run.

Use the trait from your journey base class:

```php
use TacticMedia\QaBundle\Test\JourneyScreenshots;

abstract class JourneyTestCase extends PantherTestCase
{
    use JourneyScreenshots;
}
```

The capture drives Chrome's DevTools protocol. A client that carries no `RemoteWebDriver` is a
silent no-op, and a stage label repeated within one test is captured once - a journey helper
called twice shoots its screen once.

## Configuration

```yaml
when@dev:
    qa:
        screenshots_dir: '%kernel.project_dir%/var/screenshots'
        review_dir: '%kernel.project_dir%/var/review'
```

Both default to the values above. `review_dir` holds the notes file and the generated crops, and
must sit outside `screenshots_dir`, which every run empties - the bundle refuses a configuration
where it does not.

The capture trait never reads this configuration - it runs inside PHPUnit, with no container. It
takes its root from `QA_SCREENSHOTS_DIR`, falling back to `getcwd().'/var/screenshots'`. Point
the two at the same tree.

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

The orientation directory follows from each viewport's shape. The review page discovers whatever the
tree holds, so a new viewport or a third colour scheme needs no configuration.

## What the host has to provide

**A firewall that lets `/_dev/` through, where the host has one.** The page has no authentication
of its own and needs nothing from SecurityBundle:

```yaml
security:
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt|dev)|css|images|js)/
            security: false
            stateless: true
```

**`framework.csrf_protection` enabled.** The write actions validate the token through
`AbstractController::isCsrfTokenValid()`, which resolves `security.csrf.token_manager` from
FrameworkBundle. With CSRF protection switched off that service is absent and every write is
rejected.

**The recipe's `csrf-protection` Stimulus controller, on Symfony 7.2 and up.** There the bundle
prepends `screenshot-review` to `framework.csrf_protection.stateless_token_ids`, and its forms carry
`{{ stimulus_controller('csrf-protection') }}` on the hidden token input. `SameOriginCsrfTokenManager`
never downgrades: once a session has validated a request that carried the double-submit cookie, every
later request from it must carry one too. Any browser that has used your application is in that state,
so without that controller the POSTs are rejected - invisibly, because the redirect still happens and
it reads as a page refresh where the write never occurred. The controller ships with the
`symfony/stimulus-bundle` recipe as `assets/controllers/csrf_protection_controller.js` - Encore
hosts receive it through that same recipe; do not rename it.

Symfony 6.4 and 7.0 have no stateless token ids, so there the token is session-backed and that
controller does not exist. Sessions must be enabled, and `/_dev/screenshots` must be able to start
one, or every write is rejected as an invalid token.

**A running Stimulus application.** Whatever builds your JavaScript, the page needs the host's
Stimulus application started, and the four `qa-*` controllers registered in it. StimulusBundle's Twig
helpers are bundler-neutral, so the markup is the same everywhere; only the delivery differs.

On **AssetMapper** there is nothing to do. Flex writes the `controllers.json` block and adds
`@hotwired/stimulus` and `@hotwired/turbo` to `importmap.php`, and the bundle registers `assets/dist`
as an AssetMapper path itself.

On **Webpack Encore** and **Symfony Reprise** the controllers are resolved from `node_modules`
instead. Flex writes the same `controllers.json` block, and adds the package link and its peers to
`package.json`:

```json
{
    "devDependencies": {
        "@hotwired/stimulus": "^3.0.0",
        "@hotwired/turbo": "^8.0.0",
        "@tacticmedia/qa-bundle": "file:vendor/tacticmedia/qa-bundle/assets"
    }
}
```

Run `npm install` and rebuild after `composer require`. `@hotwired/turbo` is not optional:
`annotate_controller.js` imports `visit` at module scope, so an unresolved Turbo takes the whole
annotate controller down, not only keyboard navigation.

Those two hosts also have to say how their JavaScript reaches the page, because the default `scripts`
block calls `importmap()`. Override the layout:

```twig
{# templates/bundles/TacticMediaQaBundle/layout.html.twig #}
{% extends '@!TacticMediaQa/layout.html.twig' %}

{% block scripts %}
    {{ encore_entry_script_tags('app') }}
{% endblock %}
```

`{{ reprise_entry_script_tags('app') }}` for Reprise. Name the entrypoint that starts your Stimulus
application; `app` is only the recipe default. `@!` is TwigBundle's reference to the bundle's own copy
of the template, so the override can extend the file it replaces.

`symfony/reprise` needs PHP 8.4 and Symfony 7.4 or 8.x, so a 6.4 or 7.4 host is on Encore or
AssetMapper.

**Styling.** The bundle's default layout loads Tailwind from a CDN and ships no compiled CSS. Under a
strict content security policy that script is blocked, so override the layout with your own shell:

```
templates/bundles/TacticMediaQaBundle/layout.html.twig
```

The blocks meant for that are `title`, `brand`, `styles`, `scripts` and `sidebar`; page content
arrives in `review_content`.

```twig
{% extends '@!TacticMediaQa/layout.html.twig' %}

{% block styles %}
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
{% endblock %}
```

`asset()` is `symfony/asset`, which AssetMapper does not require - install it where the override
needs it.

If your Tailwind build should pick up the bundle's own markup, add its templates as a source. A
`vendor/` directory in your `.gitignore` does not hide them: Tailwind filters what it discovers by
itself, not a path you name.

```css
@source "../../vendor/tacticmedia/qa-bundle/templates";
```

`demo/` is that override, working, on an AssetMapper host: `demo/README.md` has the walk-through.

**Prompt wording.** `prompt.txt.twig` is deliberately generic. Put your project's conventions,
document paths and test command in
`templates/bundles/TacticMediaQaBundle/prompt.txt.twig`.

The annotate controller assigns overlay geometry through the CSSOM, which `style-src` does not gate,
and previews need no `blob:` sources, so a strict policy needs no relaxation beyond the stylesheet.

## Running the page

Journeys write into `screenshots_dir`; the review reads it. When the two run in different containers,
bind-mount both directories from the host so a review survives a rebuild and the crops are readable
from the checkout.

```
https://localhost/_dev/screenshots
```

| Key | Moves to |
| --- | --- |
| Left / Right | The previous / next capture in this group. |
| Up / Down | The same capture one group up / down the sidebar, or that group's grid when it lacks this capture. |
| Escape | The selection, the focused field, then the group grid, in that order. |
| Cmd+Enter, Ctrl+Enter | Saves the note being written. |

Both axes wrap. Coordinates are stored in natural image pixels, so a note keeps its meaning whatever
width the page renders the screenshot at - and `y` is measured from the top of the page, not the top
of the viewport.

## Using it without PHP

The review page reads a directory tree and nothing else, so neither half needs a PHP toolchain on
the host.

Capture from Playwright:

```
npm i -D @tacticmedia/qa-capture @playwright/test
```

```ts
// playwright.config.ts
import { createRequire } from 'node:module';

export default defineConfig({
    globalSetup: createRequire(import.meta.url).resolve('@tacticmedia/qa-capture/playwright/global-setup'),
});
```

```ts
// tests/home.spec.ts
import { test } from '@tacticmedia/qa-capture/playwright';

test('a visitor reads the company site', async ({ page, qaScreenshots }) => {
    await page.goto('https://tacticmedia.com.au/');
    await qaScreenshots.capture('Home');
});
```

The global setup empties the tree once before any worker starts; the fixture shoots every viewport
and colour scheme. Defaults and overrides (`qaScreenshotsOptions`) are in [js/README.md](js/README.md).

Review in a container:

```yaml
# compose.yaml
services:
  review:
    image: ghcr.io/tacticmedia/qa-review:latest
    ports:
      - "127.0.0.1:8000:8000"
    volumes:
      - ./var/screenshots:/data/var/screenshots
      - ./var/review:/data/var/review
```

```
docker compose up review
open http://localhost:8000/_dev/screenshots
```

**`/data` is your project root as the container sees it.** Mount each tree at the path it has on the
host, so the generated brief names paths you can open. `QA_SCREENSHOTS_DIR`, `QA_REVIEW_DIR` and
`QA_PROJECT_ROOT` override the defaults at runtime. The page has no authentication, which is why the
port is bound to `127.0.0.1`; on Linux the container writes as uid 1000, so add
`user: "${UID:-1000}:${GID:-1000}"` to own the notes yourself. Details in
[docker/review/README.md](docker/review/README.md).

Capture never runs in the container: browsers live on the host, and Playwright manages its own.

## Demo

`demo/` is a runnable end-to-end example: a stock Symfony 8.1 application whose single Panther
journey traverses https://tacticmedia.com.au, captures every screen at five viewports in light and
dark, and serves the review page over the captured tree. `demo/playwright/` is the same journey
written for Playwright, writing the same tree, so you can switch producers and see an identical
review. `demo/README.md` walks through both, and through reading the tree from the container.

## Development

```
composer install
vendor/bin/phpstan analyse
vendor/bin/phpunit --exclude-group e2e
composer cs:check
vendor/bin/bdi detect drivers && vendor/bin/phpunit --group e2e
```

CI runs PHPStan and the non-E2E tests on PHP 8.2/Symfony 6.4, 8.3/7.4, 8.4/8.1 and 8.5/8.1, the
E2E group on 8.2/6.4 and 8.5/8.1, and the style check on 8.5. To reproduce one leg, pin the
branch the way the workflow does - a plain `composer update` pins only what `composer.json` names and
lets the transitive Symfony packages float to their latest branch:

```
composer global require symfony/flex
composer config extra.symfony.require 6.4.*
composer update
```

The E2E group drives a real Chrome against `tests/Fixtures/app`, served by the PHP built-in web
server.

The capture package and the review image have their own gates:

```
cd js && npm ci && npm run build && npm test
QA_SCREENSHOTS_DIR=/tmp/shots npm run test:integration
QA_SCREENSHOTS_DIR=/tmp/shots vendor/bin/phpunit --group contract

docker build -f docker/review/Dockerfile -t qa-review:local .
```

The `contract` group is the one that matters most: it runs the real reader over a tree no PHP wrote,
which is what keeps the layout a contract rather than one implementation's habit. It skips itself
without `QA_SCREENSHOTS_DIR`.

## License

MIT.
