# Flex recipe

The recipe is not part of the distributed package. `.gitattributes` and `composer.json` exclude it
from both archives.

## Target location

Copy `1.0/` into a fork of [`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib)
at:

```
tacticmedia/qa-bundle/1.0/
```

The directory name is the lowest bundle version the recipe applies to. Flex uses one recipe until a
higher directory exists.

## Contents, and the reason for each part

`manifest.json` registers the bundle in `dev`, where the page runs, and in `test`, where a host
opens the page from a `WebTestCase`. A host installs the bundle with `--dev`, so no other
environment can load it.

`config/routes/qa.yaml` publishes `/_dev/screenshots` in `dev`. `config/packages/qa.yaml` contains
the two options as comments.
