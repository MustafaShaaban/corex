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
            );
        }

        return $schema;
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
