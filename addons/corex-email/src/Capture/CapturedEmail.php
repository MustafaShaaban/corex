<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Capture;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Locally captured message retained for Development inspection.
 */
final class CapturedEmail
{
    /**
     * @param list<string>         $to
     * @param list<string>         $cc
     * @param list<string>         $bcc
     * @param array<string,string> $headers
     */
    public function __construct(
        public readonly int $id,
        public readonly string $captureId,
        public readonly array $to,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly ?string $replyTo,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $headers,
        public readonly DateTimeImmutable $capturedAt,
        public readonly ?DateTimeImmutable $retentionUntil = null,
        public readonly ?string $attemptId = null,
        public readonly string $plainText = '',
    ) {
        if ($this->id < 1 || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->captureId) !== 1) {
            throw new InvalidArgumentException(__('Captured email identity is invalid.', 'corex'));
        }

        if ($this->retentionUntil !== null && $this->retentionUntil <= $this->capturedAt) {
            throw new InvalidArgumentException(__('Captured email retention must end after capture.', 'corex'));
        }

        if ($this->attemptId !== null
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->attemptId) !== 1
        ) {
            throw new InvalidArgumentException(__('Captured email attempt reference is invalid.', 'corex'));
        }
    }
}
