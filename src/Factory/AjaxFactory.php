<?php

declare(strict_types=1);

namespace Pollora\Ajax\Factory;

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Model\AjaxAction;

/**
 * Factory for creating AJAX action definitions with deferred registration.
 *
 * Each {@see AjaxAction} returned by `listen()` carries a reference to the
 * application service so that registration happens automatically when the
 * action goes out of scope (via `__destruct`). This enables fluent chaining:
 *
 *     $factory->listen('my_action', $callback)->forGuestUsers();
 *
 * Designed to be bound as a singleton in a service container (key `wp.ajax`).
 *
 * @see AjaxAction
 * @see RegisterAjaxActionService
 */
class AjaxFactory
{
    /**
     * @param  RegisterAjaxActionService  $registerService  The application service injected into every created action.
     */
    public function __construct(private readonly RegisterAjaxActionService $registerService) {}

    /**
     * Create a new AJAX action definition for the given action name and callback.
     *
     * The returned action defaults to logged-in users only.
     * Chain `->forAllUsers()` or `->forGuestUsers()` to change targeting.
     *
     * @param  string  $action  The WordPress AJAX action name.
     * @param  callable|string  $callback  The callback to execute when the action fires.
     * @return AjaxAction A fluent action instance registered on destruct.
     */
    public function listen(string $action, callable|string $callback): AjaxAction
    {
        return new AjaxAction($action, $callback, $this->registerService);
    }
}
