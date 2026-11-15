<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * A login settings save that was refused, and nothing written (spec 124, FR-013, User Story 6
 * scenario 4).
 *
 * It carries the field and a reason code, never a sentence: the store is reached before translations
 * load, and the layer that answers a person turns the code into words, as it does for
 * {@see LoginSlug::rejectionReason()}.
 */
final class LoginSettingsRefused extends InvalidArgumentException
{
    /**
     * @param string $field  the stored key the refusal is about
     * @param string $reason one of LoginSlug::REASON_*
     */
    public function __construct(
        public readonly string $field,
        public readonly string $reason,
    ) {
        parent::__construct(sprintf('Login settings refused: %s (%s).', $field, $reason));
    }

    /** Hiding wp-login.php needs an address to sign in at instead. */
    public static function addressNeededToHide(string $reason): self
    {
        return new self('custom_slug', $reason);
    }
}
