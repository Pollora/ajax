<?php

declare(strict_types=1);

namespace Pollora\Ajax\Adapter\Out\WordPress;

use Closure;
use Pollora\Ajax\Domain\Exception\InvalidAjaxActionException;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Domain\Model\NoncePolicy;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

/**
 * Translates an {@see AjaxAction} into the appropriate `wp_ajax_*`
 * and/or `wp_ajax_nopriv_*` hook registrations depending on the
 * action's user type, and applies what the action says about its nonce and the capability it requires.
 */
class WordPressAjaxActionRegistrar implements AjaxActionRegistrarPort
{
    /**
     * Filter: return `false` to run the actions that did not choose without a nonce check, as 1.x did. Only a strict
     * `false` does: any other answer keeps the check.
     */
    public const NONCE_BY_DEFAULT_FILTER = 'pollora/ajax/nonce_by_default';

    /**
     * Error code of the JSON response a request without a valid nonce receives.
     */
    public const INVALID_NONCE_CODE = 'pollora_ajax_invalid_nonce';

    /**
     * Error code of the JSON response a logged-in user who lacks the required capability receives.
     */
    public const FORBIDDEN_CODE = 'pollora_ajax_forbidden';

    /**
     * Error code of the JSON response a guest receives from an action that requires a capability.
     */
    public const UNAUTHENTICATED_CODE = 'pollora_ajax_unauthenticated';

    /**
     * @param  bool|null  $debug  Whether a refused request is told which nonce was expected; the `WP_DEBUG` constant
     *                            decides when null.
     */
    public function __construct(private readonly ?bool $debug = null) {}

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

            if ($action->getCapability() !== null) {
                $this->reportCapabilityForGuests($action);
            }
        }
    }

    /**
     * The callback to hook: the action's own, wrapped in the checks the action asks for. The nonce is checked
     * first (it tells where the request comes from), then the capability (what the user may do), then the
     * callback runs.
     */
    private function protect(AjaxAction $action): callable
    {
        $callback = $this->resolvable($action->getCallback());
        $capability = $action->getCapability();

        if ($capability !== null) {
            $callback = $this->authorized($callback, $capability);
        }

        return match ($action->getNoncePolicy()) {
            NoncePolicy::Verify => $this->verified($action, $callback),
            NoncePolicy::Disabled => $callback,
            NoncePolicy::Undecided => $this->protectUndecided($action, $callback),
        };
    }

    /**
     * An action that did not choose verifies a nonce, unless the filter says otherwise.
     */
    private function protectUndecided(AjaxAction $action, callable $callback): callable
    {
        if (! $this->verifiesByDefault($action)) {
            $this->reportUnchecked($action);

            return $callback;
        }

        if ($action->isBothOrGuestUsers()) {
            $this->reportUndecidedForGuests($action);
        }

        return $this->verified($action, $callback);
    }

    /**
     * The callback as something WordPress can hook. A callback that is not callable yet (the name of a function
     * the file defines further down) is resolved when the hook runs, as it always was; if it still is not
     * callable then, the request fails with a named exception rather than a bare TypeError.
     */
    private function resolvable(mixed $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        return static fn (mixed ...$arguments): mixed => self::callLate($callback, $arguments);
    }

    /**
     * Runs a callback that was not callable when the action was registered.
     *
     * @param  array<int|string, mixed>  $arguments
     *
     * @throws InvalidAjaxActionException When it is still not callable.
     */
    private static function callLate(mixed $callback, array $arguments): mixed
    {
        if (! is_callable($callback)) {
            throw new InvalidAjaxActionException('The callback of the AJAX action is not callable.');
        }

        return call_user_func_array($callback, $arguments);
    }

    private function verifiesByDefault(AjaxAction $action): bool
    {
        return apply_filters(self::NONCE_BY_DEFAULT_FILTER, true, $action->getName()) !== false;
    }

    /**
     * The callback wrapped in a nonce check: a request without a valid nonce gets a 403 and $callback does not run.
     * While debugging, the answer carries a hint for the developer: the nonce that was expected and, for an action
     * that did not choose, why one was asked.
     */
    private function verified(AjaxAction $action, callable $callback): Closure
    {
        $nonceAction = $action->getNonceAction();
        $hint = $this->nonceHint($action);
        $debug = $this->debug;

        return static function (mixed ...$arguments) use ($callback, $nonceAction, $hint, $debug): mixed {
            if (! check_ajax_referer($nonceAction, false, false)) {
                $error = ['code' => self::INVALID_NONCE_CODE, 'message' => 'Invalid or expired security token.'];

                if ($debug ?? (defined('WP_DEBUG') && WP_DEBUG)) {
                    $error['hint'] = $hint;
                }

                wp_send_json_error($error, 403);

                return null;
            }

            return call_user_func_array($callback, $arguments);
        };
    }

    /**
     * What a refused request is told about the nonce it should have carried.
     */
    private function nonceHint(AjaxAction $action): string
    {
        $hint = sprintf(
            'Send a nonce for "%1$s" (Ajax::nonce(\'%1$s\')) in the _ajax_nonce or _wpnonce request field.',
            $action->getNonceAction(),
        );

        if ($action->getNoncePolicy() === NoncePolicy::Undecided) {
            $hint .= ' This action chose no nonce policy, so pollora/ajax verifies one by default; declare ->withoutNonce() (nonce: false) when no nonce makes sense.';
        }

        return $hint;
    }

    /**
     * The callback wrapped in a capability check: a guest gets a 401 and a logged-in user without the capability
     * a 403, and $callback does not run.
     */
    private function authorized(callable $callback, string $capability): Closure
    {
        return static function (mixed ...$arguments) use ($callback, $capability): mixed {
            if (! is_user_logged_in()) {
                wp_send_json_error([
                    'code' => self::UNAUTHENTICATED_CODE,
                    'message' => 'You must be logged in to do this.',
                ], 401);

                return null;
            }

            if (! current_user_can($capability)) {
                wp_send_json_error([
                    'code' => self::FORBIDDEN_CODE,
                    'message' => 'You are not allowed to do this.',
                ], 403);

                return null;
            }

            return call_user_func_array($callback, $arguments);
        };
    }

    /**
     * Tells the developer that an action open to guests requires a capability: a guest is not logged in, so the
     * guest side always answers 401. Skipped during an AJAX request, like the nonce notice.
     */
    private function reportCapabilityForGuests(AjaxAction $action): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        _doing_it_wrong(
            'Ajax::listen("'.$action->getName().'")',
            sprintf(
                'The AJAX action "%1$s" requires the "%2$s" capability but is open to guests. A guest is not logged in, so guests always receive a 401 from it. Restrict it to logged-in users (->forLoggedUsers()), or drop ->requireCapability() when guests must be served.',
                $action->getName(),
                $action->getCapability(),
            ),
            '1.3.0',
        );
    }

    /**
     * Tells the developer that an action open to guests got the default nonce check, which a guest or a cached page
     * does not always satisfy: this audience is where the choice has to be made. Skipped during an AJAX request:
     * the notice would be printed into the JSON the browser is waiting for.
     */
    private function reportUndecidedForGuests(AjaxAction $action): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        _doing_it_wrong(
            'Ajax::listen("'.$action->getName().'")',
            sprintf(
                'The AJAX action "%1$s" is open to guests and chose no nonce policy, so a nonce is verified for them too. A guest\'s nonce is tied to no user, and a cached page can serve an expired one. Declare ->requireNonce() (#[Ajax(\'%1$s\', nonce: true)]) to keep the check, or ->withoutNonce() (nonce: false) for a public or cached endpoint; that removes the check for logged-in users as well.',
                $action->getName(),
            ),
            '2.0.0',
        );
    }

    /**
     * Tells the developer that the filter turned the default check off for an action that never chose. Skipped
     * during an AJAX request, like the notice above.
     */
    private function reportUnchecked(AjaxAction $action): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        _doing_it_wrong(
            'Ajax::listen("'.$action->getName().'")',
            sprintf(
                'The AJAX action "%1$s" runs without a nonce check: it chose no nonce policy and the "%2$s" filter returned false. Declare ->requireNonce() (#[Ajax(\'%1$s\', nonce: true)]), or ->withoutNonce() (nonce: false) when no nonce makes sense.',
                $action->getName(),
                self::NONCE_BY_DEFAULT_FILTER,
            ),
            '2.0.0',
        );
    }
}
