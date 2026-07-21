<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

/**
 * The server-known facts a challenge is judged against.
 *
 * Every field here is derived from stored configuration and the current request environment,
 * never from the submitted token or any client-supplied value. That is the whole point: the
 * expected action and the allowed hostnames are the server's expectations, so a browser cannot
 * declare its own action and have it believed.
 */
final class ChallengeContext
{
    /**
     * @param string       $expectedAction   The action the token must have been generated for.
     * @param float        $threshold        The confidence floor (0.0–1.0) a score must meet.
     * @param list<string> $allowedHostnames Exact, normalised hostnames the token may originate from.
     * @param string|null  $remoteIp         The submitter IP for the provider call; never persisted.
     */
    public function __construct(
        public readonly string $expectedAction,
        public readonly float $threshold,
        public readonly array $allowedHostnames,
        public readonly ?string $remoteIp = null,
    ) {
    }
}
