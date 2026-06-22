# Pollora Ajax

A modern PHP package for WordPress AJAX action management with a fluent API.

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

## Documentation

See [docs/ajax.md](docs/ajax.md) for full documentation.

## Testing

```bash
composer test
```

## License

GPL-2.0-or-later