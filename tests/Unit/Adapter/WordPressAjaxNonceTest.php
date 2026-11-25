<?php

declare(strict_types=1);

use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Ajax;
use Pollora\Ajax\Domain\Model\AjaxAction;

beforeEach(function (): void {
    WpMock::$actions = [];
    WpMock::$filters = [];
    WpMock::$filterCalls = [];
    WpMock::$doingItWrong = [];
    WpMock::$doingAjax = false;
    WpMock::$nonceChecks = [];
    WpMock::$nonceValid = false;
    WpMock::$jsonErrors = [];
});

describe('an action that verifies a nonce', function (): void {
    it('refuses a request without a valid nonce, with a 403 and a code to recognise it', function (): void {
        $ran = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function () use (&$ran): void {
            $ran[] = 'ran';
        }))->requireNonce());

        $result = WpMock::hooked('wp_ajax_save_note')['callback']();

        expect($result)->toBeNull()
            ->and($ran)->toBe([])
            ->and(WpMock::$jsonErrors)->toBe([[
                'data' => ['code' => 'pollora_ajax_invalid_nonce', 'message' => 'Invalid or expired security token.'],
                'status' => 403,
            ]]);
    });

    it('checks the nonce for the action name, in either request field, without stopping the request itself', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce());

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$nonceChecks)->toBe([['action' => 'save_note', 'query_arg' => false, 'stop' => false]]);
    });

    it('checks the nonce action it was given', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce('notes_form'));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$nonceChecks[0]['action'])->toBe('notes_form');
    });

    it('runs the callback with its arguments, and returns what it returns, when the nonce is valid', function (): void {
        WpMock::$nonceValid = true;
        $received = [];
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', function (...$arguments) use (&$received): string {
            $received = $arguments;

            return 'done';
        }))->requireNonce());

        $result = WpMock::hooked('wp_ajax_save_note')['callback']('a', 2);

        expect($result)->toBe('done')
            ->and($received)->toBe(['a', 2])
            ->and(WpMock::$jsonErrors)->toBe([]);
    });

    it('runs a callback given as a "Class::method" string', function (): void {
        WpMock::$nonceValid = true;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', 'strtoupper'))->requireNonce());

        expect(WpMock::hooked('wp_ajax_save_note')['callback']('abc'))->toBe('ABC');
    });

    it('protects both hooks of an action open to guests', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->forAllUsers()->requireNonce());

        WpMock::hooked('wp_ajax_save_note')['callback']();
        WpMock::hooked('wp_ajax_nopriv_save_note')['callback']();

        expect(WpMock::$nonceChecks)->toHaveCount(2)
            ->and(WpMock::$jsonErrors)->toHaveCount(2)
            ->and(WpMock::hooked('wp_ajax_save_note')['callback'])->toBeInstanceOf(Closure::class);
    });
});

describe('an action that turned the nonce off on purpose', function (): void {
    it('is hooked as it is, without a check and without a notice', function (): void {
        $callback = fn (): null => null;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('track', $callback))->withoutNonce());

        expect(WpMock::hooked('wp_ajax_track')['callback'])->toBe($callback)
            ->and(WpMock::$doingItWrong)->toBe([])
            ->and(WpMock::$filterCalls)->toBe([]);
    });
});

describe('an action that never chose', function (): void {
    it('is hooked as it is and the developer is told, once, with the action and the way out', function (): void {
        $callback = fn (): null => null;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', $callback));

        expect(WpMock::hooked('wp_ajax_save_note')['callback'])->toBe($callback)
            ->and(WpMock::$doingItWrong)->toHaveCount(1)
            ->and(WpMock::$doingItWrong[0]['function'])->toBe('Ajax::listen("save_note")')
            ->and(WpMock::$doingItWrong[0]['version'])->toBe('1.2.0')
            ->and(WpMock::$doingItWrong[0]['message'])->toBe(
                'The AJAX action "save_note" does not say how it is protected against cross-site request forgery. It runs without a nonce check for now; the next major version verifies a nonce by default. Declare ->requireNonce() (#[Ajax(\'save_note\', nonce: true)]), or ->withoutNonce() (nonce: false) when no nonce makes sense. Return true from the "pollora/ajax/nonce_by_default" filter to verify a nonce on every action that did not choose.'
            );
    });

    it('is not reported while an AJAX request is being answered', function (): void {
        WpMock::$doingAjax = true;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        expect(WpMock::$doingItWrong)->toBe([]);
    });

    it('asks the filter, with the action name, whether to verify a nonce', function (): void {
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        expect(WpMock::$filterCalls)->toBe([[
            'hook' => 'pollora/ajax/nonce_by_default',
            'value' => false,
            'arguments' => ['save_note'],
        ]]);
    });

    it('verifies a nonce when the filter says to, and is not reported then', function (): void {
        WpMock::$filters['pollora/ajax/nonce_by_default'] = true;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$nonceChecks)->toHaveCount(1)
            ->and(WpMock::$nonceChecks[0]['action'])->toBe('save_note')
            ->and(WpMock::$doingItWrong)->toBe([]);
    });

    it('stays unchecked when the filter says no', function (): void {
        WpMock::$filters['pollora/ajax/nonce_by_default'] = false;
        $callback = fn (): null => null;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', $callback));

        expect(WpMock::hooked('wp_ajax_save_note')['callback'])->toBe($callback)
            ->and(WpMock::$nonceChecks)->toBe([]);
    });

    it('takes any truthy answer of the filter as a yes', function (): void {
        WpMock::$filters['pollora/ajax/nonce_by_default'] = 1;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        expect(WpMock::hooked('wp_ajax_save_note')['callback'])->toBeInstanceOf(Closure::class);
    });
});

describe('the nonce a page sends', function (): void {
    it('is created for the nonce action', function (): void {
        expect(Ajax::nonce('save_note'))->toBe('nonce-for-save_note');
    });
});
