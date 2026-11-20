<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * What the owner tells visitors while a mode closes public submissions (spec 124, FR-007): their
 * own message, or '' for CoreX's, and the time they expect to be back, or none.
 *
 * Nothing is cut: the length is checked where the owner writes it (FR-007, slice 9).
 */
final readonly class VisitorMessage
{
    public function __construct(
        public string $message = '',
        public ?DateTimeImmutable $backBy = null,
    ) {
    }
}
