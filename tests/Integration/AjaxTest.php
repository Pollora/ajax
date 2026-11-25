<?php

declare(strict_types=1);

use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Ajax;

require_once ABSPATH.'wp-admin/includes/user.php';

/*
 * The AJAX actions against a real WordPress: the hooks admin-ajax.php fires, the real nonce and capability
 * functions, and the real JSON answer. The unit suite doubles all of these.
 */

/**
 * What the hooks of the tests record, in typed properties rather than in $GLOBALS.
 */
final class AjaxWpint
{
    /** @var list<string> The messages of the _doing_it_wrong notices. */
    public static array $notices = [];

    /** The last HTTP status code WordPress set. */
    public static ?int $status = null;

    /** @var list<int> The users the test created, deleted afterwards. */
    public static array $users = [];
}

beforeEach(function (): void {
    AjaxWpint::$notices = [];
    add_filter('wp_doing_ajax', '__return_true');
    add_filter('doing_it_wrong_trigger_error', '__return_false');
    add_action('doing_it_wrong_run', function (string $function, string $message): void {
        AjaxWpint::$notices[] = $message;
    }, 10, 2);

    // wp_send_json_error() ends the request with wp_die(): turn that into an exception the test can catch.
    add_filter('wp_die_ajax_handler', fn (): Closure => function (): void {
        throw new RuntimeException('wp_die');
    });
    add_filter('status_header', function (string $header, int $code): string {
        AjaxWpint::$status = $code;

        return $header;
    }, 10, 2);
});

afterEach(function (): void {
    foreach (AjaxWpint::$users as $id) {
        wp_delete_user($id);
    }

    AjaxWpint::$users = [];
    remove_all_filters('wp_doing_ajax');
    remove_all_filters('doing_it_wrong_trigger_error');
    remove_all_actions('doing_it_wrong_run');
    remove_all_filters('wp_die_ajax_handler');
    remove_all_filters('status_header');
    remove_all_filters(WordPressAjaxActionRegistrar::NONCE_BY_DEFAULT_FILTER);
    remove_all_actions('wp_head');
    unset($_REQUEST['_ajax_nonce']);
    AjaxWpint::$status = null;
    AjaxWpint::$notices = [];
    wp_set_current_user(0);
});

/**
 * Fire an action the way admin-ajax.php does: `wp_ajax_{action}` for a logged-in user, `wp_ajax_nopriv_{action}` for a guest.
 *
 * @return array{status: int|null, body: array<mixed>|null}
 */
function dispatchAjax(string $action, ?int $userId = null, ?string $nonce = null): array
{
    wp_set_current_user($userId ?? 0);
    AjaxWpint::$status = null;

    if ($nonce !== null) {
        $_REQUEST['_ajax_nonce'] = $nonce;
    }

    ob_start();

    try {
        do_action(is_user_logged_in() ? "wp_ajax_{$action}" : "wp_ajax_nopriv_{$action}");
    } catch (RuntimeException) {
        // wp_die() of wp_send_json_error()
    }

    $output = (string) ob_get_clean();
    $body = json_decode($output, true);

    return ['status' => AjaxWpint::$status, 'body' => is_array($body) ? $body : null];
}

/**
 * The error code of a JSON error answer, or null when the answer has none.
 *
 * @param  array{status: int|null, body: array<mixed>|null}  $answer
 */
function ajaxErrorCode(array $answer): ?string
{
    $data = $answer['body']['data'] ?? null;
    $code = is_array($data) ? ($data['code'] ?? null) : null;

    return is_string($code) ? $code : null;
}

/**
 * A user with a role, deleted after the test.
 */
function ajaxUser(string $role): int
{
    $id = wp_insert_user(['user_login' => 'ajax_'.$role.'_'.wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'role' => $role]);
    if ($id instanceof WP_Error) {
        throw new RuntimeException('Could not create the test user: '.$id->get_error_message());
    }

    AjaxWpint::$users[] = $id;

    return $id;
}

it('registers the hooks admin-ajax.php fires, for the audience the action chose', function (): void {
    Ajax::listen('pollora_wpint_logged', fn () => null)->withoutNonce();
    Ajax::listen('pollora_wpint_guest', fn () => null)->forGuestUsers()->withoutNonce();
    Ajax::listen('pollora_wpint_both', fn () => null)->forAllUsers()->withoutNonce();
    // The actions are registered when the fluent objects are destroyed.
    gc_collect_cycles();

    expect(has_action('wp_ajax_pollora_wpint_logged'))->not->toBeFalse()
        ->and(has_action('wp_ajax_nopriv_pollora_wpint_logged'))->toBeFalse()
        ->and(has_action('wp_ajax_pollora_wpint_guest'))->toBeFalse()
        ->and(has_action('wp_ajax_nopriv_pollora_wpint_guest'))->not->toBeFalse()
        ->and(has_action('wp_ajax_pollora_wpint_both'))->not->toBeFalse()
        ->and(has_action('wp_ajax_nopriv_pollora_wpint_both'))->not->toBeFalse();
});

it('refuses a request without a valid nonce with a 403 JSON error, and accepts one made by WordPress', function (): void {
    $ran = 0;
    Ajax::listen('pollora_wpint_nonce', function () use (&$ran): void {
        $ran++;
        wp_send_json_success('done');
    })->requireNonce();
    gc_collect_cycles();
    $user = ajaxUser('subscriber');

    $refused = dispatchAjax('pollora_wpint_nonce', $user);

    expect($ran)->toBe(0)
        ->and($refused['body']['success'] ?? null)->toBeFalse()
        ->and(ajaxErrorCode($refused))->toBe(WordPressAjaxActionRegistrar::INVALID_NONCE_CODE)
        ->and($refused['status'])->toBe(403);

    // The nonce is made for the logged-in user: the same one is refused for another.
    wp_set_current_user($user);
    $nonce = Ajax::nonce('pollora_wpint_nonce');

    $accepted = dispatchAjax('pollora_wpint_nonce', $user, $nonce);

    expect($ran)->toBe(1)
        ->and($accepted['body'])->toBe(['success' => true, 'data' => 'done']);

    $other = dispatchAjax('pollora_wpint_nonce', ajaxUser('subscriber'), $nonce);

    expect($ran)->toBe(1)
        ->and(ajaxErrorCode($other))->toBe(WordPressAjaxActionRegistrar::INVALID_NONCE_CODE);
});

it('answers 401 to a guest and 403 to a user without the capability, and runs for one who has it', function (): void {
    $ran = 0;
    Ajax::listen('pollora_wpint_cap', function () use (&$ran): void {
        $ran++;
        wp_send_json_success();
    })->forAllUsers()->withoutNonce()->requireCapability('manage_options');
    gc_collect_cycles();

    $guest = dispatchAjax('pollora_wpint_cap');
    $subscriber = dispatchAjax('pollora_wpint_cap', ajaxUser('subscriber'));
    $admin = dispatchAjax('pollora_wpint_cap', ajaxUser('administrator'));

    expect(ajaxErrorCode($guest))->toBe(WordPressAjaxActionRegistrar::UNAUTHENTICATED_CODE)
        ->and($guest['status'])->toBe(401)
        ->and(ajaxErrorCode($subscriber))->toBe(WordPressAjaxActionRegistrar::FORBIDDEN_CODE)
        ->and($subscriber['status'])->toBe(403)
        ->and($admin['body']['success'] ?? null)->toBeTrue()
        ->and($ran)->toBe(1);
});

it('tells the developer, once registered, that an action never chose how it is protected', function (): void {
    Ajax::listen('pollora_wpint_undecided', fn () => null);
    gc_collect_cycles();

    expect(AjaxWpint::$notices)->toHaveCount(0);

    // Outside an AJAX request the notice is reported; it is the registration that matters here.
    remove_all_filters('wp_doing_ajax');
    Ajax::listen('pollora_wpint_undecided_page', fn () => null);
    gc_collect_cycles();

    expect(AjaxWpint::$notices)->toHaveCount(1)
        ->and(AjaxWpint::$notices[0])->toContain('pollora_wpint_undecided_page');
});

it('verifies a nonce for every undecided action when the filter says so', function (): void {
    add_filter(WordPressAjaxActionRegistrar::NONCE_BY_DEFAULT_FILTER, '__return_true');
    $ran = 0;
    Ajax::listen('pollora_wpint_default', function () use (&$ran): void {
        $ran++;
    });
    gc_collect_cycles();

    $answer = dispatchAjax('pollora_wpint_default', ajaxUser('subscriber'));

    expect($ran)->toBe(0)
        ->and(ajaxErrorCode($answer))->toBe(WordPressAjaxActionRegistrar::INVALID_NONCE_CODE);
});

it('prints the admin-ajax.php URL of the site in the head of the page', function (): void {
    Ajax::injectScripts();

    ob_start();
    do_action('wp_head');
    $head = (string) ob_get_clean();

    expect($head)->toContain('var Pollora = { ajaxurl: "'.esc_url(admin_url('admin-ajax.php')).'" };')
        ->and(admin_url('admin-ajax.php'))->toEndWith('/wp-admin/admin-ajax.php');
});
