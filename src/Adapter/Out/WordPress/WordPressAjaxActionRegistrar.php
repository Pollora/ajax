<?php

declare(strict_types=1);

namespace Pollora\Ajax\Adapter\Out\WordPress;

use Closure;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Domain\Model\NoncePolicy;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

/**
 * Translates an {@see AjaxAction} into the appropriate `wp_ajax_*`
 * and/or `wp_ajax_nopriv_*` hook registrations depending on the
 * action's user type, and applies what the action says about its nonce.
 */
class WordPressAjaxActionRegistrar implements AjaxActionRegistrarPort
{
    /**
     * Filter: return `true` to verify a nonce on every action that did not choose, as the next major version will.
     */
    public const NONCE_BY_DEFAULT_FILTER = 'pollora/ajax/nonce_by_default';

    /**
     * Error code of the JSON response a request without a valid nonce receives.
     */
    public const INVALID_NONCE_CODE = 'pollora_ajax_invalid_nonce';

    /**
     * Registers `wp_ajax_{name}` for logged-in users and/or
     * `wp_ajax_nopriv_{name}` for guests, based on the action's user type.
     */
    public function register(AjaxAction $action): void
    {
        $callback = $this->protect($action);

        if ($action->isBothOrLoggedUsers()) {
            add_action('wp_ajax_'.$action->getName(), $callback);
        }

        if ($action->isBothOrGuestUsers()) {
            add_action('wp_ajax_nopriv_'.$action->getName(), $callback);
        }
    }

    /**
     * The callback to hook: the action's own, wrapped in a nonce check when the action verifies one.
     */
    private function protect(AjaxAction $action): mixed
    {
        $policy = $action->getNoncePolicy();

        if ($policy === NoncePolicy::Undecided) {
            if ($this->verifiesByDefault($action)) {
                $policy = NoncePolicy::Verify;
            } else {
                $this->reportUndecided($action);

                return $action->getCallback();
            }
        }

        return $policy === NoncePolicy::Verify ? $this->verified($action) : $action->getCallback();
    }

    private function verifiesByDefault(AjaxAction $action): bool
    {
        return (bool) apply_filters(self::NONCE_BY_DEFAULT_FILTER, false, $action->getName());
    }

    private function verified(AjaxAction $action): Closure
    {
        $callback = $action->getCallback();
        $nonceAction = $action->getNonceAction();

        return static function (mixed ...$arguments) use ($callback, $nonceAction): mixed {
            if (! check_ajax_referer($nonceAction, false, false)) {
                wp_send_json_error([
                    'code' => self::INVALID_NONCE_CODE,
                    'message' => 'Invalid or expired security token.',
                ], 403);

                return null;
            }

            return call_user_func_array($callback, $arguments);
        };
    }

    /**
     * Tells the developer that the action never chose how it is protected. Skipped during an AJAX request: the
     * notice would be printed into the JSON the browser is waiting for.
     */
    private function reportUndecided(AjaxAction $action): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        _doing_it_wrong(
            'Ajax::listen("'.$action->getName().'")',
            sprintf(
                'The AJAX action "%1$s" does not say how it is protected against cross-site request forgery. It runs without a nonce check for now; the next major version verifies a nonce by default. Declare ->requireNonce() (#[Ajax(\'%1$s\', nonce: true)]), or ->withoutNonce() (nonce: false) when no nonce makes sense. Return true from the "%2$s" filter to verify a nonce on every action that did not choose.',
                $action->getName(),
                self::NONCE_BY_DEFAULT_FILTER,
            ),
            '1.2.0',
        );
    }
}
