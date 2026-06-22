# Pollora Ajax

A modern PHP package for WordPress AJAX action management with a fluent API and secure defaults.

## Installation

```bash
composer require pollora/ajax
```

## Quick Start

```php
use Pollora\Ajax\Ajax;

// Logged-in users only (default — secure by design)
Ajax::listen('my_action', function () {
    wp_send_json_success(['message' => 'It works!']);
});

// All users (explicit opt-in required)
Ajax::listen('public_action', function () {
    // ...
})->forAllUsers();

// Guest users only
Ajax::listen('guest_action', function () {
    // ...
})->forGuestUsers();
```

### With the Pollora Framework

When used inside Pollora, you can also use the `#[Ajax]` PHP attribute for declarative registration:

```php
use Pollora\Attributes\Ajax;
use Pollora\Ajax\AjaxAccess;

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

See [docs/ajax.md](docs/ajax.md) for full documentation.

## Testing

```bash
composer test
```

## License

GPL-2.0-or-later