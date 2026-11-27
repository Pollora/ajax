<?php

declare(strict_types=1);

namespace Pollora\Ajax\Domain\Model;

/**
 * What an AJAX action says about the nonce that protects it against cross-site request forgery.
 *
 * `Undecided` is what an action is until its author chooses: the registrar verifies a nonce for it, as for
 * `Verify`, unless the `pollora/ajax/nonce_by_default` filter returns false.
 */
enum NoncePolicy: string
{
    case Undecided = 'undecided';
    case Verify = 'verify';
    case Disabled = 'disabled';
}
