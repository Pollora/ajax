<?php

declare(strict_types=1);

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

describe('RegisterAjaxActionService', function (): void {
    it('delegates registration of the given action to the port', function (): void {
        $registrar = new class implements AjaxActionRegistrarPort
        {
            /** @var list<AjaxAction> */
            public array $registered = [];

            public function register(AjaxAction $action): void
            {
                $this->registered[] = $action;
            }
        };
        $action = new AjaxAction('delegated', 'strlen');

        (new RegisterAjaxActionService($registrar))->execute($action);

        expect($registrar->registered)->toBe([$action]);
    });
});
