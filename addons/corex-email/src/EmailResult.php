<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email;

defined('ABSPATH') || exit;

/**
 * The outcome of a send attempt: a final status (`sent` | `failed` | `rejected`), a
 * short message, and the audit log id (when one was written).
 */
final class EmailResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?int $logId = null,
    ) {
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }
}
