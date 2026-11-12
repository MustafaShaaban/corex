<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * The values one sign-in attempt is counted by: the keyed pair it is recorded under, and the plain
 * pair the release before this one recorded it under (spec 124, FR-019).
 *
 * Attempts stored before the update keep counting until they age out, so a lockout that was active
 * when the site updated still holds until its end. No attempt is recorded under the plain pair any
 * more; it is only looked for. It can be dropped once every site has run this release for longer
 * than its longest counting window and lockout.
 */
final readonly class LoginAttemptKeys
{
    public function __construct(
        public string $identityHash,
        public string $networkHash,
        public string $legacyIdentityHash,
        public string $legacyNetworkHash,
    ) {
    }

    /**
     * Each identity and network pair a stored row may carry, the keyed one first.
     *
     * @return list<array{0:string,1:string}>
     */
    public function pairs(): array
    {
        return [
            [$this->identityHash, $this->networkHash],
            [$this->legacyIdentityHash, $this->legacyNetworkHash],
        ];
    }

    /** Whether a stored row's values are one of this attempt's pairs. */
    public function matches(string $identityHash, string $networkHash): bool
    {
        return in_array([$identityHash, $networkHash], $this->pairs(), true);
    }
}
