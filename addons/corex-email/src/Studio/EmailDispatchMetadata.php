<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Immutable provenance attached to attempts created by one dispatch.
 */
final class EmailDispatchMetadata
{
    private const SOURCES = ['application', 'route', 'test', 'resend'];

    public function __construct(
        public readonly ?EmailTemplateReference $template,
        public readonly ?string $parentAttemptId,
        public readonly string $source,
    ) {
        if (! in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException(__('Email dispatch source is invalid.', 'corex'));
        }
    }
}
