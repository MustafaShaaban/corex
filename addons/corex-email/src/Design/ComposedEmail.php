<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * One email as it is sent: the designed document, and the same words as plain text.
 */
final readonly class ComposedEmail
{
    public function __construct(
        public string $html,
        public string $text,
    ) {
    }
}
