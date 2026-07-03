<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * Immutable safety result produced before a role or user ability change is applied.
 */
final class AccessChangePreview
{
    /**
     * @param list<array{code:string,ability:string}> $blockers
     * @param array<string,string>                    $changes
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly array $blockers,
        public readonly array $changes,
    ) {
    }
}
