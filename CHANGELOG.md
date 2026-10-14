# Changelog

## Unreleased

## 2.0.0 - 2026-10-15

[UPGRADE.md](UPGRADE.md) gives the steps for a host on 1.0.

### Changed

- The review page loads its own JavaScript from `/_dev/screenshots/assets/`
- The bundle does not require `symfony/stimulus-bundle` anymore.

### Removed

- The `@tacticmedia/qa-bundle` npm package in `assets/package.json`, and the `symfony-ux` keyword.
- The AssetMapper path `@tacticmedia/qa-bundle`.

### Fixed

- Pulling Turbo/Stimulus management fixed a problem where AssetMapper failed to build production assets due to this dev-only package was not installed.

## 1.1.0 - 2026-10-08

### Added

- `PlaywrightJourneyScreenshots`, a capture trait for playwright-php journeys.
- A Flex recipe in `recipe/`, for `symfony/recipes-contrib`.

## 1.0.0 - 2026-08-25

First release.
