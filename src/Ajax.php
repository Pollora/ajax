<?php

declare(strict_types=1);

namespace Pollora\Ajax;

use Pollora\Ajax\Adapter\Out\WordPress\ScriptInjectionAdapter;
use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Factory\AjaxFactory;

/**
 * Static facade for WordPress AJAX functionality.
 *
 * Provides a clean interface for registering and handling WordPress AJAX actions
 * without requiring a service container (standalone usage).
 */
class Ajax
{
    private static ?AjaxFactory $factory = null;

    /**
     * Register an AJAX action handler.
     */
    public static function listen(string $action, callable|string $callback): AjaxAction
    {
        return self::getFactory()->listen($action, $callback);
    }

    /**
     * Inject the AJAX URL as a JS variable in the HTML head.
     */
    public static function injectScripts(): void
    {
        (new ScriptInjectionAdapter)->registerAjaxUrlScript();
    }

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
