<?php

declare(strict_types=1);

namespace Pollora\Ajax\Port\Out;

use Pollora\Ajax\Domain\Model\AjaxAction;

/**
 * Port interface for registering an AjaxAction in the system.
 */
interface AjaxActionRegistrarPort
{
    /**
     * Register the given AjaxAction.
     */
    public function register(AjaxAction $action): void;
}
