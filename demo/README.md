# qa-bundle demo

A Symfony 8.1 application on AssetMapper and PHP 8.4 that consumes the bundle through a Composer
path repository. It contains one Panther journey,
`tests/E2e/TacticMediaJourneyE2eTest.php`, which opens each page of https://tacticmedia.com.au and
captures each screen after it settles. It also serves the review page for the result. The
application has no pages of its own. Apart from the `/_error` preview from the framework, each route
it serves is the `/_dev/screenshots*` route of the bundle.

`playwright/` contains the same journey for Playwright and writes the same tree. Each producer gives
the same six labelled screens in the review, which shows that the layout in
`docs/screenshot-sets.md` is a contract and not a description of the PHP trait. The basenames
contain the name of the test that produced them, so a note written against the tree of one producer
does not match the tree of the other.

## Requirements

- PHP 8.4 or newer with `ext-gd`
- Google Chrome
- Network access to https://tacticmedia.com.au

## Set up

```
composer install
vendor/bin/bdi detect drivers
```

`bdi` downloads the chromedriver for the installed version of Chrome into `drivers/`, where Panther
reads it.

`composer install` also compiles the stylesheet, because `tailwind:build` is one of the
`auto-scripts` and `post-install-cmd` and `post-update-cmd` run it. The first run downloads the
selected version of the standalone Tailwind binary into `var/tailwind/`. Outside the `test`
environment, `symfonycasts/tailwind-bundle` sets `strict_mode` to on by default. A checkout with no
compiled stylesheet therefore returns an exception for the review page instead of markup with no
styles.

## Capture

```
vendor/bin/phpunit
```

The journey opens the site, follows the navigation links and calls `captureFullPageScreenshot()` at
each settled screen. Each capture is written to `var/screenshots/<mode>/<WxH>/<orientation>/` at
five viewports in light and dark, which are the defaults of the trait. Each run empties the tree, so
the tree contains the latest run only.

## Capture with Playwright instead

```
cd ../js && npm ci && npm run build
cd ../demo/playwright && npm install && npx playwright install chromium
npm run journey
```

`npm install` copies the local `@tacticmedia/qa-capture` instead of creating a symbolic link,
because `.npmrc` sets `install-links`. After you rebuild `js/`, run `npm install` here again. A
symlinked package resolves `@playwright/test` from its own `node_modules`, and Playwright does not
load twice.

The spec repeats each stage of the PHP journey and uses the same six labels.
`playwright.config.ts` defaults `QA_SCREENSHOTS_DIR` to the `var/screenshots` directory of this
application when the variable is not set. **The two producers alternate; they do not accumulate.**
Each one empties the tree when
its run starts.

## Review

```
php -S localhost:8000 -t public public/index.php
```

Open http://localhost:8000/_dev/screenshots. Select a group in the sidebar, open a capture, select
a rectangle over the incorrect area and write a note. Then use Generate prompt to produce the agent
brief. The review stores the notes in `var/review/screenshot-feedback.json`, outside the screenshot
tree, so they stay available after the journey runs again.

## Review via Docker

```
docker compose up --build review
```

Open http://localhost:8000/_dev/screenshots. `compose.yaml` mounts `var/screenshots` and
`var/review` under `/data`, which is the root of this project inside the container. The
generated brief therefore prints `var/screenshots/...` and not a path inside the container. The
service specifies both an `image:` and a `build:`. `up` on its own reuses an image already in the
local cache, otherwise pulls, and builds only when the pull fails. To take the published image after
a local build, run `docker compose pull review`; deleting `build:` alone keeps the local tag.

Use this method for a project with no PHP tools. Capture continues to run on the host.

## Styling

The Tailwind build of this application supplies the styles for the review page, instead of the CDN
script that the default layout of the bundle loads. Two items produce that result, and
`docs/frontend.md` in the bundle requires both from a host:

`templates/bundles/TacticMediaQaBundle/layout.html.twig` overrides the `styles` block with a link to
the compiled stylesheet:

```twig
{% extends '@!TacticMediaQa/layout.html.twig' %}

{% block styles %}
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
{% endblock %}
```

`asset()` comes from `symfony/asset`, which AssetMapper does not require. A host that writes this
override installs it.

`assets/styles/app.css` adds the bundle's templates as a Tailwind source, so the build emits the
utilities that markup uses:

```css
@import "tailwindcss";
@source "../../vendor/tacticmedia/qa-bundle/templates";
```

While changing those templates, rebuild on save:

```
php bin/console tailwind:build --watch
```

## Differences from the host instructions of the bundle

- The bundle is in `require` and is enabled in each environment, because this application exists
  only to test it. A production host installs it with `--dev` and enables it for `dev`.
- The review routes come from the `routing.controllers` loader (symfony/routing 7.4 and later),
  which reads them from the controller service. The `config/routes/qa.yaml` import that the README
  of the bundle documents is the method for hosts on Symfony 6.4; this application does not use it.
- `assets/app.js` does not import `styles/app.css`. Twig links the stylesheet instead, so it
  reaches the page once and not a second time through `importmap('app')`.
