<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * The outcome of validating an uploaded file: valid, or a specific machine-readable
 * reason (e.g. `too_large`, `type_not_allowed`).
 */
final class UploadResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $reason = '',
    ) {
    }
}
