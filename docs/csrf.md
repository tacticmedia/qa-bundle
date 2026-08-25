# CSRF protection

Each write on the review page validates a CSRF token. What the host must provide differs by
Symfony branch.

## `framework.csrf_protection` not disabled

The write actions validate the token with `AbstractController::isCsrfTokenValid()`, which resolves
`security.csrf.token_manager` from FrameworkBundle. From Symfony 7.2 the bundle enables CSRF
protection itself, because the registration of a stateless token id enables it. If you disable it
explicitly, the review page fails before a write occurs: `csrf_token()` is then not registered, and
the annotate and prompt pages do not render.

## The `csrf-protection` Stimulus controller, on Symfony 7.2 and later

On those versions the bundle prepends `screenshot-review` to
`framework.csrf_protection.stateless_token_ids`, and its forms apply
`{{ stimulus_controller('csrf-protection') }}` to the hidden token input.
`SameOriginCsrfTokenManager` does not accept a lower level of proof after a higher one: when a
session has validated one request that carried the double-submit cookie, each later request from
that session must also carry it. A browser that has used your application is in that state. Without
the controller the server rejects the POST requests. The failure is not visible, because the
redirect still occurs and the result looks like a page refresh in which the write did not happen.
The `symfony/stimulus-bundle` recipe supplies the controller as
`assets/controllers/csrf_protection_controller.js`, and an Encore host receives it from the same
recipe. Do not rename it.

Symfony 6.4 and 7.0 have no stateless token ids. On those branches the token is session-backed and
the controller does not exist. Sessions must be enabled, and `/_dev/screenshots` must be able to
start a session. Without a session, `framework.csrf_protection` defaults to disabled, `csrf_token()`
is not registered, and the review page does not render its forms.
