# CSRF protection

Each write on the review page validates a CSRF token. What the host must provide differs by
Symfony branch.

## `framework.csrf_protection` not disabled

The write actions validate the token with `AbstractController::isCsrfTokenValid()`, which resolves
`security.csrf.token_manager` from FrameworkBundle. FrameworkBundle enables CSRF protection by
default when a session is enabled or, from Symfony 7.2, when a stateless token id is registered,
but only while `symfony/security-csrf` is a non-dev dependency of the host. This bundle registers
the id and requires `symfony/security-csrf`, but a `composer require --dev` install makes both
dev-only. A host with no other dependency on `symfony/security-csrf` must therefore set
`framework.csrf_protection: true` or require the package itself. Where CSRF protection is off,
`csrf_token()` is not registered, and the annotate and prompt pages do not render.

## The double-submit listener, on Symfony 7.2 and later

On those versions the bundle prepends `screenshot-review` to
`framework.csrf_protection.stateless_token_ids`. `SameOriginCsrfTokenManager` does not accept a
lower level of proof after a higher one: when a session has validated one request that carried the
double-submit cookie, each later request from that session must also carry it. A browser that has
used your application is in that state. Without the double-submit the server answers each POST with
403 Forbidden and stores nothing.

The page therefore double-submits the token itself. `assets/dist/csrf_protection.js` is a copy of
`csrf_protection_controller.js` from the `symfony/stimulus-bundle` recipe. On each submit it sets
the cookie, and the header for a Turbo submission, for the token input that has
`data-controller="csrf-protection"`. The host supplies nothing.

Symfony 6.4 has no stateless token ids. On that branch the token is session-backed and the listener
does nothing. Sessions must be enabled, and `/_dev/screenshots` must be able to start a session.
Without a session, `framework.csrf_protection` defaults to disabled, `csrf_token()` is not
registered, and the review page does not render its forms.
