<?php

declare(strict_types=1);

namespace Pollora\Ajax\Application\Service;

use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

/**
 * Application service to orchestrate the registration of an AjaxAction via the domain port.
 */
class RegisterAjaxActionService
{
    public function __construct(private readonly AjaxActionRegistrarPort $registrar) {}

    /**
     * Register the given AjaxAction using the domain port.
     */
    public function execute(AjaxAction $action): void
    {
        $this->registrar->register($action);
    }
}
