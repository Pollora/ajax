<?php

declare(strict_types=1);

use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Domain\Exception\InvalidAjaxActionException;
use Pollora\Ajax\Domain\Model\AjaxAction;

beforeEach(function (): void {
    WpMock::$actions = [];
});

describe('WordPressAjaxActionRegistrar', function (): void {
    it('defaults to wp_ajax only (logged-in users)', function (): void {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = new AjaxAction('my_action', function (): void {});
        $registrar->register($action);

        $hooks = array_column(WpMock::$actions, 'hook');
        expect($hooks)->toContain('wp_ajax_my_action')
            ->and($hooks)->not->toContain('wp_ajax_nopriv_my_action');
    });

    it('registers both hooks when forAllUsers is called', function (): void {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function (): void {}))->forAllUsers();
        $registrar->register($action);

        $hooks = array_column(WpMock::$actions, 'hook');
        expect($hooks)->toContain('wp_ajax_my_action')
            ->and($hooks)->toContain('wp_ajax_nopriv_my_action');
    });

    it('registers only wp_ajax for LOGGED_USERS', function (): void {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function (): void {}))->forLoggedUsers();
        $registrar->register($action);

        $hooks = array_column(WpMock::$actions, 'hook');
        expect($hooks)->toContain('wp_ajax_my_action')
            ->and($hooks)->not->toContain('wp_ajax_nopriv_my_action');
    });

    it('registers only wp_ajax_nopriv for GUEST_USERS', function (): void {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function (): void {}))->forGuestUsers();
        $registrar->register($action);

        $hooks = array_column(WpMock::$actions, 'hook');
        expect($hooks)->not->toContain('wp_ajax_my_action')
            ->and($hooks)->toContain('wp_ajax_nopriv_my_action');
    });

    it('resolves a callback that is not callable yet when the hook runs', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('late', 'pollora_ajax_defined_late'))->withoutNonce());

        if (! function_exists('pollora_ajax_defined_late')) {
            function pollora_ajax_defined_late(string $word): string
            {
                return strtoupper($word);
            }
        }

        expect(WpMock::hooked('wp_ajax_late')['callback']('ok'))->toBe('OK');
    });

    it('fails with a named exception when a late callback never becomes callable', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('never', 'pollora_ajax_never_defined'))->withoutNonce());

        expect(fn (): mixed => WpMock::hooked('wp_ajax_never')['callback']())->toThrow(InvalidAjaxActionException::class);
    });
});
