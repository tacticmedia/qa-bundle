# Development

The root `composer.lock` is not committed, so the first run resolves like an update.

```
composer update
vendor/bin/phpstan analyse
vendor/bin/phpunit --exclude-group e2e
composer cs:check
vendor/bin/bdi detect drivers && vendor/bin/phpunit --group e2e
```

CI runs PHPStan and the non-E2E tests on PHP 8.2/Symfony 6.4, 8.3/7.4, 8.4/8.1 and 8.5/8.1. It runs
the E2E group on 8.2/6.4 and 8.5/8.1, and the style check on 8.5. To reproduce one leg, select the
branch in the same way as the workflow. `composer update` on its own constrains only the packages
that `composer.json` names, and the transitive Symfony packages then resolve to their latest
branch:

```
composer global require symfony/flex
composer config extra.symfony.require 6.4.*
composer update
```

The E2E group drives Chrome against `tests/Fixtures/app`, which the PHP built-in web server serves.

The capture package and the review image have separate gates:

```
cd js && npm ci && npm run build && npm test
npx playwright install chromium
QA_SCREENSHOTS_DIR=/tmp/shots npm run test:integration
```

Back at the repository root, which is also the build context the Dockerfile expects:

```
QA_SCREENSHOTS_DIR=/tmp/shots vendor/bin/phpunit --group contract
docker build -f docker/review/Dockerfile -t qa-review:local .
```

The `contract` group runs the PHP reader over a tree that the npm producer wrote. This confirms that
the layout is a contract between the two producers and not the behaviour of one implementation. The
group skips itself when `QA_SCREENSHOTS_DIR` is not set.

## Demo

`demo/` is a complete example that you can run. It is a Symfony 8.1 application on PHP 8.4. Its
single Panther journey opens each page of https://tacticmedia.com.au, captures each screen at five
viewports in light and dark, and serves the review page over the captured tree. `demo/playwright/`
contains the same journey for Playwright and writes the same tree, so each producer gives the same
six screens in the review. `demo/README.md` describes both producers and the container.
