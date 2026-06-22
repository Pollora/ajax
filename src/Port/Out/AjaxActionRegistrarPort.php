<?php

declare(strict_types=1);

namespace Pollora\Ajax\Port\Out;

use Pollora\Ajax\Domain\Model\AjaxAction;

/**
 * Output port for registering AJAX actions in the underlying CMS.
 *
 * Implementations translate the domain {@see AjaxAction} into
 * platform-specific hook registrations (e.g. WordPress `add_action()`).
 */
interface AjaxActionRegistrarPort
{
    /**
     * Register the given AJAX action with the underlying platform.
     *
     * @param  AjaxAction  $action  The action definition to register.
     */
    public function register(AjaxAction $action): void;
}
