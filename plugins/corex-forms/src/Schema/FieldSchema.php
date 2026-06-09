<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Schema;

defined('ABSPATH') || exit;

/**
 * A normalized, immutable form field: its canonical name, input type, label, the
 * ordered rule list, and whether it is required (derived from the rules).
 */
final class FieldSchema
{
    /**
     * @param list<array{rule:string,params:array<int,string>}> $rules
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly array $rules,
        public readonly bool $required,
    ) {
    }
}
