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

// Logged-in users only (default, wp_ajax_my_action), nonce verified (default)
Ajax::listen('my_action', function (): void {
    wp_send_json_success(['message' => 'It works!']);
});

// Everyone: wp_ajax_* and wp_ajax_nopriv_* (explicit opt-in); a public read, so no nonce, on purpose
Ajax::listen('public_action', fn () => wp_send_json_success())->forAllUsers()->withoutNonce();

// Guests only (wp_ajax_nopriv_*)
Ajax::listen('guest_action', fn () => wp_send_json_success())->forGuestUsers()->withoutNonce();
```

A request to `my_action` must carry a nonce, made with `Ajax::nonce('my_action')`, in its `_ajax_nonce` (or `_wpnonce`) field. Without it the answer is a 403 and the callback does not run.

## What you get

- **`Ajax::listen($action, $callback)`** returns a fluent `AjaxAction`; the hooks are registered once the chain completes.
- **Secure defaults**: logged-in users only, and a nonce verified. `forAllUsers()`, `forGuestUsers()` and `forLoggedUsers()` change the audience.
- **`AjaxAccess` enum**: `LOGGED`, `GUEST` and `ALL`, for choosing the audience programmatically.
- **Nonce protection**: a nonce is verified before the callback runs unless the action says `->withoutNonce()`; see [Protect an action with a nonce](#protect-an-action-with-a-nonce).
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
    #[Ajax('subscribe')]                 // verifies the nonce `subscribe`
    public function subscribe(): void
    {
        wp_send_json_success(['message' => 'Subscribed!']);
    }

    #[Ajax('load_more', access: AjaxAccess::ALL, nonce: false)]
    public function loadMore(): void
    {
        wp_send_json_success([/* ... */]);
    }
}
```

## Protect an action with a nonce

An AJAX request carries the visitor's cookies, so a page of another site can make a logged-in visitor's browser send it. A nonce, a token WordPress creates for a user and an action, is what tells the request your own page sent from one it did not. **It is verified by default**, for every audience: a request without a valid one receives a 403 and the callback does not run.

```php
// The page creates the nonce and sends it in the `_ajax_nonce` field (or `_wpnonce`).
$nonce = Ajax::nonce('save_note');

// Nothing to declare: the nonce action is the AJAX action name.
Ajax::listen('save_note', $callback);

// Another nonce action than the AJAX action name, when a form already has one.
Ajax::listen('save_note', $callback)->requireNonce('notes_form');

// No nonce on purpose (a public beacon, a webhook that signs its own requests).
Ajax::listen('track', $callback)->forGuestUsers()->withoutNonce();
```

With the Pollora framework, the `#[Ajax]` attribute says the same: `#[Ajax('save_note')]`, `nonce: 'notes_form'` or `nonce: false`.

A refused request receives a 403 status and:

```json
{"success": false, "data": {"code": "pollora_ajax_invalid_nonce", "message": "Invalid or expired security token.", "hint": "Send a nonce for \"save_note\" (Ajax::nonce('save_note')) in the _ajax_nonce or _wpnonce request field."}}
```

`hint` is for the developer: it names the nonce that was expected and, for an action that did not choose, says that the check is the default one.

### Actions open to guests

The default is the same for guests (`forGuestUsers()`, `forAllUsers()`), but choose for them:

- A guest's nonce is tied to no user: any visitor can read it from your page, so it stops much less than a logged-in user's nonce.
- A page served from a cache keeps its nonce after it expired (WordPress nonces live 12 to 24 hours), and the guest gets a 403.

For a public or cached endpoint, say `->withoutNonce()`. **On `forAllUsers()` this removes the check for logged-in users too**: keep the nonce, or split the action in two, when the callback does something on behalf of the logged-in user. An action open to guests that chooses nothing is reported to the developer (`_doing_it_wrong()`, visible with `WP_DEBUG`, skipped while an AJAX request is answered).

### Turning the default off while migrating

```php
// Actions that did not choose run without a check again, as in 1.x. Each one is reported with `_doing_it_wrong()`.
add_filter('pollora/ajax/nonce_by_default', '__return_false');
```

Add it before the actions are registered. It receives the action name as its second argument, so it can spare only the actions you have not migrated yet. Only a strict `false` turns the check off, and it never overrides `requireNonce()` or `withoutNonce()`. It reopens your actions to cross-site request forgery: use it to migrate, not as a setting.

## Require a capability

A nonce proves where a request comes from, not that the user may do what it asks. Without a capability check, any logged-in user (a subscriber, for instance) who has a valid nonce can run the action. Say who may:

```php
// The action refuses a logged-in user who lacks the capability: 403, and the callback does not run.
Ajax::listen('save_note', $callback)->requireCapability('edit_posts'); // the nonce is verified too
```

With the Pollora framework: `#[Ajax('save_note', capability: 'edit_posts')]`.

The capability is checked with `current_user_can()`, after the nonce and before the callback. A user without it receives `{"success": false, "data": {"code": "pollora_ajax_forbidden", "message": "You are not allowed to do this."}}` with a 403 status. The capability is optional, and there is no default one: which capability makes sense depends on what the action does (`read` would let every account through, `edit_posts` would shut out a customer saving their own data). Without `requireCapability()`, any logged-in user with a valid nonce runs the action.

**A capability and guests do not go together**: a guest is not logged in, so it has no capability. On the guest side of an action open to guests (`forGuestUsers()`, `forAllUsers()`), the request is refused with a 401 (`pollora_ajax_unauthenticated`) and WordPress is told (`_doing_it_wrong()`, visible with `WP_DEBUG`, skipped while an AJAX request is answered). Restrict the action to logged-in users, or drop the capability.

An action that requires a capability still verifies its nonce; the two checks are independent.

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
