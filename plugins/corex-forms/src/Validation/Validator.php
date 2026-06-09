<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation;

defined('ABSPATH') || exit;

use Corex\Forms\Schema\FieldSchema;

/**
 * Runs a resolved schema against a payload. Pure: no WordPress. For each declared
 * field it applies the rules in order and records at most one error — the first to
 * fail (bail per field). An absent optional field is valid and not stored; values
 * for fields not in the schema are ignored (FR-002, FR-003).
 */
final class Validator
{
    public function __construct(private readonly RuleRegistry $rules)
    {
    }

    /**
     * @param array<string,FieldSchema> $schema
     * @param array<string,mixed>       $values
     */
    public function validate(array $schema, array $values): ValidationResult
    {
        $errors     = [];
        $normalized = [];

        foreach ($schema as $name => $field) {
            $present = array_key_exists($name, $values);

            if (! $present && ! $field->required) {
                continue;
            }

            $value = $present ? $values[$name] : null;

            if ($present) {
                $normalized[$name] = $value;
            }

            foreach ($field->rules as $spec) {
                $error = $this->rules->get($spec['rule'])->validate($value, $spec['params'], $values);

                if ($error !== null) {
                    $errors[$name] = $error;

                    break;
                }
            }
        }

        return new ValidationResult($errors === [], $errors, $normalized);
    }
}
