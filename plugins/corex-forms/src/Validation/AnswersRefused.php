<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation;

defined('ABSPATH') || exit;

/**
 * Thrown by a form's `prepare()` to refuse answers that passed every rule (spec 129, FR-022).
 *
 * A rule answers about the request as it reads; some answers can only be refused once the form
 * acts on them. The assistant's callback claims the time a visitor picked there, and a visitor
 * who sent the same time a moment earlier wins it. The engine answers 422 in the shape of a
 * validation failure, with these errors, so a client that already shows field errors needs
 * nothing new.
 */
final class AnswersRefused extends \RuntimeException
{
    /**
     * @param array<string, string> $errors field name => message key, as a rule answers
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Answers refused: ' . implode(', ', array_keys($errors)));
    }
}
