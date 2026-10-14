#!/usr/bin/env bash
#
# A new Symfony host on AssetMapper installs this checkout with `composer require --dev`,
# then builds its production assets without the development dependencies.
#
#     tests/Host/production-build.sh <new host directory> [<host package>...]
#
# The host packages are the direct requirements of the host, for example
# symfony/stimulus-bundle.
set -euo pipefail

bundle="$(cd "$(dirname "$0")/../.." && pwd)"
host="$1"
shift

composer create-project --no-interaction --no-progress 'symfony/skeleton:8.1.*' "$host"
cd "$host"

# The checkout can be a detached HEAD, from which Composer cannot guess the branch.
composer config repositories.qa-bundle \
    "{\"type\": \"path\", \"url\": \"$bundle\", \"options\": {\"versions\": {\"tacticmedia/qa-bundle\": \"dev-main\"}}}"

composer require --no-interaction --no-progress symfony/asset-mapper symfony/twig-bundle "$@"
composer require --dev --no-interaction --no-progress tacticmedia/qa-bundle:dev-main

APP_ENV=prod composer install --no-dev --no-interaction --no-progress
APP_ENV=prod php bin/console asset-map:compile
