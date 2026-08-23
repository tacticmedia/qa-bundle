# qa-bundle demo

A stock Symfony 8.1 application on AssetMapper and PHP 8.4 that consumes the bundle through a
Composer path repository. It carries one Panther journey, `tests/E2e/TacticMediaJourneyE2eTest.php`,
which traverses https://tacticmedia.com.au and captures every screen it settles on, and the review
page to look at the result. The application has no pages of its own; the only routes are the
bundle's `/_dev/screenshots*`.

`playwright/` carries the same journey written for Playwright, writing the same tree. Running either
producer gives the same six screens in the review, which is what makes the layout in
`docs/screenshot-sets.md` a contract rather than a description of the PHP trait.

## Requirements

- PHP 8.4 or newer with `ext-gd`
- Google Chrome
- Network access to https://tacticmedia.com.au

## Set up

```
composer install
vendor/bin/bdi detect drivers
```

`bdi` downloads the chromedriver that matches the installed Chrome into `drivers/`, where Panther
finds it.

`composer install` compiles the stylesheet as well: `tailwind:build` is one of the `auto-scripts`,
so `post-install-cmd` and `post-update-cmd` run it. The first run downloads the pinned standalone
Tailwind binary into `var/tailwind/`. Outside the `test` environment the bundle's `strict_mode` is
on, so a checkout with no built stylesheet answers the review page with an exception rather than
unstyled markup.

## Capture

```
vendor/bin/phpunit
```

The journey opens the site, follows the navigation links and calls `captureFullPageScreenshot()` at
each settled screen. Every capture lands in `var/screenshots/<mode>/<WxH>/<orientation>/` at five
viewports in light and dark - the trait's defaults. The tree is emptied at the start of each run,
so it always holds the latest run only.

## Capture with Playwright instead

```
cd ../js && npm ci && npm run build
cd ../demo/playwright && npm install && npx playwright install chromium
npm run journey
```

`npm install` copies rather than symlinks the local `@tacticmedia/qa-capture` (`.npmrc` sets
`install-links`), so rebuilding `js/` means running `npm install` here again. A symlinked package
resolves `@playwright/test` from its own `node_modules` and Playwright refuses to load twice.

The spec mirrors the PHP journey stage for stage, with the same six labels, and
`playwright.config.ts` points `QA_SCREENSHOTS_DIR` at this application's `var/screenshots`. **The two
producers alternate, they do not accumulate**: each empties the tree at the start of its run.

## Review

```
php -S localhost:8000 -t public public/index.php
```

Open http://localhost:8000/_dev/screenshots. Browse the groups in the sidebar, open a capture, drag
over what looks wrong and write a note, then use Generate prompt for the agent brief. Notes are
stored in `var/review/screenshot-feedback.json`, outside the screenshot tree, so they survive a
journey re-run.

## Review via Docker

```
docker compose up --build review
```

Open http://localhost:8000/_dev/screenshots. `compose.yaml` mounts `var/screenshots` and
`var/review` under `/data`, which is this project's root as the container sees it - that is what
makes the generated brief print `var/screenshots/...` rather than a path inside the container. Drop
`--build` once the published image is what you want.

This is the path for a project with no PHP toolchain. Capture still runs on the host.

## Styling

The review page arrives styled by this application's Tailwind build, not by the CDN script the
bundle's own layout loads. Two pieces do that, and they are the two the bundle's README asks a host
for:

`templates/bundles/TacticMediaQaBundle/layout.html.twig` overrides the `styles` block with a link to
the compiled stylesheet:

```twig
{% extends '@!TacticMediaQa/layout.html.twig' %}

{% block styles %}
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
{% endblock %}
```

`asset()` comes from `symfony/asset`, which AssetMapper does not require - a host writing this
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

## Where this deviates from the README's host instructions

- The bundle sits in `require` and is enabled in every environment, because this application
  exists only to exercise it. A real host installs it with `--dev` and enables it for `dev`.
- The review routes come from Symfony 8's `routing.controllers` loader, which reads them off the
  controller service. The `config/routes/qa.yaml` import the README documents is the way for hosts
  on Symfony 6.4 or 7.4, and works here too.
- `assets/app.js` does not import `styles/app.css`. The stylesheet is linked from Twig instead, so
  it reaches the page once rather than also riding along with `importmap('app')`.
