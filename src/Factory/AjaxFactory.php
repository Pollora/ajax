<?php

declare(strict_types=1);

namespace Pollora\Ajax\Factory;

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Model\AjaxAction;

/**
 * Factory for creating AjaxAction instances and registering them via the application service.
 */
class AjaxFactory
{
    public function __construct(private readonly RegisterAjaxActionService $registerService) {}

    /**
     * Create a new AjaxAction instance for the given action and callback.
     */
    public function listen(string $action, callable|string $callback): AjaxAction
    {
        return new AjaxAction($action, $callback, $this->registerService);
    }
}
