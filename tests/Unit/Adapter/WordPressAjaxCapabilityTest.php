<?php

declare(strict_types=1);

use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Domain\Model\AjaxAction;

beforeEach(function (): void {
    WpMock::$actions = [];
    WpMock::$filters = [];
    WpMock::$filterCalls = [];
    WpMock::$doingItWrong = [];
    WpMock::$doingAjax = false;
    WpMock::$nonceChecks = [];
    WpMock::$nonceValid = true;
    WpMock::$jsonErrors = [];
    WpMock::$userLoggedIn = true;
    WpMock::$userCaps = [];
    WpMock::$capabilityChecks = [];
});

describe('an action that requires a capability', function (): void {
    it('refuses a logged-in user who lacks it, with a 403 and a code to recognise it', function (): void {
        $ran = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function () use (&$ran): void {
            $ran[] = 'ran';
        }))->withoutNonce()->requireCapability('edit_posts'));

        $result = WpMock::hooked('wp_ajax_save_note')['callback']();

        expect($result)->toBeNull()
            ->and($ran)->toBe([])
            ->and(WpMock::$capabilityChecks)->toBe(['edit_posts'])
            ->and(WpMock::$jsonErrors)->toBe([[
                'data' => ['code' => 'pollora_ajax_forbidden', 'message' => 'You are not allowed to do this.'],
                'status' => 403,
            ]]);
    });

    it('runs the callback with its arguments, and returns what it returns, for a user who has it', function (): void {
        WpMock::$userCaps = ['edit_posts'];
        $received = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function (...$arguments) use (&$received): string {
            $received = $arguments;

            return 'done';
        }))->withoutNonce()->requireCapability('edit_posts'));

        $result = WpMock::hooked('wp_ajax_save_note')['callback']('a', 2);

        expect($result)->toBe('done')
            ->and($received)->toBe(['a', 2])
            ->and(WpMock::$jsonErrors)->toBe([]);
    });

    it('is not reported: a capability is a way to protect an action', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->withoutNonce()->requireCapability('edit_posts'));

        expect(WpMock::$doingItWrong)->toBe([]);
    });

    it('checks the capability after the nonce: a bad nonce is refused first and the capability is not asked', function (): void {
        WpMock::$nonceValid = false;
        WpMock::$userCaps = ['edit_posts'];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce()->requireCapability('edit_posts'));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$jsonErrors)->toHaveCount(1)
            ->and(WpMock::jsonErrorCode())->toBe('pollora_ajax_invalid_nonce')
            ->and(WpMock::$capabilityChecks)->toBe([]);
    });

    it('refuses a user without the capability even with a valid nonce', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce()->requireCapability('edit_posts'));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$nonceChecks)->toHaveCount(1)
            ->and(WpMock::jsonErrorCode())->toBe('pollora_ajax_forbidden');
    });

    it('runs the callback when the nonce is valid and the user has the capability', function (): void {
        WpMock::$userCaps = ['edit_posts'];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): string => 'done'))->requireNonce()->requireCapability('edit_posts'));

        expect(WpMock::hooked('wp_ajax_save_note')['callback']())->toBe('done');
    });

    it('is protected as well when the nonce is verified by default through the filter', function (): void {
        WpMock::$filters['pollora/ajax/nonce_by_default'] = true;
        WpMock::$nonceValid = false;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireCapability('edit_posts'));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::jsonErrorCode())->toBe('pollora_ajax_invalid_nonce')
            ->and(WpMock::$doingItWrong)->toBe([]);
    });

    it('still tells the developer when the nonce choice is missing', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireCapability('edit_posts'));

        expect(WpMock::$doingItWrong)->toHaveCount(1)
            ->and(WpMock::$doingItWrong[0]['message'])->toContain('cross-site request forgery');
    });
});

describe('an action that requires a capability and is open to guests', function (): void {
    it('answers a guest with a 401, the callback does not run and the capability is not asked', function (): void {
        WpMock::$userLoggedIn = false;
        $ran = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function () use (&$ran): void {
            $ran[] = 'ran';
        }))->forGuestUsers()->withoutNonce()->requireCapability('edit_posts'));

        $result = WpMock::hooked('wp_ajax_nopriv_save_note')['callback']();

        expect($result)->toBeNull()
            ->and($ran)->toBe([])
            ->and(WpMock::$capabilityChecks)->toBe([])
            ->and(WpMock::$jsonErrors)->toBe([[
                'data' => ['code' => 'pollora_ajax_unauthenticated', 'message' => 'You must be logged in to do this.'],
                'status' => 401,
            ]])
            ->and(WpMock::callbackOn('wp_ajax_save_note'))->toBeNull();
    });

    it('still serves the logged-in side of an action open to everyone', function (): void {
        WpMock::$userCaps = ['edit_posts'];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): string => 'done'))->forAllUsers()->withoutNonce()->requireCapability('edit_posts'));

        expect(WpMock::hooked('wp_ajax_save_note')['callback']())->toBe('done');

        WpMock::$userLoggedIn = false;
        WpMock::hooked('wp_ajax_nopriv_save_note')['callback']();

        expect(WpMock::$jsonErrors[0]['status'])->toBe(401);
    });

    it('tells the developer, once, with the action, the capability and the way out', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->forAllUsers()->withoutNonce()->requireCapability('edit_posts'));

        expect(WpMock::$doingItWrong)->toHaveCount(1)
            ->and(WpMock::$doingItWrong[0]['function'])->toBe('Ajax::listen("save_note")')
            ->and(WpMock::$doingItWrong[0]['version'])->toBe('1.3.0')
            ->and(WpMock::$doingItWrong[0]['message'])->toBe(
                'The AJAX action "save_note" requires the "edit_posts" capability but is open to guests. A guest is not logged in, so guests always receive a 401 from it. Restrict it to logged-in users (->forLoggedUsers()), or drop ->requireCapability() when guests must be served.'
            );
    });

    it('is not reported while an AJAX request is being answered', function (): void {
        WpMock::$doingAjax = true;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->forAllUsers()->withoutNonce()->requireCapability('edit_posts'));

        expect(WpMock::$doingItWrong)->toBe([]);
    });
});

describe('an action that requires no capability', function (): void {
    it('is hooked as it is, whatever the user can do', function (): void {
        $callback = fn (): null => null;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', $callback))->withoutNonce());

        expect(WpMock::callbackOn('wp_ajax_save_note'))->toBe($callback)
            ->and(WpMock::$capabilityChecks)->toBe([]);
    });
});
