# The review host

A Symfony application that serves `/_dev/screenshots` from two mounted directories and nothing
else. `/` redirects to that page. The application lets a project with no PHP tools review its
screenshot sets.

This application is not the demo: it has no Tailwind build and no journey. It is also not the test
fixture. That host registers the importmap Twig services manually, because `symfony/asset-mapper` is
a dev dependency there. Here it is a normal dependency, so TwigBundle registers `importmap()`.

## The mount contract

**`/data` is the root of the reviewed project inside the container. Mount each tree at the path it
has on the host.**

The generated agent brief then prints paths that the host can open, because the brief gives the
path of a capture relative to the project root, and the container uses `/data` as that root.

```
docker run --rm -p 127.0.0.1:8000:8000 \
    -v "$PWD/var/screenshots:/data/var/screenshots" \
    -v "$PWD/var/review:/data/var/review" \
    ghcr.io/tacticmedia/qa-review:latest
```

A project whose trees are at different paths mounts them at those paths and sets the variables:

```
docker run --rm -p 127.0.0.1:8000:8000 \
    -v "$PWD/test-results/screenshots:/data/test-results/screenshots" \
    -v "$PWD/test-results/review:/data/test-results/review" \
    -e QA_SCREENSHOTS_DIR=/data/test-results/screenshots \
    -e QA_REVIEW_DIR=/data/test-results/review \
    ghcr.io/tacticmedia/qa-review:latest
```

The application reads `QA_SCREENSHOTS_DIR`, `QA_REVIEW_DIR` and `QA_PROJECT_ROOT` at runtime, so a
change to them needs no rebuild. They default to `/data/var/screenshots`, `/data/var/review` and
`/data`. With no mounts, the application serves the empty state.

The page has no authentication. Publish the port on `127.0.0.1` unless you intend to share it.

On Linux the container writes notes and crops as uid 1000. To own them, pass
`--user "$(id -u):$(id -g)"`.

## Maintenance

`composer.lock` is committed. The application consumes the bundle through a path repository at
`../..`, and the Dockerfile copies the repository with the same structure, so the relative path
resolves to the same location on a developer machine and in the image.

**After a change to the dependencies of the bundle, run `composer update` here.** Nothing compares
the lock with the bundle's `composer.json`: `composer install` in the image installs from the lock,
and an outdated lock breaks the build only where `cache:warmup` or the review page needs the missing
package.

## Local development without Docker

```
composer install
php bin/console importmap:install
QA_SCREENSHOTS_DIR=/absolute/path/to/screenshots \
QA_REVIEW_DIR=/absolute/path/to/review \
QA_PROJECT_ROOT=/absolute/path/to/project \
    php -S 127.0.0.1:8000 -t public public/index.php
```

Do not run `asset-map:compile` before this command, and remove `public/assets` if it exists. The
built-in server passes an existing file under `public/` to the router script as `SCRIPT_FILENAME`,
and the Symfony runtime then requires the compiled asset as PHP instead of serving it. Leave
`APP_ENV` unset: in `prod` no dev server serves uncompiled assets, and the built-in server passes an
exported `APP_ENV` to PHP only where `variables_order` in php.ini contains `E`. The image runs
`prod` behind Caddy, where `php_server` serves an existing file directly and sends other requests
to PHP.
