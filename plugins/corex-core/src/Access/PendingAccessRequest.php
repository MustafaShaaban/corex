<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * A requester's own open access request, reduced to what the denied surface may render.
 *
 * The reason is deliberately absent. It is the requester's own words and they are entitled to a
 * copy, but the surface that renders this is also embedded as an administrator-facing preview
 * ({@see \Corex\Admin\AdminPage::deniedPreview()}) — so a value object that carries the reason is a
 * value object that will eventually print it somewhere it does not belong. What the requester needs
 * from a confirmation is that it arrived and when, and both are here.
 */
final class PendingAccessRequest
{
    public function __construct(
        public readonly int $id,
        public readonly DateTimeImmutable $requestedAt,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
