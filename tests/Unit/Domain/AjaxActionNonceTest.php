<?php

declare(strict_types=1);

use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Domain\Model\NoncePolicy;

describe('the nonce policy of an AjaxAction', function (): void {
    it('has not chosen until its author does', function (): void {
        $action = new AjaxAction('save_note', fn (): null => null);

        expect($action->getNoncePolicy())->toBe(NoncePolicy::Undecided)
            ->and($action->getNonceAction())->toBe('save_note');
    });

    it('verifies a nonce named after the action when asked to', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->requireNonce();

        expect($action->getNoncePolicy())->toBe(NoncePolicy::Verify)
            ->and($action->getNonceAction())->toBe('save_note');
    });

    it('verifies a nonce of another name when one is given', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->requireNonce('notes_form');

        expect($action->getNoncePolicy())->toBe(NoncePolicy::Verify)
            ->and($action->getNonceAction())->toBe('notes_form');
    });

    it('can say, on purpose, that no nonce protects it', function (): void {
        $action = (new AjaxAction('track', fn (): null => null))->withoutNonce();

        expect($action->getNoncePolicy())->toBe(NoncePolicy::Disabled);
    });

    it('forgets the nonce action it was given when the nonce is turned off', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->requireNonce('notes_form')->withoutNonce();

        expect($action->getNoncePolicy())->toBe(NoncePolicy::Disabled)
            ->and($action->getNonceAction())->toBe('save_note');
    });

    it('keeps the last choice, whatever it was before', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->withoutNonce()->requireNonce('again');

        expect($action->getNoncePolicy())->toBe(NoncePolicy::Verify)
            ->and($action->getNonceAction())->toBe('again');
    });

    it('chains with the access choices', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->forAllUsers()->requireNonce();

        expect($action)->toBeInstanceOf(AjaxAction::class)
            ->and($action->getUserType())->toBe(AjaxAction::BOTH_USERS)
            ->and($action->getNoncePolicy())->toBe(NoncePolicy::Verify);
    });

    it('names its three states', function (): void {
        expect(array_map(static fn (NoncePolicy $policy): string => $policy->value, NoncePolicy::cases()))
            ->toBe(['undecided', 'verify', 'disabled']);
    });
});
