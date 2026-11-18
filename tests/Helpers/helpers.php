<?php

declare(strict_types=1);

/**
 * WordPress function mocks for testing.
 */
if (! function_exists('add_action')) {
    function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $GLOBALS['wp_actions'][] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.com/wp-admin/'.$path;
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return $url;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$arguments): mixed
    {
        $GLOBALS['wp_filter_calls'][] = ['hook' => $hook, 'value' => $value, 'arguments' => $arguments];

        return array_key_exists($hook, $GLOBALS['wp_filters'] ?? []) ? $GLOBALS['wp_filters'][$hook] : $value;
    }
}

if (! function_exists('_doing_it_wrong')) {
    function _doing_it_wrong(string $function_name, string $message, string $version): void
    {
        $GLOBALS['wp_doing_it_wrong'][] = ['function' => $function_name, 'message' => $message, 'version' => $version];
    }
}

if (! function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return (bool) ($GLOBALS['wp_doing_ajax'] ?? false);
    }
}

if (! function_exists('check_ajax_referer')) {
    function check_ajax_referer(int|string $action = -1, false|string $query_arg = false, bool $stop = true): int|false
    {
        $GLOBALS['wp_nonce_checks'][] = ['action' => $action, 'query_arg' => $query_arg, 'stop' => $stop];

        return ($GLOBALS['wp_nonce_valid'] ?? false) ? 1 : false;
    }
}

if (! function_exists('wp_send_json_error')) {
    function wp_send_json_error(mixed $data = null, ?int $status_code = null, int $flags = 0): void
    {
        $GLOBALS['wp_json_errors'][] = ['data' => $data, 'status' => $status_code];
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce(int|string $action = -1): string
    {
        return 'nonce-for-'.$action;
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return (bool) ($GLOBALS['wp_user_logged_in'] ?? false);
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        $GLOBALS['wp_capability_checks'][] = $capability;

        return in_array($capability, $GLOBALS['wp_user_caps'] ?? [], true);
    }
}
