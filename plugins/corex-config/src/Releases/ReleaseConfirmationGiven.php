<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * What an administrator gave to confirm an installation (spec 107, plan D8; FR-015, FR-050).
 */
final readonly class ReleaseConfirmationGiven
{
    /**
     * @param string      $phrase        What was typed for the version being installed.
     * @param string|null $client        The client's name, typed, on a site that could not say whose it is.
     * @param bool        $olderAccepted That an older release than the one running is meant.
     */
    public function __construct(
        public string $phrase,
        public ?string $client,
        public bool $olderAccepted,
    ) {
    }
}
