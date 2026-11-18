<p align="center">
  <a href="https://pollora.dev">
    <img src="https://raw.githubusercontent.com/Pollora/.github/main/brand/banners/ajax.png" width="100%" alt="Pollora Ajax: WordPress AJAX handlers with a fluent API and secure defaults">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/pollora/ajax"><img src="https://img.shields.io/packagist/v/pollora/ajax" alt="Latest version"></a>
  <a href="https://packagist.org/packages/pollora/ajax"><img src="https://img.shields.io/packagist/dt/pollora/ajax" alt="Total downloads"></a>
  <a href="https://github.com/Pollora/ajax/actions/workflows/tests.yml"><img src="https://github.com/Pollora/ajax/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/ajax" alt="License"></a>
</p>

A dependency-free PHP API for WordPress `admin-ajax.php` handlers. One `listen()` call registers the `wp_ajax_*` and `wp_ajax_nopriv_*` hooks for you, and handlers are restricted to logged-in users unless you opt in, so a public endpoint is always a deliberate choice rather than a copy-pasted `nopriv` line.

> Part of [Pollora](https://pollora.dev), the Laravel framework for WordPress. In a Pollora project it is already installed: use the `#[Ajax]` attribute or the `Pollora\Support\Facades\Ajax` facade instead.

## Installation

```bash
composer require pollora/ajax
```

Requires PHP 8.2+ and WordPress (the adapter calls `add_action()`).

## Quick start

```php
use Pollora\Ajax\Ajax;

// Logged-in users only (default, wp_ajax_my_action)
Ajax::listen('my_action', function (): void {
    wp_send_json_success(['message' => 'It works!']);
});

// Everyone: wp_ajax_* and wp_ajax_nopriv_* (explicit opt-in)
Ajax::listen('public_action', fn () => wp_send_json_success())->forAllUsers();

// Guests only (wp_ajax_nopriv_*)
Ajax::listen('guest_action', fn () => wp_send_json_success())->forGuestUsers();
```

## What you get

- **`Ajax::listen($action, $callback)`** returns a fluent `AjaxAction`; the hooks are registered once the chain completes.
- **Secure default**: logged-in users only. `forAllUsers()`, `forGuestUsers()` and `forLoggedUsers()` change the audience.
- **`AjaxAccess` enum**: `LOGGED`, `GUEST` and `ALL`, for choosing the audience programmatically.
- **Nonce protection**: `->requireNonce()` verifies a nonce before the callback runs; see [Protect an action with a nonce](#protect-an-action-with-a-nonce).
- **Capability check**: `->requireCapability('edit_posts')` refuses a user who may not do what the request asks; see [Require a capability](#require-a-capability).
- **`Ajax::injectScripts()`** prints `var Pollora = { ajaxurl: "…/admin-ajax.php" };` in `wp_head`, for front-end requests.
- **Hexagonal core**: `RegisterAjaxActionService` works against an `AjaxActionRegistrarPort`; the WordPress registrar is one adapter.

### With the Pollora framework

Inside Pollora, declare handlers with the `#[Ajax]` attribute; they are discovered automatically:

```php
use Pollora\Ajax\Domain\Model\AjaxAccess;
use Pollora\Attributes\Ajax;

class NewsletterHandler
{
    #[Ajax('subscribe')]
    public function subscribe(): void
    {
        wp_send_json_success(['message' => 'Subscribed!']);
    }

    #[Ajax('load_more', access: AjaxAccess::ALL)]
    public function loadMore(): void
    {
        wp_send_json_success([/* ... */]);
    }
}
```

## Protect an action with a nonce

An AJAX request carries the visitor's cookies, so a page of another site can make a logged-in visitor's browser send it. A nonce, a token WordPress creates for a user and an action, is what tells the request your own page sent from one it did not.

```php
// The page creates the nonce and sends it in the `_ajax_nonce` field (or `_wpnonce`).
$nonce = Ajax::nonce('save_note');

// The action refuses a request without a valid one: 403, and the callback does not run.
Ajax::listen('save_note', $callback)->requireNonce();

// Another nonce action than the AJAX action name, when a form already has one.
Ajax::listen('save_note', $callback)->requireNonce('notes_form');

// No nonce on purpose (a public beacon, a webhook that signs its own requests).
Ajax::listen('track', $callback)->forGuestUsers()->withoutNonce();
```

With the Pollora framework, the `#[Ajax]` attribute says the same: `#[Ajax('save_note', nonce: true)]`, `nonce: 'notes_form'` or `nonce: false`.

A refused request receives `{"success": false, "data": {"code": "pollora_ajax_invalid_nonce", "message": "Invalid or expired security token."}}` with a 403 status.

**An action that does not choose** still runs without a check in the 1.x versions, and WordPress is told (`_doing_it_wrong()`, visible with `WP_DEBUG`, skipped while an AJAX request is answered). The next major version verifies a nonce by default. To get that behaviour now, on every action that did not choose:

```php
add_filter('pollora/ajax/nonce_by_default', '__return_true');
```

A nonce of a guest is tied to no user and a cached page can serve an old one: prefer a different protection for an action open to guests, and say `withoutNonce()` when you do.

## Require a capability

A nonce proves where a request comes from, not that the user may do what it asks. Without a capability check, any logged-in user (a subscriber, for instance) who has a valid nonce can run the action. Say who may:

```php
// The action refuses a logged-in user who lacks the capability: 403, and the callback does not run.
Ajax::listen('save_note', $callback)->requireNonce()->requireCapability('edit_posts');
```

With the Pollora framework: `#[Ajax('save_note', nonce: true, capability: 'edit_posts')]`.

The capability is checked with `current_user_can()`, after the nonce and before the callback. A user without it receives `{"success": false, "data": {"code": "pollora_ajax_forbidden", "message": "You are not allowed to do this."}}` with a 403 status. An action that does not call `requireCapability()` behaves as before.

**A capability and guests do not go together**: a guest is not logged in, so it has no capability. On the guest side of an action open to guests (`forGuestUsers()`, `forAllUsers()`), the request is refused with a 401 (`pollora_ajax_unauthenticated`) and WordPress is told (`_doing_it_wrong()`, visible with `WP_DEBUG`, skipped while an AJAX request is answered). Restrict the action to logged-in users, or drop the capability.

An action that requires a capability is still told to choose its nonce policy (`requireNonce()` or `withoutNonce()`).

## Documentation

- [docs/ajax.md](docs/ajax.md): the security model, the `AjaxAccess` enum, front-end JavaScript and script injection.
- AJAX in a Pollora project: [AJAX](https://pollora.dev/advanced/ajax/).

## Testing

```bash
composer test
```

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/Pollora/.github/blob/main/CONTRIBUTING.md). Report security issues privately, as described in the [security policy](https://github.com/Pollora/.github/blob/main/SECURITY.md).

## License

Pollora Ajax is open-source software licensed under the [MIT license](LICENSE). © [RuBee group](https://rubee.group)
