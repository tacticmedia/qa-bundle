# Upgrade

## From 1.0

Run `composer update tacticmedia/qa-bundle`. Flex then removes the `@tacticmedia/qa-bundle` block
from `assets/controllers.json`, and the `file:vendor/tacticmedia/qa-bundle/assets` link from
`package.json`. Commit both files. Remove the block and the link by hand where
`extra.symfony.synchronize_package_json` is `false`, or where the host has no Flex.

On a Webpack Encore or Symfony Reprise host, run `npm install`. Then remove the `scripts` block from
`templates/bundles/TacticMediaQaBundle/layout.html.twig`, and remove the file if that block was its
only content. If the block stays, the page does not load its JavaScript and does not respond to a
selection or to a key.

A host that has `symfony/stimulus-bundle` only through this bundle loses it on the update. Flex
removes its recipe, but `importmap.php` keeps an entry into `vendor/`, and `asset-map:compile` then
fails with `The asset "./vendor/symfony/stimulus-bundle/assets/dist/loader.js" cannot be found in
any asset map paths.` Remove the entry:

```
bin/console importmap:remove @symfony/stimulus-bundle
```

A host that uses Stimulus itself requires `symfony/stimulus-bundle` instead.

Flex does not remove `@hotwired/stimulus` and `@hotwired/turbo` from `importmap.php` or from the
`devDependencies` of `package.json`. They do not break a build. Remove them if the host does not use
them.

A production pipeline that ran `composer update --no-dev --lock` before `asset-map:compile` to remove
the block does not need that step.
