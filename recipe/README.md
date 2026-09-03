# Flex recipe

Not part of the distributed package. `.gitattributes` and `composer.json` exclude it from both
archives.

## Where it goes

Copy `1.0/` into a fork of [`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib)
at:

```
tacticmedia/qa-bundle/1.0/
```

The directory name is the lowest bundle version the recipe applies to. Flex uses one recipe until a
higher directory exists.

## What it does, and why each part is necessary

`manifest.json` registers the bundle in `dev` and `test`. Both environments matter:

- Composer writes the `@tacticmedia/qa-bundle` block into the host's `assets/controllers.json`,
  because `composer.json` carries the `symfony-ux` keyword. That file is environment-agnostic.
- The AssetMapper path that the block resolves against comes from
  `TacticMediaQaBundle::prependExtension()`, which runs only where the bundle is registered.

Where the two disagree, `symfony/stimulus-bundle` throws
`Could not find an asset mapper path that points to the "annotate" controller in package
"tacticmedia/qa-bundle", defined in controllers.json.` A host that builds a container in `test`, for
example through `WebTestCase`, needs the registration as much as `dev` does. See
[docs/host-setup.md](../docs/host-setup.md).

The recipe also fixes an ordering problem. Flex installs recipes before it synchronises
`controllers.json` and before the `importmap:require` calls that follow it, so with the recipe the
host is never in the broken state. Without it, `composer require` writes the block into a host that
has no `config/bundles.php` entry, and the next container build fails.

`config/routes/qa.yaml` publishes `/_dev/screenshots` in `dev`. `config/packages/qa.yaml` carries
the two options as comments.
