<?php

declare(strict_types=1);

use Pollora\Ajax\Ajax;
use Pollora\Ajax\Domain\Model\AjaxAction;

beforeEach(function (): void {
    $GLOBALS['wp_actions'] = [];
});

afterEach(function (): void {
    unset($GLOBALS['wp_actions']);
});

describe('Ajax static facade', function (): void {
    it('returns a fluent AjaxAction defaulting to logged-in users', function (): void {
        $action = Ajax::listen('facade_default', fn () => null);

        expect($action)->toBeInstanceOf(AjaxAction::class)
            ->and($action->getUserType())->toBe(AjaxAction::LOGGED_USERS);
    });

    it('registers the wp_ajax hook when the chain ends', function (): void {
        Ajax::listen('facade_logged', 'strlen');

        $hooks = array_column($GLOBALS['wp_actions'], 'hook');
        expect($hooks)->toBe(['wp_ajax_facade_logged']);
    });

    it('registers both hooks for forAllUsers once the chain ends', function (): void {
        Ajax::listen('facade_all', 'strlen')->forAllUsers();

        $hooks = array_column($GLOBALS['wp_actions'], 'hook');
        expect($hooks)->toContain('wp_ajax_facade_all')
            ->and($hooks)->toContain('wp_ajax_nopriv_facade_all');
    });

    it('registers only the nopriv hook for forGuestUsers', function (): void {
        Ajax::listen('facade_guest', 'strlen')->forGuestUsers();

        $hooks = array_column($GLOBALS['wp_actions'], 'hook');
        expect($hooks)->toBe(['wp_ajax_nopriv_facade_guest']);
    });

    it('reuses the same wiring across calls', function (): void {
        Ajax::listen('facade_first', 'strlen');
        Ajax::listen('facade_second', 'strlen');

        $hooks = array_column($GLOBALS['wp_actions'], 'hook');
        expect($hooks)->toBe(['wp_ajax_facade_first', 'wp_ajax_facade_second']);
    });

    it('injects the ajax url script on wp_head at priority 1', function (): void {
        Ajax::injectScripts();

        expect($GLOBALS['wp_actions'])->toHaveCount(1)
            ->and($GLOBALS['wp_actions'][0]['hook'])->toBe('wp_head')
            ->and($GLOBALS['wp_actions'][0]['priority'])->toBe(1);
    });
});
