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
