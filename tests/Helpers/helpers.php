<?php

declare(strict_types=1);

/**
 * What the WordPress function mocks below record and answer, kept in typed static properties rather than in
 * $GLOBALS so that tests read it with known types.
 */
final class WpMock
{
    /** @var list<array{hook: string, callback: callable, priority: int, accepted_args: int}> */
    public static array $actions = [];

    /** @var array<string, mixed> The value apply_filters() answers, by hook. */
    public static array $filters = [];

    /** @var list<array{hook: string, value: mixed, arguments: list<mixed>}> */
    public static array $filterCalls = [];

    /** @var list<array{function: string, message: string, version: string}> */
    public static array $doingItWrong = [];

    public static bool $doingAjax = false;

    /** @var list<array{action: int|string, query_arg: false|string, stop: bool}> */
    public static array $nonceChecks = [];

    public static bool $nonceValid = false;

    /** @var list<array{data: mixed, status: int|null}> */
    public static array $jsonErrors = [];

    public static bool $userLoggedIn = false;

    /** @var list<string> The capabilities the current user has. */
    public static array $userCaps = [];

    /** @var list<string> The capabilities current_user_can() was asked about. */
    public static array $capabilityChecks = [];

    /**
     * The error code of the JSON error at $index, or null when there is none.
     */
    public static function jsonErrorCode(int $index = 0): ?string
    {
        $data = self::$jsonErrors[$index]['data'] ?? null;
        $code = is_array($data) ? ($data['code'] ?? null) : null;

        return is_string($code) ? $code : null;
    }

    /**
     * The first action hooked on $hook.
     *
     * @return array{hook: string, callback: callable, priority: int, accepted_args: int}
     */
    public static function hooked(string $hook): array
    {
        foreach (self::$actions as $action) {
            if ($action['hook'] === $hook) {
                return $action;
            }
        }

        throw new RuntimeException("Nothing is hooked on {$hook}.");
    }

    /**
     * The callback of the first action hooked on $hook, or null when nothing is hooked there.
     */
    public static function callbackOn(string $hook): ?callable
    {
        foreach (self::$actions as $action) {
            if ($action['hook'] === $hook) {
                return $action['callback'];
            }
        }

        return null;
    }

    /**
     * Forgets everything that was recorded.
     */
    public static function reset(): void
    {
        self::$actions = [];
        self::$filters = [];
        self::$filterCalls = [];
        self::$doingItWrong = [];
        self::$doingAjax = false;
        self::$nonceChecks = [];
        self::$nonceValid = false;
        self::$jsonErrors = [];
        self::$userLoggedIn = false;
        self::$userCaps = [];
        self::$capabilityChecks = [];
    }
}

/**
 * WordPress function mocks for testing.
 */
if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        WpMock::$actions[] = [
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
        WpMock::$filterCalls[] = ['hook' => $hook, 'value' => $value, 'arguments' => array_values($arguments)];

        return array_key_exists($hook, WpMock::$filters) ? WpMock::$filters[$hook] : $value;
    }
}

if (! function_exists('_doing_it_wrong')) {
    function _doing_it_wrong(string $function_name, string $message, string $version): void
    {
        WpMock::$doingItWrong[] = ['function' => $function_name, 'message' => $message, 'version' => $version];
    }
}

if (! function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return WpMock::$doingAjax;
    }
}

if (! function_exists('check_ajax_referer')) {
    function check_ajax_referer(int|string $action = -1, false|string $query_arg = false, bool $stop = true): int|false
    {
        WpMock::$nonceChecks[] = ['action' => $action, 'query_arg' => $query_arg, 'stop' => $stop];

        return WpMock::$nonceValid ? 1 : false;
    }
}

if (! function_exists('wp_send_json_error')) {
    function wp_send_json_error(mixed $data = null, ?int $status_code = null, int $flags = 0): void
    {
        WpMock::$jsonErrors[] = ['data' => $data, 'status' => $status_code];
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
        return WpMock::$userLoggedIn;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        WpMock::$capabilityChecks[] = $capability;

        return in_array($capability, WpMock::$userCaps, true);
    }
}
