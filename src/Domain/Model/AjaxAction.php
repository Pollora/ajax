<?php

declare(strict_types=1);

namespace Pollora\Ajax\Domain\Model;

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Exception\InvalidAjaxActionException;

/**
 * Domain entity representing an AJAX action definition.
 *
 * Encapsulates the action name, callback, and user-type targeting.
 * By default, actions are restricted to logged-in users (security-by-default).
 * The action is automatically registered via the application service when
 * this object is destroyed (end of scope), enabling a fluent chaining API.
 *
 * @see RegisterAjaxActionService
 */
class AjaxAction
{
    /**
     * User type constant for both logged-in and guest users.
     */
    public const BOTH_USERS = 'both';

    /**
     * User type constant for logged-in users only.
     */
    public const LOGGED_USERS = 'logged';

    /**
     * User type constant for guest users only.
     */
    public const GUEST_USERS = 'guest';

    /**
     * The target audience for this action.
     *
     * Defaults to LOGGED_USERS so that endpoints are not exposed
     * to unauthenticated visitors unless explicitly opted in.
     *
     * @var string One of self::BOTH_USERS, self::LOGGED_USERS, self::GUEST_USERS
     */
    private string $userType = self::LOGGED_USERS;

    private NoncePolicy $noncePolicy = NoncePolicy::Undecided;

    private ?string $nonceAction = null;

    private ?string $capability = null;

    /**
     * Create a new AJAX action definition.
     *
     * @param  string  $name  The WordPress AJAX action name (used in `wp_ajax_{name}` hooks).
     * @param  callable|string  $callback  The callback to execute when the action is triggered.
     * @param  RegisterAjaxActionService|null  $registerService  Optional application service for deferred registration via __destruct.
     *
     * @throws InvalidAjaxActionException If the action name is empty or the callback is falsy.
     */
    public function __construct(
        private readonly string $name,
        private readonly mixed $callback,
        private readonly ?RegisterAjaxActionService $registerService = null
    ) {
        if ($this->name === '' || $this->name === '0' || empty($this->callback)) {
            throw new InvalidAjaxActionException('Action and callback must be provided.');
        }
    }

    /**
     * Get the WordPress AJAX action name.
     *
     * @return string The action name used in `wp_ajax_{name}` hooks.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the callback for this action.
     *
     * @return callable|string
     */
    public function getCallback(): mixed
    {
        return $this->callback;
    }

    /**
     * Get the user type targeting for this action.
     *
     * @return string One of self::BOTH_USERS, self::LOGGED_USERS, or self::GUEST_USERS.
     */
    public function getUserType(): string
    {
        return $this->userType;
    }

    /**
     * Set the user type targeting for this action.
     *
     * @param  string  $userType  One of self::BOTH_USERS, self::LOGGED_USERS, or self::GUEST_USERS.
     * @return $this
     */
    public function setUserType(string $userType): static
    {
        $this->userType = $userType;

        return $this;
    }

    /**
     * Make the action available to all users (logged-in and guests).
     * Must be called explicitly — the default is logged-in users only.
     *
     * @return $this
     */
    public function forAllUsers(): static
    {
        $this->setUserType(self::BOTH_USERS);

        return $this;
    }

    /**
     * Restrict the action to logged-in users only (this is the default).
     *
     * @return $this
     */
    public function forLoggedUsers(): static
    {
        $this->setUserType(self::LOGGED_USERS);

        return $this;
    }

    /**
     * Restrict the action to guest users only.
     *
     * @return $this
     */
    public function forGuestUsers(): static
    {
        $this->setUserType(self::GUEST_USERS);

        return $this;
    }

    /**
     * Verify a nonce before the callback runs: a request without a valid one gets a 403 and the callback does not run.
     * This is also what happens to an action that chooses nothing; say it to name another nonce action, or to keep
     * the check whatever the `pollora/ajax/nonce_by_default` filter answers.
     *
     * The nonce action is the name of the AJAX action unless another one is given; the nonce travels in the
     * `_ajax_nonce` (or `_wpnonce`) request field, as `check_ajax_referer()` reads it. Create it with
     * `Ajax::nonce($action)`.
     *
     * @param  string|null  $nonceAction  The nonce action to verify, the AJAX action name when null.
     * @return $this
     */
    public function requireNonce(?string $nonceAction = null): static
    {
        $this->noncePolicy = NoncePolicy::Verify;
        $this->nonceAction = $nonceAction;

        return $this;
    }

    /**
     * Say, on purpose, that no nonce protects this action (a public beacon, a webhook that signs its own requests).
     * Must be called explicitly: an action that never chose verifies a nonce.
     *
     * @return $this
     */
    public function withoutNonce(): static
    {
        $this->noncePolicy = NoncePolicy::Disabled;
        $this->nonceAction = null;

        return $this;
    }

    /**
     * What the action says about its nonce.
     */
    public function getNoncePolicy(): NoncePolicy
    {
        return $this->noncePolicy;
    }

    /**
     * The nonce action a verified request must carry a nonce for.
     */
    public function getNonceAction(): string
    {
        return $this->nonceAction ?? $this->name;
    }

    /**
     * Require a capability of the user before the callback runs: a request from a user who does not have it gets a
     * 403 and the callback does not run. A nonce proves where the request comes from, not that the user may do what
     * it asks; this is that second check, made after the nonce one.
     *
     * The capability is a WordPress one (`edit_posts`, `manage_options`, a custom one), read with `current_user_can()`.
     * A capability only makes sense for a logged-in user: on the guest side of an action open to guests the request
     * is answered with a 401 and the registrar tells the developer.
     *
     * @param  string  $capability  The capability the current user must have.
     * @return $this
     *
     * @throws InvalidAjaxActionException If the capability is empty.
     */
    public function requireCapability(string $capability): static
    {
        if (trim($capability) === '') {
            throw new InvalidAjaxActionException('The capability must not be empty.');
        }

        $this->capability = $capability;

        return $this;
    }

    /**
     * The capability a user must have, null when the action requires none.
     */
    public function getCapability(): ?string
    {
        return $this->capability;
    }

    /**
     * Check if the action targets logged-in users (either exclusively or alongside guests).
     *
     * @return bool True if user type is BOTH_USERS or LOGGED_USERS.
     */
    public function isBothOrLoggedUsers(): bool
    {
        return $this->getUserType() === self::BOTH_USERS
            || $this->getUserType() === self::LOGGED_USERS;
    }

    /**
     * Check if the action targets guest users (either exclusively or alongside logged-in users).
     *
     * @return bool True if user type is BOTH_USERS or GUEST_USERS.
     */
    public function isBothOrGuestUsers(): bool
    {
        return $this->getUserType() === self::BOTH_USERS
            || $this->getUserType() === self::GUEST_USERS;
    }

    /**
     * Destructor — triggers deferred registration.
     *
     * When the AjaxAction goes out of scope (e.g. at end of a fluent chain),
     * the application service registers the WordPress hooks automatically.
     * This enables the pattern: `Ajax::listen('x', $cb)->forGuestUsers();`
     * where registration happens after all chained calls complete.
     */
    public function __destruct()
    {
        if ($this->registerService instanceof RegisterAjaxActionService) {
            $this->registerService->execute($this);
        }
    }
}
