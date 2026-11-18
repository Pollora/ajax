<?php

declare(strict_types=1);

use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Domain\Model\AjaxAction;

beforeEach(function (): void {
    $GLOBALS['wp_actions'] = [];
    $GLOBALS['wp_filters'] = [];
    $GLOBALS['wp_filter_calls'] = [];
    $GLOBALS['wp_doing_it_wrong'] = [];
    $GLOBALS['wp_doing_ajax'] = false;
    $GLOBALS['wp_nonce_checks'] = [];
    $GLOBALS['wp_nonce_valid'] = true;
    $GLOBALS['wp_json_errors'] = [];
    $GLOBALS['wp_user_logged_in'] = true;
    $GLOBALS['wp_user_caps'] = [];
    $GLOBALS['wp_capability_checks'] = [];
});

/**
 * @return mixed The callback hooked on $hook.
 */
function capabilityHook(string $hook): mixed
{
    foreach ($GLOBALS['wp_actions'] as $action) {
        if ($action['hook'] === $hook) {
            return $action['callback'];
        }
    }

    return null;
}

describe('an action that requires a capability', function (): void {
    it('refuses a logged-in user who lacks it, with a 403 and a code to recognise it', function (): void {
        $ran = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function () use (&$ran): void {
            $ran[] = 'ran';
        }))->withoutNonce()->requireCapability('edit_posts'));

        $result = capabilityHook('wp_ajax_save_note')();

        expect($result)->toBeNull()
            ->and($ran)->toBe([])
            ->and($GLOBALS['wp_capability_checks'])->toBe(['edit_posts'])
            ->and($GLOBALS['wp_json_errors'])->toBe([[
                'data' => ['code' => 'pollora_ajax_forbidden', 'message' => 'You are not allowed to do this.'],
                'status' => 403,
            ]]);
    });

    it('runs the callback with its arguments, and returns what it returns, for a user who has it', function (): void {
        $GLOBALS['wp_user_caps'] = ['edit_posts'];
        $received = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function (...$arguments) use (&$received): string {
            $received = $arguments;

            return 'done';
        }))->withoutNonce()->requireCapability('edit_posts'));

        $result = capabilityHook('wp_ajax_save_note')('a', 2);

        expect($result)->toBe('done')
            ->and($received)->toBe(['a', 2])
            ->and($GLOBALS['wp_json_errors'])->toBe([]);
    });

    it('is not reported: a capability is a way to protect an action', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->withoutNonce()->requireCapability('edit_posts'));

        expect($GLOBALS['wp_doing_it_wrong'])->toBe([]);
    });

    it('checks the capability after the nonce: a bad nonce is refused first and the capability is not asked', function (): void {
        $GLOBALS['wp_nonce_valid'] = false;
        $GLOBALS['wp_user_caps'] = ['edit_posts'];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce()->requireCapability('edit_posts'));

        capabilityHook('wp_ajax_save_note')();

        expect($GLOBALS['wp_json_errors'])->toHaveCount(1)
            ->and($GLOBALS['wp_json_errors'][0]['data']['code'])->toBe('pollora_ajax_invalid_nonce')
            ->and($GLOBALS['wp_capability_checks'])->toBe([]);
    });

    it('refuses a user without the capability even with a valid nonce', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce()->requireCapability('edit_posts'));

        capabilityHook('wp_ajax_save_note')();

        expect($GLOBALS['wp_nonce_checks'])->toHaveCount(1)
            ->and($GLOBALS['wp_json_errors'][0]['data']['code'])->toBe('pollora_ajax_forbidden');
    });

    it('runs the callback when the nonce is valid and the user has the capability', function (): void {
        $GLOBALS['wp_user_caps'] = ['edit_posts'];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): string => 'done'))->requireNonce()->requireCapability('edit_posts'));

        expect(capabilityHook('wp_ajax_save_note')())->toBe('done');
    });

    it('is protected as well when the nonce is verified by default through the filter', function (): void {
        $GLOBALS['wp_filters']['pollora/ajax/nonce_by_default'] = true;
        $GLOBALS['wp_nonce_valid'] = false;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireCapability('edit_posts'));

        capabilityHook('wp_ajax_save_note')();

        expect($GLOBALS['wp_json_errors'][0]['data']['code'])->toBe('pollora_ajax_invalid_nonce')
            ->and($GLOBALS['wp_doing_it_wrong'])->toBe([]);
    });

    it('still tells the developer when the nonce choice is missing', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireCapability('edit_posts'));

        expect($GLOBALS['wp_doing_it_wrong'])->toHaveCount(1)
            ->and($GLOBALS['wp_doing_it_wrong'][0]['message'])->toContain('cross-site request forgery');
    });
});

describe('an action that requires a capability and is open to guests', function (): void {
    it('answers a guest with a 401, the callback does not run and the capability is not asked', function (): void {
        $GLOBALS['wp_user_logged_in'] = false;
        $ran = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function () use (&$ran): void {
            $ran[] = 'ran';
        }))->forGuestUsers()->withoutNonce()->requireCapability('edit_posts'));

        $result = capabilityHook('wp_ajax_nopriv_save_note')();

        expect($result)->toBeNull()
            ->and($ran)->toBe([])
            ->and($GLOBALS['wp_capability_checks'])->toBe([])
            ->and($GLOBALS['wp_json_errors'])->toBe([[
                'data' => ['code' => 'pollora_ajax_unauthenticated', 'message' => 'You must be logged in to do this.'],
                'status' => 401,
            ]])
            ->and(capabilityHook('wp_ajax_save_note'))->toBeNull();
    });

    it('still serves the logged-in side of an action open to everyone', function (): void {
        $GLOBALS['wp_user_caps'] = ['edit_posts'];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): string => 'done'))->forAllUsers()->withoutNonce()->requireCapability('edit_posts'));

        expect(capabilityHook('wp_ajax_save_note')())->toBe('done');

        $GLOBALS['wp_user_logged_in'] = false;
        capabilityHook('wp_ajax_nopriv_save_note')();

        expect($GLOBALS['wp_json_errors'][0]['status'])->toBe(401);
    });

    it('tells the developer, once, with the action, the capability and the way out', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->forAllUsers()->withoutNonce()->requireCapability('edit_posts'));

        expect($GLOBALS['wp_doing_it_wrong'])->toHaveCount(1)
            ->and($GLOBALS['wp_doing_it_wrong'][0]['function'])->toBe('Ajax::listen("save_note")')
            ->and($GLOBALS['wp_doing_it_wrong'][0]['version'])->toBe('1.3.0')
            ->and($GLOBALS['wp_doing_it_wrong'][0]['message'])->toBe(
                'The AJAX action "save_note" requires the "edit_posts" capability but is open to guests. A guest is not logged in, so guests always receive a 401 from it. Restrict it to logged-in users (->forLoggedUsers()), or drop ->requireCapability() when guests must be served.'
            );
    });

    it('is not reported while an AJAX request is being answered', function (): void {
        $GLOBALS['wp_doing_ajax'] = true;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->forAllUsers()->withoutNonce()->requireCapability('edit_posts'));

        expect($GLOBALS['wp_doing_it_wrong'])->toBe([]);
    });
});

describe('an action that requires no capability', function (): void {
    it('is hooked as it is, whatever the user can do', function (): void {
        $callback = fn (): null => null;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', $callback))->withoutNonce());

        expect(capabilityHook('wp_ajax_save_note'))->toBe($callback)
            ->and($GLOBALS['wp_capability_checks'])->toBe([]);
    });
});
