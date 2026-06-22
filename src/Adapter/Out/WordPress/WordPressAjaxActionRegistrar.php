<?php

declare(strict_types=1);

namespace Pollora\Ajax\Adapter\Out\WordPress;

use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

/**
 * WordPress adapter for registering AJAX actions via `add_action()`.
 *
 * Translates an {@see AjaxAction} into the appropriate `wp_ajax_*`
 * and/or `wp_ajax_nopriv_*` hook registrations depending on the
 * action's user-type targeting.
 */
class WordPressAjaxActionRegistrar implements AjaxActionRegistrarPort
{
    /**
     * Register the given AJAX action using WordPress hooks.
     *
     * Registers `wp_ajax_{name}` for logged-in users and/or
     * `wp_ajax_nopriv_{name}` for guests, based on the action's user type.
     *
     * @param  AjaxAction  $action  The action definition to register.
     */
    public function register(AjaxAction $action): void
    {
        if ($action->isBothOrLoggedUsers()) {
            add_action('wp_ajax_'.$action->getName(), $action->getCallback());
        }

        if ($action->isBothOrGuestUsers()) {
            add_action('wp_ajax_nopriv_'.$action->getName(), $action->getCallback());
        }
    }
}
