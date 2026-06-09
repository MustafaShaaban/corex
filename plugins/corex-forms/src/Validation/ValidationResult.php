<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation;

defined('ABSPATH') || exit;

/**
 * The outcome of validating a payload: at most one error per field (bail), plus
 * the normalized values for the fields the schema declared. `valid === (errors === [])`.
 */
final class ValidationResult
{
    /**
     * @param array<string,string> $errors field => single message key
     * @param array<string,mixed>  $values normalized values for declared fields
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
        public readonly array $values,
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}
