<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

/**
 * Runtime route with concrete validated recipients.
 */
final class ResolvedEmailRoute
{
    /** @param list<string> $recipients */
    public function __construct(
        public readonly ?int $routeId,
        public readonly int $templateId,
        public readonly array $recipients,
        public readonly ?string $replyTo,
    ) {
    }
}
