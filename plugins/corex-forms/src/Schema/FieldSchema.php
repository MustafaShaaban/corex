<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Schema;

defined('ABSPATH') || exit;

/**
 * A normalized, immutable form field. Beyond the canonical name, input type, label,
 * ordered rule list, and required flag, it carries the presentation knobs a flexible
 * form needs: choice options (select/radio/checkbox groups), the label display mode,
 * an optional column width, extra CSS classes, and extra HTML attributes. All of the
 * presentation fields default to sensible values so a minimal `['rules' => [...]]`
 * definition still resolves.
 */
final class FieldSchema
{
    /**
     * Exception to the "≤4 constructor parameters" rule (Clean Code Ch. 3): this is an
     * immutable value object whose parameters are independent, named, fully-defaulted
     * presentation attributes — not collaborators. Grouping them into a sub-object would
     * add indirection without removing knowledge, so they stay as named readonly promoted
     * properties (the resolver builds them with named arguments).
     *
     * @param list<array{rule:string,params:array<int,string>}> $rules
     * @param array<string,string>                              $options  value => label (choice fields)
     * @param array<string,string>                              $attrs    extra HTML attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly array $rules,
        public readonly bool $required,
        public readonly array $options = [],
        public readonly string $labelMode = 'visible',
        public readonly string $width = 'full',
        public readonly string $cssClass = '',
        public readonly array $attrs = [],
        public readonly string $placeholder = '',
        public readonly string $helpText = '',
        public readonly mixed $defaultValue = null,
        public readonly string $visibility = 'visible',
        public readonly string $stepKey = '',
        public readonly string $personalDataClass = 'none',
        public readonly array $extensionConfig = [],
    ) {
    }

    /**
     * Whether this type renders a set of choices (and therefore needs `options`).
     */
    public function isChoice(): bool
    {
        return in_array($this->type, ['select', 'multi-select', 'radio', 'checkbox-group'], true);
    }
}
