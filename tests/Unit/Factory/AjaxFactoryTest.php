<?php

declare(strict_types=1);

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Exception\InvalidAjaxActionException;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

function ajaxFactoryRecorder(): object
{
    return new class implements AjaxActionRegistrarPort
    {
        /** @var list<AjaxAction> */
        public array $registered = [];

        public function register(AjaxAction $action): void
        {
            $this->registered[] = $action;
        }
    };
}

describe('AjaxFactory', function (): void {
    it('creates an action carrying the name and callback', function (): void {
        $factory = new AjaxFactory(new RegisterAjaxActionService(ajaxFactoryRecorder()));

        $action = $factory->listen('save', 'strlen');

        expect($action->getName())->toBe('save')
            ->and($action->getCallback())->toBe('strlen')
            ->and($action->getUserType())->toBe(AjaxAction::LOGGED_USERS);
    });

    it('registers the created action through the service when it is destroyed', function (): void {
        $registrar = ajaxFactoryRecorder();
        $factory = new AjaxFactory(new RegisterAjaxActionService($registrar));

        $factory->listen('save', 'strlen')->forGuestUsers();

        expect($registrar->registered)->toHaveCount(1)
            ->and($registrar->registered[0]->getName())->toBe('save')
            ->and($registrar->registered[0]->getUserType())->toBe(AjaxAction::GUEST_USERS);
    });

    it('rejects an empty action name', function (): void {
        $factory = new AjaxFactory(new RegisterAjaxActionService(ajaxFactoryRecorder()));

        $factory->listen('', 'strlen');
    })->throws(InvalidAjaxActionException::class);
});
