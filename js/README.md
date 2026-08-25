# @tacticmedia/qa-capture

Capture screenshot sets for the [`tacticmedia/qa-bundle`](../README.md) review page from Playwright.

An end-to-end suite shows that a page operates correctly. It does not show that the page looks
wrong. This package captures each settled screen at five viewports in light and dark, and writes a
sidecar of element geometry beside each image. A reviewer can then select a rectangle over the
incorrect area and receive a brief that is accurate enough for a coding agent to use.

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
    testDir: './tests',
    globalSetup: createRequire(import.meta.url).resolve('@tacticmedia/qa-capture/playwright/global-setup'),
});
```

`import.meta` needs an ES module. Set `"type": "module"` in the package, or name the file
`playwright.config.mts`.

```ts
// tests/home.spec.ts
import { test } from '@tacticmedia/qa-capture/playwright';

test('a visitor reads the company site', async ({ page, qaScreenshots }) => {
    await page.goto('https://tacticmedia.com.au/');
    await qaScreenshots.capture('Home');
});
```

`test` is the Playwright `test` with the `qaScreenshots` fixture and the `qaScreenshotsOptions`
option that configures it. Its other behaviour does not change. `expect` is re-exported.

Then read the tree:

```
docker run --rm -p 127.0.0.1:8000:8000 \
    -v "$PWD/var/screenshots:/data/var/screenshots" \
    -v "$PWD/var/review:/data/var/review" \
    ghcr.io/tacticmedia/qa-review:latest
```

## Options

```ts
import { defineConfig } from '@playwright/test';
import type { QaScreenshotsOptions } from '@tacticmedia/qa-capture/playwright';

export default defineConfig<{ qaScreenshotsOptions: QaScreenshotsOptions }>({
    use: {
        qaScreenshotsOptions: {
            root: 'var/screenshots',
            viewports: [{ width: 390, height: 844 }, { width: 1920, height: 1080 }],
            colorSchemes: ['light', 'dark'],
        },
    },
});
```

Playwright resolves a custom option through the generic parameter. Without it, `tsc` reports
`qaScreenshotsOptions` as an unknown property.

| Option | Default |
|---|---|
| `root` | `QA_SCREENSHOTS_DIR`, else `var/screenshots` under the working directory |
| `viewports` | 390x844, 768x1024, 1024x768, 1920x1080, 1728x1117 |
| `colorSchemes` | `['light', 'dark']` |

`QA_SCREENSHOTS_DIR` has precedence over `root` in each location, so the global setup and the
fixture cannot empty one tree and write to a different tree.

## What to know

- **The tree contains one run.** The global setup empties it before the first worker starts, which
  is the only safe point when workers run in parallel. If two producers write one root in one run,
  the second removes the output of the first.
- **The global setup does not read a root that `test.use()` sets in a spec.** Set the root in the
  Playwright configuration or in `QA_SCREENSHOTS_DIR`.
- **`viewport: null` is not supported.** The capture changes the viewport to the document height, so
  a viewport must exist. A `deviceScaleFactor` other than 1 is supported, because the capture uses
  CSS scale.
- **The label identifies the screen.** A sequence of characters outside `[A-Za-z0-9 _-]` becomes
  one hyphen. The capture skips a label already used in the same test, so a helper that is called
  twice captures once.
- **Firefox and WebKit are supported.** This package does not use CDP.
- **The identity comes from the spec path and the test title**, converted to the basename
  (`AdminProductList-aVisitorChecksOut-001_Home.png`). The path is relative to `testDir`, and its
  leading directories are part of the journey slug. Two tests that give the same slug overwrite
  each other.

The on-disk contract is [docs/screenshot-sets.md](../docs/screenshot-sets.md), with a JSON Schema at
[docs/sidecar.schema.json](../docs/sidecar.schema.json).

## Development

```
npm ci
npm run build
npm test

npx playwright install chromium
QA_SCREENSHOTS_DIR=/tmp/shots npm run test:integration
cd .. && QA_SCREENSHOTS_DIR=/tmp/shots vendor/bin/phpunit --group contract
```

`npm test` needs no browser. The integration suite needs one.

The integration suite drives the PHP fixture host of the bundle and reads the tree. The `contract`
group then runs the PHP reader over the same directory.

`src/capture/metadata.js` does not exist. The DOM metadata script is `resources/capture/metadata.js`
at the repository root. The PHP trait uses the same file without modification, and `npm run build`
copies it into `dist/capture/`. The npm `files` field cannot reference a path outside the package
root, so the copy is the method of sharing. Do not create a second version of the file.
