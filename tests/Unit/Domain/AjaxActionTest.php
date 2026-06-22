<?php

declare(strict_types=1);

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Exception\InvalidAjaxActionException;
use Pollora\Ajax\Domain\Model\AjaxAction;

class DummyRegisterAjaxActionService extends RegisterAjaxActionService
{
    public array $calls = [];

    public function __construct() {}

    public function execute($action): void
    {
        $this->calls[] = $action;
    }
}

describe('AjaxAction', function (): void {
    it('defaults to logged-in users only for security', function (): void {
        $action = new AjaxAction('my_action', function (): void {});
        expect($action->getName())->toBe('my_action')
            ->and($action->getUserType())->toBe(AjaxAction::LOGGED_USERS)
            ->and(is_callable($action->getCallback()))->toBeTrue();
    });

    it('throws exception if name or callback is empty', function (): void {
        expect(fn (): AjaxAction => new AjaxAction('', function (): void {}))->toThrow(InvalidAjaxActionException::class)
            ->and(fn (): AjaxAction => new AjaxAction('my_action', null))->toThrow(InvalidAjaxActionException::class); // @phpstan-ignore argument.type
    });

    it('can set user type to logged or guest', function (): void {
        $action = new AjaxAction('my_action', function (): void {});
        $action->forLoggedUsers();

        expect($action->getUserType())->toBe(AjaxAction::LOGGED_USERS);
        $action->forGuestUsers();
        expect($action->getUserType())->toBe(AjaxAction::GUEST_USERS);
    });

    it('isBothOrLoggedUsers and isBothOrGuestUsers logic works', function (): void {
        // Default: logged only
        $action = new AjaxAction('my_action', function (): void {});
        expect($action->isBothOrLoggedUsers())->toBeTrue()
            ->and($action->isBothOrGuestUsers())->toBeFalse();

        // Explicit: all users
        $action->forAllUsers();
        expect($action->isBothOrLoggedUsers())->toBeTrue()
            ->and($action->isBothOrGuestUsers())->toBeTrue();

        // Logged only
        $action->forLoggedUsers();
        expect($action->isBothOrLoggedUsers())->toBeTrue()
            ->and($action->isBothOrGuestUsers())->toBeFalse();

        // Guest only
        $action->forGuestUsers();
        expect($action->isBothOrGuestUsers())->toBeTrue()
            ->and($action->isBothOrLoggedUsers())->toBeFalse();
    });

    it('registers via RegisterAjaxActionService on destruct', function (): void {
        $mockService = new DummyRegisterAjaxActionService;
        $action = new AjaxAction('my_action', function (): void {}, $mockService);
        unset($action);
        expect($mockService->calls)->toHaveCount(1)
            ->and($mockService->calls[0]->getName())->toBe('my_action');
    });
});
