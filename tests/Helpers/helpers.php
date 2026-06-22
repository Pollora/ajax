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
