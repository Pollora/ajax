<?php

declare(strict_types=1);

namespace Pollora\Ajax\Adapter\Out\WordPress;

use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

/**
 * WordPress adapter for registering AjaxActions using WordPress hooks.
 * Implements the port for AJAX action registration.
 */
class WordPressAjaxActionRegistrar implements AjaxActionRegistrarPort
{
    /**
     * Register the given AjaxAction using WordPress hooks.
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
