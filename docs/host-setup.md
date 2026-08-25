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
