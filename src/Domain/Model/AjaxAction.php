<?php

declare(strict_types=1);

namespace Pollora\Ajax\Domain\Model;

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Exception\InvalidAjaxActionException;

/**
 * Domain entity representing an AJAX action definition.
 * Contains the action name, callback, user type, and registration logic.
 */
class AjaxAction
{
    /**
     * User type constant for both logged and guest users.
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

    private string $userType = self::LOGGED_USERS;

    /**
     * @param  string  $name  The action name.
     * @param  callable|string  $callback  The callback to execute.
     * @param  RegisterAjaxActionService|null  $registerService  The application service for registration.
     *
     * @throws InvalidAjaxActionException
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
     * Get the action name.
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
     * Get the user type for this action.
     */
    public function getUserType(): string
    {
        return $this->userType;
    }

    /**
     * Set the user type for this action.
     *
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
     * Check if the action is for both or logged-in users.
     */
    public function isBothOrLoggedUsers(): bool
    {
        return $this->getUserType() === self::BOTH_USERS
            || $this->getUserType() === self::LOGGED_USERS;
    }

    /**
     * Check if the action is for both or guest users.
     */
    public function isBothOrGuestUsers(): bool
    {
        return $this->getUserType() === self::BOTH_USERS
            || $this->getUserType() === self::GUEST_USERS;
    }

    /**
     * Destructor. Registers the action using the application service if available.
     */
    public function __destruct()
    {
        if ($this->registerService instanceof RegisterAjaxActionService) {
            $this->registerService->execute($this);
        }
    }
}
