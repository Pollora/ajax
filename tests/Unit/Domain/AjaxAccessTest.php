<?php

declare(strict_types=1);

use Pollora\Ajax\Domain\Model\AjaxAccess;
use Pollora\Ajax\Domain\Model\AjaxAction;

describe('AjaxAccess', function (): void {
    it('maps each case to the matching user type on the action', function (AjaxAccess $access, string $expected): void {
        $action = new AjaxAction('access', 'strlen');

        $result = $access->applyTo($action);

        expect($result)->toBe($action)
            ->and($action->getUserType())->toBe($expected);
    })->with([
        'logged' => [AjaxAccess::LOGGED, AjaxAction::LOGGED_USERS],
        'guest' => [AjaxAccess::GUEST, AjaxAction::GUEST_USERS],
        'all' => [AjaxAccess::ALL, AjaxAction::BOTH_USERS],
    ]);

    it('overrides a previously chosen audience', function (): void {
        $action = (new AjaxAction('access', 'strlen'))->forAllUsers();

        AjaxAccess::LOGGED->applyTo($action);

        expect($action->getUserType())->toBe(AjaxAction::LOGGED_USERS);
    });

    it('is backed by the stable string values', function (): void {
        expect(AjaxAccess::from('both'))->toBe(AjaxAccess::ALL)
            ->and(AjaxAccess::tryFrom('nope'))->toBeNull();
    });
});
