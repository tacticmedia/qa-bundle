# JavaScript and styling

How the page's JavaScript reaches the browser in each build tool, how to replace the default
styling, and what a strict content security policy permits.

## A running Stimulus application

The page needs the Stimulus application of the host to be started, with the four `qa-*` controllers
registered in it. This applies to each build tool. The Twig helpers in StimulusBundle do not depend
on the build tool, so the markup is the same in each host and only the delivery of the JavaScript
changes.

On **AssetMapper** no action is necessary, if your Stimulus entrypoint is named `app`. The default
`scripts` block calls `importmap('app')`, which throws an exception where the host has no such
entrypoint. If your entrypoint has a different name, override the block in the same way as the
bundler hosts below. Flex writes the `controllers.json` block and adds `@hotwired/stimulus` and
`@hotwired/turbo` to `importmap.php`, and the bundle registers `assets/dist` as an AssetMapper
path.

On **Webpack Encore** and **Symfony Reprise** the controllers resolve from `node_modules`. Flex
writes the same `controllers.json` block, and adds the package link and its peer dependencies to
`package.json`:

```json
{
    "devDependencies": {
        "@hotwired/stimulus": "^3.0.0",
        "@hotwired/turbo": "^8.0.0",
        "@tacticmedia/qa-bundle": "file:vendor/tacticmedia/qa-bundle/assets"
    }
}
```

Run `npm install` and rebuild after `composer require`. `@hotwired/turbo` is necessary:
`annotate_controller.js` imports `visit` at module scope, so an unresolved Turbo stops the complete
annotate controller and not the keyboard navigation only.

Those two hosts must also specify how their JavaScript reaches the page, because the default
`scripts` block calls `importmap()`. Override the layout:

```twig
{# templates/bundles/TacticMediaQaBundle/layout.html.twig #}
{% extends '@!TacticMediaQa/layout.html.twig' %}

{% block scripts %}
    {{ encore_entry_script_tags('app') }}
{% endblock %}
```

Use `{{ reprise_entry_script_tags('app') }}` for Reprise. Give the name of the entrypoint that
starts your Stimulus application; `app` is the default from the recipe. `@!` is the TwigBundle
reference to the copy of the template in the bundle, which lets the override extend the file that it
replaces.

`symfony/reprise` needs PHP 8.4 and Symfony 7.4 or 8.x, so a host below PHP 8.4, or on Symfony 6.4,
is on Encore or AssetMapper.

## Styling

The default layout of the bundle loads Tailwind from a CDN and contains no compiled CSS. A strict
content security policy blocks that script, so override the layout with your own shell:

```
templates/bundles/TacticMediaQaBundle/layout.html.twig
```

The blocks meant for that are `title`, `brand`, `styles`, `scripts` and `sidebar`; page content
arrives in `review_content`. `brand` sits inside `sidebar`, so an override of `sidebar` replaces it
too.

```twig
{% extends '@!TacticMediaQa/layout.html.twig' %}

{% block styles %}
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
{% endblock %}
```

`asset()` comes from `symfony/asset`, which AssetMapper does not require. Install it where the
override needs it.

If your Tailwind build should pick up the bundle's own markup, add its templates as a source. A
`vendor/` directory in your `.gitignore` does not hide them: gitignore filtering applies only to
Tailwind's automatic detection, and an explicit `@source` is always scanned.

```css
@source "../../vendor/tacticmedia/qa-bundle/templates";
```

`demo/` contains that override in an operating AssetMapper host. `demo/README.md` describes it.

## Content security policy

The annotate controller sets the overlay geometry through the CSSOM, which `style-src` does not
control, so the selection needs no change to the policy. The default `scripts` block writes an
inline importmap and an inline module script: give them a nonce through
`framework.asset_mapper.importmap_script_attributes`, or override the block. The default `styles`
block loads Tailwind from a CDN, which needs `script-src https://cdn.jsdelivr.net` unless you also
override it.
