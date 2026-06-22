# Pollora Ajax

A modern PHP package for WordPress AJAX action management with a fluent API.

## Installation

```bash
composer require pollora/ajax
```

## Quick Start

```php
use Pollora\Ajax\Ajax;

// Register for all users
Ajax::listen('my_action', function () {
    wp_send_json_success(['message' => 'It works!']);
});

// Logged-in users only
Ajax::listen('my_action', function () {
    // ...
})->forLoggedUsers();

// Guest users only
Ajax::listen('my_action', function () {
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