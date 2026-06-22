<?php

declare(strict_types=1);

namespace Pollora\Ajax;

use Pollora\Ajax\Adapter\Out\WordPress\ScriptInjectionAdapter;
use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Model\AjaxAction;

/**
 * Facade for WordPress AJAX functionality.
 *
 * Provides a clean interface for registering and handling WordPress AJAX actions
 * with improved type safety and modern PHP syntax.
 */
class Ajax
{
    private static ?RegisterAjaxActionService $registerService = null;

    /**
     * Register an AJAX action handler.
     */
    public static function listen(string $action, callable|string $callback): AjaxAction
    {
        return new AjaxAction($action, $callback, self::getRegisterService());
    }

    /**
     * Inject the AJAX URL as a JS variable in the HTML head.
     */
    public static function injectScripts(): void
    {
        (new ScriptInjectionAdapter)->registerAjaxUrlScript();
    }

    private static function getRegisterService(): RegisterAjaxActionService
    {
        if (! self::$registerService instanceof RegisterAjaxActionService) {
            self::$registerService = new RegisterAjaxActionService(new WordPressAjaxActionRegistrar);
        }

        return self::$registerService;
    }
}
