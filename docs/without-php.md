# Using it without PHP

Capture runs from Playwright and the review page runs from a container, so the host needs no PHP
tools.

To capture from Playwright:

```
npm i -D @tacticmedia/qa-capture @playwright/test
```

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

The global setup empties the tree once, before the first worker starts. The fixture captures each
viewport and colour scheme. [js/README.md](../js/README.md) gives the defaults and the overrides in
`qaScreenshotsOptions`.

To review in a container:

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
```

Then open http://localhost:8000/_dev/screenshots.

**`/data` is the root of your project as the container reads it.** Mount each tree at the path it
has on the host, so that the generated brief gives paths that you can open. `QA_SCREENSHOTS_DIR`,
`QA_REVIEW_DIR` and `QA_PROJECT_ROOT` replace the defaults at runtime. The page has no
authentication, so the port is bound to `127.0.0.1`. On Linux the container writes as uid 1000. To
own the notes, start the service with
`docker compose run --user "$(id -u):$(id -g)" review`.
[docker/review/README.md](../docker/review/README.md) gives more information.

Capture does not run in the container. The review image contains no browser. Playwright downloads
and manages its own browser on the host.
