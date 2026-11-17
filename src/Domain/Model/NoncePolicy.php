<?php

declare(strict_types=1);

namespace Pollora\Ajax\Domain\Model;

/**
 * What an AJAX action says about the nonce that protects it against cross-site request forgery.
 *
 * `Undecided` is what an action is until its author chooses: it keeps running without a check for now, and the
 * registrar tells the developer to choose (the next major version verifies a nonce by default).
 */
enum NoncePolicy: string
{
    case Undecided = 'undecided';
    case Verify = 'verify';
    case Disabled = 'disabled';
}
