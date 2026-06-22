<?php

declare(strict_types=1);

namespace Pollora\Ajax;

/**
 * Enum defining the audience targeting for an AJAX action.
 *
 * Controls which WordPress hooks are registered for a given action:
 * `wp_ajax_*` (authenticated), `wp_ajax_nopriv_*` (guests), or both.
 *
 * Defaults to {@see self::LOGGED} (security-by-default): endpoints are not
 * exposed to unauthenticated visitors unless explicitly opted in.
 *
 * This is the **public stable API** — import `Pollora\Ajax\AjaxAccess`,
 * not the internal domain path.
 *
 * @see AjaxAction
 */
enum AjaxAccess: string
{
    /**
     * Apply this access level to the given AJAX action domain model.
     *
     * @param  AjaxAction  $action  The action to configure.
     * @return AjaxAction The configured action (fluent).
     */
    public function applyTo(AjaxAction $action): AjaxAction
    {
        return match ($this) {
            self::LOGGED => $action->forLoggedUsers(),
            self::GUEST => $action->forGuestUsers(),
            self::ALL => $action->forAllUsers(),
        };
    }
    /**
     * Logged-in users only — registers `wp_ajax_{action}`.
     * This is the default when no access is specified.
     */
    case LOGGED = 'logged';

    /**
     * Guest (unauthenticated) users only — registers `wp_ajax_nopriv_{action}`.
     */
    case GUEST = 'guest';

    /**
     * All users (logged-in and guests) — registers both hooks.
     * Must be opted into explicitly.
     */
    case ALL = 'both';
}
