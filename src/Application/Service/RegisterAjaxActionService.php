<?php

declare(strict_types=1);

namespace Pollora\Ajax\Application\Service;

use Pollora\Ajax\AjaxAction;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

/**
 * Application service that orchestrates AJAX action registration.
 *
 * Acts as the single entry point for the use-case "register an AJAX action".
 * Delegates the actual platform-specific registration to the output port.
 *
 * @see AjaxActionRegistrarPort
 */
class RegisterAjaxActionService
{
    /**
     * @param  AjaxActionRegistrarPort  $registrar  The output port that performs platform-specific registration.
     */
    public function __construct(private readonly AjaxActionRegistrarPort $registrar) {}

    /**
     * Register the given AJAX action through the output port.
     *
     * @param  AjaxAction  $action  The action definition to register.
     */
    public function execute(AjaxAction $action): void
    {
        $this->registrar->register($action);
    }
}
