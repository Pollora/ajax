# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.3.0] - 2026-11-18

### Added

- `AjaxAction::requireCapability(string $capability)` and `getCapability()`: the registrar checks `current_user_can()` after the nonce and before the callback. A logged-in user without the capability receives a 403 JSON response (`pollora_ajax_forbidden`); a guest on an action that requires a capability receives a 401 (`pollora_ajax_unauthenticated`) and the developer is told with `_doing_it_wrong()`, because a capability and guests are contradictory. An empty capability throws `InvalidAjaxActionException`. An action that does not call it behaves as before.
