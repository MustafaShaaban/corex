<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Typed destination selected by a routing rule or fallback.
 */
final readonly class RoutingTarget
{
    private const TYPES = [
        'email',
        'user',
        'role',
        'team',
        'department',
        'page_owner',
        'post_author',
        'flow_owner',
        'field_value',
        'extension',
    ];

    /** @param array<string,mixed> $config */
    public function __construct(
        public string $type,
        public array $config = [],
    ) {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported routing target: "%s".', $type));
        }
    }

    /** @return list<string> */
    public static function types(): array
    {
        return self::TYPES;
    }
}
