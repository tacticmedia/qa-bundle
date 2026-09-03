# Host setup

What a Symfony host must have after `composer require`. The [README](../README.md) gives the
install commands. Two concerns have their own documents: [csrf.md](csrf.md) for the token setup
on each Symfony branch, and [frontend.md](frontend.md) for the JavaScript, the styling and the
content security policy.

## Stimulus controllers

Composer writes the Stimulus controllers into `assets/controllers.json`. The package has the
`symfony-ux` keyword, so the Flex package synchronizer adds the block during installation and keeps
it on each later run. Flex writes the block only where the host already has
`assets/controllers.json` and a root `package.json` or `importmap.php`. It does not create the file.
Confirm that the block is present, and write it manually if the file did not exist. **Without the
block the page renders, but it does not respond to a selection or to a key**:

```json
{
    "controllers": {
        "@tacticmedia/qa-bundle": {
            "annotate": { "enabled": true, "fetch": "eager" },
            "clipboard": { "enabled": true, "fetch": "lazy" },
            "confirm": { "enabled": true, "fetch": "lazy" },
            "anchor-highlight": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

The synchronizer rebuilds the complete file from the installed `symfony-ux` packages. It removes a
manually added entry for a package that does not have the keyword on the next composer run.

## The bundle and controllers.json must agree

`assets/controllers.json` names the package for every environment, because Composer writes it and
Composer knows nothing about environments. The AssetMapper path that each entry resolves against
comes from `TacticMediaQaBundle::prependExtension()`, which runs only where `config/bundles.php`
enables the bundle. Where the two disagree, StimulusBundle stops the container build:

```
In ControllersMapGenerator.php line 147:
  Could not find an asset mapper path that points to the "annotate" controller
  in package "tacticmedia/qa-bundle", defined in controllers.json.
```

**Wherever `assets/controllers.json` names the package, the bundle must be registered.** Two host
states satisfy that:

- `composer require --dev` with `['dev' => true, 'test' => true]`. Build the production assets
  after `composer install --no-dev`, which makes Flex remove the block first.
- `composer require` with `['all' => true]`, for a pipeline that runs `asset-map:compile` while the
  development dependencies are installed.

`test` matters as much as `dev`. A host that opens the page from a `WebTestCase` builds a container
from the same `controllers.json`.

To recover a host that already shows the error, add the missing line to `config/bundles.php` and run
`bin/console cache:clear`. To install into a host that has no recipe, keep Composer away from the
container until the line exists:

```
composer require --dev --no-scripts tacticmedia/qa-bundle symfony/panther
# add the bundles.php line and the routes
composer install
```

`--no-dev --no-scripts` together leave the opposite state: the block stays in the committed file
after the package is gone, and the reader reports the package instead of the path.

```
Could not find package "tacticmedia/qa-bundle" referred to from controllers.json.
```

A deploy that runs the Composer scripts does not meet this, because Flex rewrites the file.

## The capture trait

`JourneyScreenshots` goes on one shared journey base class, and on no other class. The clear-once
guard is a trait static, which PHP copies into each class that uses the trait directly, so a second
such class empties the tree again during the run.

`captureFullPageScreenshot($client, $label)` captures the settled screen at each viewport and colour
scheme. The label becomes part of the basename, and the brief names it. The capture uses the Chrome
DevTools protocol, so the journey must run a Chrome client. A stage label that occurs more than once
in a test is captured once, so a journey helper that is called twice captures its screen once.

## Configuration

```yaml
when@dev:
    qa:
        screenshots_dir: '%kernel.project_dir%/var/screenshots'
        review_dir: '%kernel.project_dir%/var/review'
```

Both options default to the values above. `review_dir` contains the notes file and the generated
crops. It must be outside `screenshots_dir`, because each run empties `screenshots_dir`. The bundle
rejects a configuration in which `review_dir` is inside `screenshots_dir`.

The capture trait does not read this configuration, because it runs in PHPUnit with no container. It
takes its root from `QA_SCREENSHOTS_DIR`, or from `getcwd().'/var/screenshots'` when that variable
is not set. Set both to the same tree.

Override what is captured on your journey base class:

```php
protected static function screenshotViewports(): array
{
    return [['width' => 1440, 'height' => 900]];
}

protected static function screenshotColorSchemes(): array
{
    return ['light'];
}
```

The orientation directory follows from the shape of each viewport. The review page reads the
directories that the tree contains, so an added viewport or a third colour scheme needs no
configuration, if the directory names agree with the grammar in
[docs/screenshot-sets.md](screenshot-sets.md).

## A firewall that permits `/_dev/`

The page has no authentication of its own and needs nothing from SecurityBundle. Where the host has
a firewall:

```yaml
security:
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt|dev)|css|images|js)/
            security: false
            stateless: true
```

## Prompt wording

`prompt.txt.twig` contains general wording. Put the conventions, document paths and test command of
your project in `templates/bundles/TacticMediaQaBundle/prompt.txt.twig`.
