# Development

The root `composer.lock` is not committed, so the first run resolves like an update.

```
composer update
vendor/bin/phpstan analyse
vendor/bin/phpunit --exclude-group e2e
composer cs:check
vendor/bin/bdi detect drivers && vendor/bin/playwright-install chromium
vendor/bin/phpunit --group e2e
```

CI runs PHPStan and the non-E2E tests on PHP 8.2/Symfony 6.4, 8.3/7.4, 8.4/8.1 and 8.5/8.1. It runs
the E2E group on 8.2/6.4 and 8.5/8.1, in two jobs: `panther` runs it without the `playwright` group,
and `playwright` runs the `playwright` group only. It runs the style check on 8.5. To reproduce one
matrix job, select the branch in the same way as the workflow. `composer update` on its own
constrains only the packages that `composer.json` names, and the transitive Symfony packages then
resolve to their latest branch:

```
composer global require symfony/flex
composer config extra.symfony.require 6.4.*
composer update
```

The E2E group drives Chrome against `tests/Fixtures/app`, which the PHP built-in web server serves.
`PantherCaptureE2eTest` and `PlaywrightCaptureE2eTest` capture the review page and
`tests/Fixtures/app/public/tall.html` with each capture trait. playwright-php starts no web server,
so `PlaywrightCaptureE2eTest` starts one on port 9081 with Panther's `WebServerManager`. Without
`QA_SCREENSHOTS_DIR`, both tests write to a temporary directory and remove it afterwards. To keep a
tree for the `contract` group, set the variable:

```
QA_SCREENSHOTS_DIR=/tmp/php-shots vendor/bin/phpunit --group playwright
QA_SCREENSHOTS_DIR=/tmp/php-shots vendor/bin/phpunit --group contract
```

`tests/Host/production-build.sh` creates a `symfony/skeleton` host on AssetMapper, installs this
checkout with `composer require --dev`, and then runs `composer install --no-dev` and
`asset-map:compile` in `prod`. The `production-build` CI job runs it for a host with
`symfony/stimulus-bundle` and for a host without it. It needs network access and an empty target
directory:

```
tests/Host/production-build.sh /tmp/qa-host symfony/stimulus-bundle
```

`assets/dist/stimulus.js` and `assets/dist/turbo.js` are unchanged copies of `dist/stimulus.js`
from `@hotwired/stimulus` 3.2.2 and `dist/turbo.es2017-esm.js` from `@hotwired/turbo` 8.0.23, with
their licenses beside them. The header of the Stimulus 3.2.2 file reads 3.2.1. To upgrade, replace
both files from the npm tarballs and run the E2E group.

The capture package and the review image have separate test commands:

```
cd js && npm ci && npm run build && npm test
npx playwright install chromium
QA_SCREENSHOTS_DIR=/tmp/shots npm run test:integration
```

From the repository root, which is the build context of the Dockerfile:

```
QA_SCREENSHOTS_DIR=/tmp/shots vendor/bin/phpunit --group contract
docker build -f docker/review/Dockerfile -t qa-review:local .
```

The `contract` group runs the PHP reader over a tree that a producer wrote. CI runs it over the tree
of each producer: the npm package in the `node` job, and each PHP trait in the `panther` and
`playwright` jobs. This confirms that the layout is a contract between the producers and not the
behaviour of one implementation. The group skips itself when `QA_SCREENSHOTS_DIR` is not set, so CI
runs it with `--fail-on-skipped`.

## The Flex recipe

`recipe/` contains the recipe. Neither archive contains it, and it is not published from this
repository. `recipe/README.md` gives the target path in `symfony/recipes-contrib` and the reason
the recipe registers `dev` and `test`.

## Demo

`demo/` is a complete example that you can run. It is a Symfony 8.1 application on PHP 8.4. Its
Panther journey opens each page of https://tacticmedia.com.au, captures each screen at five
viewports in light and dark, and serves the review page over the captured tree. The same journey
is in `demo/tests/E2e/TacticMediaPlaywrightJourneyE2eTest.php` for playwright-php, and in
`demo/playwright/` for Playwright on Node.js. Each writes the same tree, so each producer gives the
same six screens in the review. `demo/README.md` describes the producers and the container.
