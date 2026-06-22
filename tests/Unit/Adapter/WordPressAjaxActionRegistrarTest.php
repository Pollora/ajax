<?php

declare(strict_types=1);

use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Domain\Model\AjaxAction;

beforeEach(function (): void {
    $GLOBALS['wp_actions'] = [];
});

describe('WordPressAjaxActionRegistrar', function (): void {
    it('registers both hooks for BOTH_USERS', function (): void {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = new AjaxAction('my_action', function (): void {});
        $registrar->register($action);

        $hooks = array_column($GLOBALS['wp_actions'], 'hook');
        expect($hooks)->toContain('wp_ajax_my_action')
            ->and($hooks)->toContain('wp_ajax_nopriv_my_action');
    });

    it('registers only wp_ajax for LOGGED_USERS', function (): void {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function (): void {}))->forLoggedUsers();
        $registrar->register($action);

        $hooks = array_column($GLOBALS['wp_actions'], 'hook');
        expect($hooks)->toContain('wp_ajax_my_action')
            ->and($hooks)->not->toContain('wp_ajax_nopriv_my_action');
    });

    it('registers only wp_ajax_nopriv for GUEST_USERS', function (): void {
        $registrar = new WordPressAjaxActionRegistrar;
        $action = (new AjaxAction('my_action', function (): void {}))->forGuestUsers();
        $registrar->register($action);

        $hooks = array_column($GLOBALS['wp_actions'], 'hook');
        expect($hooks)->not->toContain('wp_ajax_my_action')
            ->and($hooks)->toContain('wp_ajax_nopriv_my_action');
    });
});
