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
                'data' => [
                    'code' => 'pollora_ajax_invalid_nonce',
                    'message' => 'Invalid or expired security token.',
                    'hint' => 'Send a nonce for "save_note" (Ajax::nonce(\'save_note\')) in the _ajax_nonce or _wpnonce request field.',
                ],
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

    it('names the nonce action it was given in the hint of the refusal', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce('notes_form'));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$jsonErrors[0]['data'])->toHaveKey(
            'hint',
            'Send a nonce for "notes_form" (Ajax::nonce(\'notes_form\')) in the _ajax_nonce or _wpnonce request field.',
        );
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
    it('refuses a request without a valid nonce, and says why a nonce was asked and how to opt out', function (): void {
        $ran = [];
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', function () use (&$ran): void {
            $ran[] = 'ran';
        }));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect($ran)->toBe([])
            ->and(WpMock::$nonceChecks)->toBe([['action' => 'save_note', 'query_arg' => false, 'stop' => false]])
            ->and(WpMock::$jsonErrors)->toBe([[
                'data' => [
                    'code' => 'pollora_ajax_invalid_nonce',
                    'message' => 'Invalid or expired security token.',
                    'hint' => 'Send a nonce for "save_note" (Ajax::nonce(\'save_note\')) in the _ajax_nonce or _wpnonce request field. This action chose no nonce policy, so pollora/ajax verifies one by default; declare ->withoutNonce() (nonce: false) when no nonce makes sense.',
                ],
                'status' => 403,
            ]]);
    });

    it('runs the callback when the request carries a valid nonce', function (): void {
        WpMock::$nonceValid = true;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): string => 'done'));

        expect(WpMock::hooked('wp_ajax_save_note')['callback']())->toBe('done');
    });

    it('is not reported when only logged-in users reach it: it is protected', function (): void {
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        expect(WpMock::$doingItWrong)->toBe([]);
    });

    it('verifies the guest hooks too', function (): void {
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->forAllUsers());

        WpMock::hooked('wp_ajax_save_note')['callback']();
        WpMock::hooked('wp_ajax_nopriv_save_note')['callback']();

        expect(WpMock::$nonceChecks)->toHaveCount(2)
            ->and(WpMock::$jsonErrors)->toHaveCount(2);
    });

    it('tells the developer, once, to choose when guests reach it', function (string $audience): void {
        $action = new AjaxAction('load_more', fn (): null => null);
        $action->setUserType($audience);
        (new WordPressAjaxActionRegistrar)->register($action);

        expect(WpMock::$doingItWrong)->toHaveCount(1)
            ->and(WpMock::$doingItWrong[0]['function'])->toBe('Ajax::listen("load_more")')
            ->and(WpMock::$doingItWrong[0]['version'])->toBe('2.0.0')
            ->and(WpMock::$doingItWrong[0]['message'])->toBe(
                'The AJAX action "load_more" is open to guests and chose no nonce policy, so a nonce is verified for them too. A guest\'s nonce is tied to no user, and a cached page can serve an expired one. Declare ->requireNonce() (#[Ajax(\'load_more\', nonce: true)]) to keep the check, or ->withoutNonce() (nonce: false) for a public or cached endpoint; that removes the check for logged-in users as well.'
            );
    })->with([AjaxAction::GUEST_USERS, AjaxAction::BOTH_USERS]);

    it('does not report it while an AJAX request is being answered', function (): void {
        WpMock::$doingAjax = true;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('load_more', fn (): null => null))->forGuestUsers());

        expect(WpMock::$doingItWrong)->toBe([]);
    });

    it('asks the filter, with the action name, and verifies a nonce unless told otherwise', function (): void {
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        expect(WpMock::$filterCalls)->toBe([[
            'hook' => 'pollora/ajax/nonce_by_default',
            'value' => true,
            'arguments' => ['save_note'],
        ]]);
    });

    it('stays protected unless the filter answers exactly false', function (mixed $answer): void {
        WpMock::$filters['pollora/ajax/nonce_by_default'] = $answer;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$nonceChecks)->toHaveCount(1);
    })->with([
        'a filter that forgot to return' => [null],
        'zero' => [0],
        'an empty string' => [''],
        'true' => [true],
    ]);

    it('runs without a check when the filter says no, and the developer is told', function (): void {
        WpMock::$filters['pollora/ajax/nonce_by_default'] = false;
        $callback = fn (): null => null;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', $callback))->forAllUsers());

        expect(WpMock::hooked('wp_ajax_save_note')['callback'])->toBe($callback)
            ->and(WpMock::hooked('wp_ajax_nopriv_save_note')['callback'])->toBe($callback)
            ->and(WpMock::$nonceChecks)->toBe([])
            ->and(WpMock::$doingItWrong)->toHaveCount(1)
            ->and(WpMock::$doingItWrong[0]['function'])->toBe('Ajax::listen("save_note")')
            ->and(WpMock::$doingItWrong[0]['version'])->toBe('2.0.0')
            ->and(WpMock::$doingItWrong[0]['message'])->toBe(
                'The AJAX action "save_note" runs without a nonce check: it chose no nonce policy and the "pollora/ajax/nonce_by_default" filter returned false. Declare ->requireNonce() (#[Ajax(\'save_note\', nonce: true)]), or ->withoutNonce() (nonce: false) when no nonce makes sense.'
            );
    });

    it('is not reported during an AJAX request when the filter says no', function (): void {
        WpMock::$doingAjax = true;
        WpMock::$filters['pollora/ajax/nonce_by_default'] = false;
        (new WordPressAjaxActionRegistrar)->register(new AjaxAction('save_note', fn (): null => null));

        expect(WpMock::$doingItWrong)->toBe([]);
    });

    it('leaves an explicit requirement alone, whatever the filter says', function (): void {
        WpMock::$filters['pollora/ajax/nonce_by_default'] = false;
        (new WordPressAjaxActionRegistrar)->register((new AjaxAction('save_note', fn (): null => null))->requireNonce());

        WpMock::hooked('wp_ajax_save_note')['callback']();

        expect(WpMock::$nonceChecks)->toHaveCount(1);
    });
});

describe('the nonce a page sends', function (): void {
    it('is created for the nonce action', function (): void {
        expect(Ajax::nonce('save_note'))->toBe('nonce-for-save_note');
    });
});
