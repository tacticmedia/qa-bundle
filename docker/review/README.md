# The review host

A Symfony application whose only job is to serve `/_dev/screenshots` from two mounted
directories. It exists so a project with no PHP toolchain can still review its screenshot sets.

It is deliberately not the demo (no Tailwind build, no journey) and not the test fixture (whose
hand-registered importmap services only exist because `symfony/asset-mapper` is a dev dependency
there; here it is a real dependency and TwigBundle wires `importmap()` itself).

## The mount contract

**`/data` is the reviewed project's root as the container sees it. Mount each tree at the path it
has on the host.**

That is what makes the generated agent brief print paths the host can open: the brief names a
capture relative to the project root, and the container's idea of that root is `/data`.

```
docker run --rm -p 127.0.0.1:8000:8000 \
    -v "$PWD/var/screenshots:/data/var/screenshots" \
    -v "$PWD/var/review:/data/var/review" \
    ghcr.io/tacticmedia/qa-review:latest
```

A project whose trees live elsewhere mounts them where they are and says so:

```
docker run --rm -p 127.0.0.1:8000:8000 \
    -v "$PWD/test-results/screenshots:/data/test-results/screenshots" \
    -v "$PWD/test-results/review:/data/test-results/review" \
    -e QA_SCREENSHOTS_DIR=/data/test-results/screenshots \
    -e QA_REVIEW_DIR=/data/test-results/review \
    ghcr.io/tacticmedia/qa-review:latest
```

`QA_SCREENSHOTS_DIR`, `QA_REVIEW_DIR` and `QA_PROJECT_ROOT` are read at runtime, so changing them
needs no rebuild. They default to `/data/var/screenshots`, `/data/var/review` and `/data`. Running
with no mounts at all serves the empty state.

The page has no authentication. Publish the port on `127.0.0.1` unless you mean to share it.

On Linux the container writes notes and crops as uid 1000. Pass `--user "$(id -u):$(id -g)"` to own
them yourself.

## Maintenance

`composer.lock` is committed. The bundle is consumed through a path repository at `../..`, and the
Dockerfile copies the repository in the same shape, so the relative path resolves identically on a
developer's disk and inside the image.

**A change to the bundle's dependencies means running `composer update` here.** The `docker` CI job
builds this image on every push and is the drift detector.

## Local development without Docker

```
composer install
php bin/console importmap:install
QA_SCREENSHOTS_DIR=/absolute/path/to/screenshots \
QA_REVIEW_DIR=/absolute/path/to/review \
QA_PROJECT_ROOT=/absolute/path/to/project \
    php -S 127.0.0.1:8000 -t public public/index.php
```

Leave `APP_ENV` unset for this. The built-in server hands a request for an existing static file
straight to the router script, so compiled assets under `public/assets` are executed as PHP rather
than served; `dev` routes them through AssetMapper instead and works. The image runs `prod` and
serves those files through Caddy, which is what `php_server` does before it reaches PHP.
