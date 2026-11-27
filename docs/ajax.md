# AJAX

A modern package for WordPress AJAX action management with a fluent API and secure defaults.

## Basic Usage

Use the `listen()` method to register an AJAX handler:

```php
use Pollora\Ajax\Ajax;

Ajax::listen('my_action', function () {
    wp_send_json_success(['message' => 'It works!']);
});
```

By default, this registers `wp_ajax_my_action` **only** — the handler is restricted to **logged-in users** for security. Unauthenticated users cannot reach it. It also **verifies a nonce**: a request without one made for `my_action` gets a 403.

## Security Model

AJAX endpoints are **secure by default**: `listen()` only registers the `wp_ajax_*` hook (authenticated users). You must explicitly opt in to expose an endpoint to unauthenticated visitors.

```php
// Default — logged-in users only (wp_ajax_*), nonce verified
Ajax::listen('my_action', function () {
    wp_send_json_success(['user' => wp_get_current_user()->display_name]);
});

// Explicit — all users, logged-in AND guests (wp_ajax_* + wp_ajax_nopriv_*)
Ajax::listen('public_action', function () {
    wp_send_json_success(['message' => 'Hello everyone!']);
})->forAllUsers()->withoutNonce();

// Guest users only (wp_ajax_nopriv_*)
Ajax::listen('guest_action', function () {
    wp_send_json_success(['message' => 'Hello guest!']);
})->forGuestUsers()->withoutNonce();
```

> **Why?** Exposing `wp_ajax_nopriv_*` allows any unauthenticated visitor to call the endpoint. This should be a conscious decision, not an implicit default.

### Nonce

A nonce is verified before the handler runs, for every audience, unless the action says `withoutNonce()`. The nonce action is the AJAX action name, or what `requireNonce('...')` names. The request carries it in `_ajax_nonce` or `_wpnonce`. A refused request gets a 403 with the code `pollora_ajax_invalid_nonce`; when `WP_DEBUG` is on, a `hint` field also names the expected nonce.

For actions open to guests, choose: a guest's nonce is tied to no user, and a cached page can serve an expired one. `withoutNonce()` removes the check for logged-in users as well.

`add_filter('pollora/ajax/nonce_by_default', '__return_false')` turns the default off for the actions that did not choose, to migrate from 1.x. See [the README](../README.md#protect-an-action-with-a-nonce).

### Capability

`requireCapability('edit_posts')` is optional and has no default. Without it, any logged-in user with a valid nonce runs the action. See [the README](../README.md#require-a-capability).

## Using a Controller Method

You can reference a controller instead of a closure:

```php
Ajax::listen('load_more_posts', [PostController::class, 'loadMore']);
```

## AjaxAccess Enum

The `AjaxAccess` enum defines audience targeting and can be used programmatically:

```php
use Pollora\Ajax\Domain\Model\AjaxAccess;
```

| Value | WordPress Hook | Audience |
|---|---|---|
| `AjaxAccess::LOGGED` | `wp_ajax_{action}` | Logged-in users only (default) |
| `AjaxAccess::ALL` | `wp_ajax_{action}` + `wp_ajax_nopriv_{action}` | Everyone |
| `AjaxAccess::GUEST` | `wp_ajax_nopriv_{action}` | Guests only |

## Frontend JavaScript

Give the page the nonce of the action, then send it with the request. `ajaxurl` is a global of the WordPress admin; on the front end, use `Ajax::injectScripts()` (see below).

```php
use Pollora\Ajax\Ajax;

add_action('wp_enqueue_scripts', function (): void {
    wp_add_inline_script('my-script', 'var myApp = '.wp_json_encode(['nonce' => Ajax::nonce('my_action')]).';', 'before');
});
```

```javascript
jQuery.post(ajaxurl, {
    action: 'my_action',
    _ajax_nonce: myApp.nonce,
    data: 'some data'
}, function (response) {
    if (response.success) {
        console.log(response.data);
    }
});
```

## Script Injection

To automatically inject the AJAX URL as a JavaScript variable in the HTML head:

```php
use Pollora\Ajax\Ajax;

Ajax::injectScripts();
```

This outputs:

```html
<script type="text/javascript">var Pollora = { ajaxurl: "https://example.com/wp-admin/admin-ajax.php" };</script>
```

## Architecture

This package follows Hexagonal Architecture (Ports & Adapters):

- **Domain** — `AjaxAction` entity, `AjaxAccess` enum, and business rules
- **Port** — `AjaxActionRegistrarPort` interface
- **Application** — `RegisterAjaxActionService` orchestration
- **Adapter** — WordPress-specific implementations (`add_action()`)
- **Factory** — `AjaxFactory` for service container integration