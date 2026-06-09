<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched once a submission passes the security gate and validation. Immutable:
 * carries the form slug and the validated/normalized values for the listeners.
 */
final class FormSubmittedEvent implements Event
{
    /**
     * @param array<string,mixed> $values
     */
    public function __construct(
        public readonly string $formSlug,
        public readonly array $values,
    ) {
    }
}
