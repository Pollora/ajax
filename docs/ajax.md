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

By default, this registers `wp_ajax_my_action` **only** — the handler is restricted to **logged-in users** for security. Unauthenticated users cannot reach it.

## Security Model

AJAX endpoints are **secure by default**: `listen()` only registers the `wp_ajax_*` hook (authenticated users). You must explicitly opt in to expose an endpoint to unauthenticated visitors.

```php
// Default — logged-in users only (wp_ajax_*)
Ajax::listen('my_action', function () {
    wp_send_json_success(['user' => wp_get_current_user()->display_name]);
});

// Explicit — all users, logged-in AND guests (wp_ajax_* + wp_ajax_nopriv_*)
Ajax::listen('public_action', function () {
    wp_send_json_success(['message' => 'Hello everyone!']);
})->forAllUsers();

// Guest users only (wp_ajax_nopriv_*)
Ajax::listen('guest_action', function () {
    wp_send_json_success(['message' => 'Hello guest!']);
})->forGuestUsers();
```

> **Why?** Exposing `wp_ajax_nopriv_*` allows any unauthenticated visitor to call the endpoint. This should be a conscious decision, not an implicit default.

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

Send AJAX requests from JavaScript using the `ajaxurl` global provided by WordPress:

```javascript
jQuery.post(ajaxurl, {
    action: 'my_action',
    _wpnonce: myApp.nonce,
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