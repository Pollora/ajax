<?php

declare(strict_types=1);

namespace Pollora\Ajax;

use Pollora\Ajax\Adapter\Out\WordPress\ScriptInjectionAdapter;
use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Factory\AjaxFactory;

/**
 * Static facade for WordPress AJAX functionality (standalone usage).
 *
 * Provides a clean, container-free entry point for registering AJAX actions.
 * When used inside the Pollora framework, prefer the Laravel facade
 * `Pollora\Support\Facades\Ajax` which resolves from the service container.
 *
 * Usage:
 *     Ajax::listen('my_action', $callback);                // logged-in users (default)
 *     Ajax::listen('public', $callback)->forAllUsers();    // everyone
 *     Ajax::listen('guest', $callback)->forGuestUsers();   // guests only
 *
 * @see AjaxFactory
 */
class Ajax
{
    /**
     * Lazily-initialized factory singleton for standalone usage.
     */
    private static ?AjaxFactory $factory = null;

    /**
     * Register a WordPress AJAX action handler.
     *
     * Returns a fluent {@see AjaxAction} that defaults to logged-in users.
     * Chain `->forAllUsers()` or `->forGuestUsers()` to change targeting.
     *
     * @param  string  $action  The WordPress AJAX action name.
     * @param  callable|string  $callback  The callback to execute when the action fires.
     * @return AjaxAction A fluent action instance registered on destruct.
     */
    public static function listen(string $action, callable|string $callback): AjaxAction
    {
        return self::getFactory()->listen($action, $callback);
    }

    /**
     * Inject the AJAX URL as a JavaScript global in the HTML `<head>`.
     *
     * Hooks into `wp_head` to output `Pollora.ajaxurl` for frontend use.
     */
    public static function injectScripts(): void
    {
        (new ScriptInjectionAdapter)->registerAjaxUrlScript();
    }

    /**
     * Get or create the internal factory singleton.
     *
     * @return AjaxFactory The factory wired with the WordPress adapter.
     */
    private static function getFactory(): AjaxFactory
    {
        if (! self::$factory instanceof AjaxFactory) {
            self::$factory = new AjaxFactory(
                new RegisterAjaxActionService(new WordPressAjaxActionRegistrar)
            );
        }

        return self::$factory;
    }
}
