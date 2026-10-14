# JavaScript and styling

How the page loads its JavaScript, how to replace the default styling, and what a strict content
security policy permits.

## The page's JavaScript

The default `scripts` block of the layout loads one module, `/_dev/screenshots/assets/review.js`.
It starts a Stimulus application with the four `qa-*` controllers, and it loads Turbo and the
listener that double-submits the CSRF token ([csrf.md](csrf.md)). A route of the bundle serves these
files from `assets/dist/`, so the page uses no file of the host build. That is the same on
AssetMapper, Webpack Encore, Symfony Reprise and a host with no JavaScript build.

Composer writes nothing for this bundle into `assets/controllers.json`, `importmap.php` or
`package.json`. A production build without the development dependencies therefore does not refer
to the bundle.

An override of the `scripts` block must keep its tag, for example with `{{ parent() }}`. The page
does not need the entrypoint of the host.

## Styling

The default layout of the bundle loads Tailwind from a CDN and contains no compiled CSS. A strict
content security policy blocks that script, so override the layout with your own:

```
templates/bundles/TacticMediaQaBundle/layout.html.twig
```

The blocks meant for that are `title`, `brand`, `styles`, `scripts` and `sidebar`; page content
arrives in `review_content`. `brand` is inside `sidebar`, so an override of `sidebar` replaces it
too.

```twig
{% extends '@!TacticMediaQa/layout.html.twig' %}

{% block styles %}
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
{% endblock %}
```

`asset()` comes from `symfony/asset`, which AssetMapper does not require. Install it where the
override needs it.

To include the markup of the bundle in your Tailwind build, add its templates as a source. A
`vendor/` directory in your `.gitignore` does not hide them: gitignore filtering applies only to
Tailwind's automatic detection, and an explicit `@source` is always scanned.

```css
@source "../../vendor/tacticmedia/qa-bundle/templates";
```

After `composer install --no-dev` that directory does not exist. Tailwind builds without those
utilities and does not fail.

`demo/` contains that override in an operating AssetMapper host. `demo/README.md` describes it.

## Content security policy

The default `scripts` block loads one module from the same origin, so `script-src 'self'` permits
it with no nonce. The annotate controller sets the overlay geometry through the CSSOM, which
`style-src` does not control, so the selection needs no change to the policy. Turbo adds a `style`
element for its progress bar and takes the nonce from `<meta name="csp-nonce">`. Without that
element, a policy that restricts `style-src` blocks the style of the progress bar. The default
`styles` block loads Tailwind from a CDN, which needs `script-src https://cdn.jsdelivr.net`, and the
browser build injects a `style` element at run time, so a policy that restricts `style-src` needs
`'unsafe-inline'` for it, unless you also override the block.
