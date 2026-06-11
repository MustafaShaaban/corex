<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Schema;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\RuleRegistry;
use InvalidArgumentException;

/**
 * Normalizes a code-defined form definition (fields → a canonical rule set). Field
 * names are normalized to a canonical key (the same key used for the input name and
 * the `corex_field_*` meta); two names that normalize to the same key, and any
 * unknown rule, are rejected — fail closed, surfaced to the developer (FR-005).
 */
final class SchemaResolver
{
    public function __construct(private readonly RuleRegistry $rules)
    {
    }

    /**
     * @param array<string,array{type?:string,rules?:list<string>,label?:string}> $fields
     *
     * @return array<string,FieldSchema>
     *
     * @throws InvalidArgumentException on a duplicate field name or an unknown rule
     */
    public function resolve(array $fields): array
    {
        $schema = [];

        foreach ($fields as $key => $definition) {
            $name = $this->canonicalName((string) $key);

            if ($name === '') {
                throw new InvalidArgumentException(sprintf('Invalid form field name: "%s".', (string) $key));
            }

            if (isset($schema[$name])) {
                throw new InvalidArgumentException(sprintf('Duplicate form field name: "%s".', $name));
            }

            $rules = $this->parseRules($definition['rules'] ?? []);

            $schema[$name] = new FieldSchema(
                $name,
                (string) ($definition['type'] ?? 'text'),
                (string) ($definition['label'] ?? (string) $key),
                $rules,
                $this->isRequired($rules),
                $this->parseOptions($definition['options'] ?? []),
                $this->labelMode((string) ($definition['label_mode'] ?? 'visible')),
                $this->width((string) ($definition['width'] ?? 'full')),
                (string) ($definition['class'] ?? ''),
                $this->parseAttrs($definition['attrs'] ?? []),
            );
        }

        return $schema;
    }

    /**
     * Choice options as a value => label string map (used by select/radio/checkbox).
     * Accepts a map or a plain list (list items become their own value + label).
     *
     * @param array<int|string,mixed> $options
     *
     * @return array<string,string>
     */
    private function parseOptions(array $options): array
    {
        $parsed = [];

        foreach ($options as $value => $label) {
            if (is_int($value)) {
                $parsed[(string) $label] = (string) $label;

                continue;
            }

            $parsed[(string) $value] = (string) $label;
        }

        return $parsed;
    }

    private function labelMode(string $mode): string
    {
        return in_array($mode, ['visible', 'hidden', 'inline'], true) ? $mode : 'visible';
    }

    private function width(string $width): string
    {
        return in_array($width, ['full', 'half', 'third', 'two-thirds', 'quarter'], true) ? $width : 'full';
    }

    /**
     * Extra HTML attributes, restricted to a safe set so a definition can never override
     * the renderer-controlled structural/security attributes (name, id, type, class,
     * required, aria-*). Keys and values are coerced to strings.
     *
     * @param array<int|string,mixed> $attrs
     *
     * @return array<string,string>
     */
    private function parseAttrs(array $attrs): array
    {
        $reserved = ['name', 'id', 'type', 'class', 'required', 'value', 'aria-describedby'];
        $parsed = [];

        foreach ($attrs as $key => $value) {
            $attr = strtolower((string) $key);

            if (! is_string($key) || in_array($attr, $reserved, true) || str_starts_with($attr, 'on')) {
                continue;
            }

            $parsed[$attr] = (string) $value;
        }

        return $parsed;
    }

    /**
     * @param list<string> $specs
     *
     * @return list<array{rule:string,params:array<int,string>}>
     */
    private function parseRules(array $specs): array
    {
        $parsed = [];

        foreach ($specs as $spec) {
            $rule = $this->rules->parse((string) $spec);

            if (! $this->rules->has($rule['name'])) {
                throw new InvalidArgumentException(sprintf('Unknown validation rule: "%s".', $rule['name']));
            }

            $parsed[] = ['rule' => $rule['name'], 'params' => $rule['params']];
        }

        return $parsed;
    }

    /**
     * @param list<array{rule:string,params:array<int,string>}> $rules
     */
    private function isRequired(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule['rule'] === 'required') {
                return true;
            }
        }

        return false;
    }

    private function canonicalName(string $key): string
    {
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', strtolower($key)) ?? '';

        return trim($normalized, '_');
    }
}
