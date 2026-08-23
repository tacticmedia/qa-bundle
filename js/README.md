# @tacticmedia/qa-capture

Capture screenshot sets for the [`tacticmedia/qa-bundle`](../README.md) review page from Playwright.

An end-to-end suite proves that a page works. It cannot tell you that the page looks wrong. This
package shoots every settled screen at five viewports in light and dark, with a sidecar of element
geometry beside each image, so a human can drag a box over what is broken and get a brief precise
enough for a coding agent to act on.

## Install

```
npm i -D @tacticmedia/qa-capture @playwright/test
```

## Use

```ts
// playwright.config.ts
import { defineConfig } from '@playwright/test';
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

`test` is Playwright's own `test` with one added fixture, so everything else about it is unchanged.
`expect` is re-exported for convenience.

Then read the tree:

```
docker run --rm -p 127.0.0.1:8000:8000 \
    -v "$PWD/var/screenshots:/data/var/screenshots" \
    -v "$PWD/var/review:/data/var/review" \
    ghcr.io/tacticmedia/qa-review:latest
```

## Options

```ts
export default defineConfig({
    use: {
        qaScreenshotsOptions: {
            root: 'var/screenshots',
            viewports: [{ width: 390, height: 844 }, { width: 1920, height: 1080 }],
            colorSchemes: ['light', 'dark'],
        },
    },
});
```

| Option | Default |
|---|---|
| `root` | `QA_SCREENSHOTS_DIR`, else `var/screenshots` under the working directory |
| `viewports` | 390x844, 768x1024, 1024x768, 1920x1080, 1728x1117 |
| `colorSchemes` | `['light', 'dark']` |

`QA_SCREENSHOTS_DIR` outranks `root` everywhere, so the global setup and the fixture can never clear
one tree and write another.

## What to know

- **The tree holds one run.** Global setup empties it before any worker starts, which is the only
  point that is safe with parallel workers. Two producers writing one root in one run means the
  second erases the first.
- **A root set with `test.use()` inside a spec is invisible to global setup.** Set it in the config
  or in `QA_SCREENSHOTS_DIR`.
- **`viewport: null` is incompatible.** The capture resizes the viewport to the document height, so
  it needs one to resize. A `deviceScaleFactor` other than 1 is fine: images are captured at CSS
  scale.
- **The label names the screen.** Anything outside `[A-Za-z0-9 _-]` is replaced with a hyphen. A
  label already captured in the same test is skipped, so a helper called twice shoots once.
- **Firefox and WebKit work.** Nothing here uses raw CDP.
- **Identity comes from the spec path and the test title**, slugged into the basename
  (`AdminProductList-aVisitorChecksOut-001_Home.png`). Two tests that slug identically overwrite
  each other; directories are included in the journey slug to make that unlikely.

The on-disk contract is [docs/screenshot-sets.md](../docs/screenshot-sets.md), with a JSON Schema at
[docs/sidecar.schema.json](../docs/sidecar.schema.json).

## Development

```
npm ci
npm run build
npm test

QA_SCREENSHOTS_DIR=/tmp/shots npm run test:integration
cd .. && QA_SCREENSHOTS_DIR=/tmp/shots vendor/bin/phpunit --group contract
```

The integration suite drives the bundle's own PHP fixture host and reads the tree back; the
`contract` group then runs the real PHP reader over the same directory.

`src/capture/metadata.js` does not exist: the DOM metadata script is
`resources/capture/metadata.js` at the repository root, shared verbatim with the PHP trait, and
copied into `dist/capture/` by `npm run build`. npm's `files` cannot reach outside the package root,
so the copy is the sharing mechanism - never fork it.
