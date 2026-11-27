# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- **BREAKING**: a nonce is verified by default. An action that says neither `requireNonce()` nor `withoutNonce()` (`nonce: null` on `#[Ajax]`) now refuses a request without a valid nonce for the action name: 403, `pollora_ajax_invalid_nonce`, and the callback does not run. In 1.x it ran without a check. This applies to every audience, guests included. [#28](https://github.com/Pollora/ajax/issues/28)

  To migrate, for each action that did not choose:
  1. When the request is sent by your own page, create the nonce with `Ajax::nonce('action_name')` and send it in the `_ajax_nonce` (or `_wpnonce`) field.
  2. When no nonce makes sense (public read, cached page, beacon, signed webhook), declare `->withoutNonce()` or `#[Ajax(..., nonce: false)]`. Both exist since 1.2.0, so this can be done before upgrading. On an action open to everyone it also removes the check for logged-in users.
  3. If a form already has a nonce of another name, declare `->requireNonce('that_name')`.

  In 1.x, `WP_DEBUG` lists the actions concerned (`_doing_it_wrong()`, outside AJAX requests), and `add_filter('pollora/ajax/nonce_by_default', '__return_true')` gives the 2.0 behaviour to try it.

- **BREAKING**: the `pollora/ajax/nonce_by_default` filter now receives `true` by default. Returning `false` runs the actions that did not choose without a check, as in 1.x, and each is reported. Only a strict `false` does so: another falsy value (`null`, `0`, `''`), which 1.x read as "do not verify", keeps the check.
- `_doing_it_wrong()` no longer reports an undecided action that only logged-in users reach: it is protected. It reports an undecided action open to guests, whose nonce is weaker and can be stale on a cached page.

### Added

- The 403 answer of a refused nonce has a `hint` field next to `code` and `message` (both unchanged): the nonce action expected, the request fields, and, for an action that did not choose, that the check is the default one.

The capability stays optional (`requireCapability()`): no capability is a sensible default for every action.

## [1.3.0] - 2026-11-18

### Added

- `AjaxAction::requireCapability(string $capability)` and `getCapability()`: the registrar checks `current_user_can()` after the nonce and before the callback. A logged-in user without the capability receives a 403 JSON response (`pollora_ajax_forbidden`); a guest on an action that requires a capability receives a 401 (`pollora_ajax_unauthenticated`) and the developer is told with `_doing_it_wrong()`, because a capability and guests are contradictory. An empty capability throws `InvalidAjaxActionException`. An action that does not call it behaves as before.
