<?php

declare(strict_types=1);

use Pollora\Ajax\Domain\Exception\InvalidAjaxActionException;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Domain\Model\NoncePolicy;

describe('the capability of an AjaxAction', function (): void {
    it('requires none until its author says so', function (): void {
        $action = new AjaxAction('save_note', fn (): null => null);

        expect($action->getCapability())->toBeNull();
    });

    it('requires the capability it was given', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->requireCapability('edit_posts');

        expect($action->getCapability())->toBe('edit_posts');
    });

    it('keeps the last capability it was given', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->requireCapability('edit_posts')->requireCapability('manage_options');

        expect($action->getCapability())->toBe('manage_options');
    });

    it('refuses an empty capability, which would let everyone through', function (string $capability): void {
        expect(fn (): AjaxAction => (new AjaxAction('save_note', fn (): null => null))->requireCapability($capability))
            ->toThrow(InvalidAjaxActionException::class, 'The capability must not be empty.');
    })->with(['empty' => [''], 'blank' => ['  ']]);

    it('chains with the nonce and the access choices without changing them', function (): void {
        $action = (new AjaxAction('save_note', fn (): null => null))->requireNonce()->requireCapability('edit_posts')->forAllUsers();

        expect($action->getCapability())->toBe('edit_posts')
            ->and($action->getNoncePolicy())->toBe(NoncePolicy::Verify)
            ->and($action->getUserType())->toBe(AjaxAction::BOTH_USERS);
    });
});
