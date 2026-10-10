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
 * fail (bail per field). An absent optional field is not stored, and only a rule that
 * implements `RuleForAbsentValue` is asked about it; values for fields not in the schema
 * are ignored (FR-002, FR-003).
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
            // An optional field left out of the request: only a rule that asked to be told runs.
            $leftOut = ! $present && ! $field->required;
            $value   = $present ? $values[$name] : null;

            if ($present) {
                $normalized[$name] = $value;
            }

            foreach ($field->rules as $spec) {
                $rule = $this->rules->get($spec['rule']);

                if ($leftOut && ! $rule instanceof RuleForAbsentValue) {
                    continue;
                }

                $error = $rule->validate($value, $spec['params'], $values);

                if ($error !== null) {
                    $errors[$name] = $error;

                    break;
                }
            }

            if (! isset($errors[$name]) && ! $this->isOffered($field, $value)) {
                $errors[$name] = 'choice';
            }
        }

        return new ValidationResult($errors === [], $errors, $normalized);
    }

    /**
     * Whether an answer to a choice field is among the options the field declares.
     *
     * Not a rule a form writes: a field that declares its options has said what it accepts, and a
     * form that had to repeat them in a rule would be right until somebody edited one of the two.
     *
     * An empty answer is left to `required`, as every rule leaves it. A field that declares no
     * options has nothing to be compared with, and a theme that fills a select from its own script
     * declares none.
     */
    private function isOffered(FieldSchema $field, mixed $value): bool
    {
        if (! $field->isChoice() || $field->options === [] || $value === null || $value === '' || $value === []) {
            return true;
        }

        if (is_array($value) && ! $field->takesSeveralAnswers()) {
            return false;
        }

        // PHP turns the array key '2025' into an integer, and the answer arrives as a string.
        $offered = array_map('strval', array_keys($field->options));

        foreach ((array) $value as $answer) {
            if (! is_scalar($answer) || ! in_array((string) $answer, $offered, true)) {
                return false;
            }
        }

        return true;
    }
}
