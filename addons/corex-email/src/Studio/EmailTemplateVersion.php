<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable template content revision.
 */
final class EmailTemplateVersion
{
    public const PLAIN_AUTO   = 'auto';
    public const PLAIN_MANUAL = 'manual';

    /** @param list<string> $variableKeys */
    public function __construct(
        public readonly int $id,
        public readonly int $templateId,
        public readonly int $versionNumber,
        public readonly string $subject,
        public readonly string $fromName,
        public readonly string $fromAddress,
        public readonly string $htmlBody,
        public readonly string $plainText,
        public readonly string $plainTextMode,
        public readonly int $layoutId,
        public readonly int $layoutVersion,
        public readonly array $variableKeys,
        public readonly int $createdBy,
        public readonly DateTimeImmutable $createdAt,
        public readonly string $checksum,
    ) {
        if ($this->id < 1 || $this->templateId < 1 || $this->versionNumber < 1 || $this->layoutId < 1 || $this->layoutVersion < 1 || $this->createdBy < 1) {
            throw new InvalidArgumentException(__('Email template version identifiers are invalid.', 'corex'));
        }

        if (! in_array($this->plainTextMode, [self::PLAIN_AUTO, self::PLAIN_MANUAL], true)) {
            throw new InvalidArgumentException(__('Email template plain-text mode is invalid.', 'corex'));
        }

        if (preg_match('/^[0-9a-f]{64}$/', $this->checksum) !== 1) {
            throw new InvalidArgumentException(__('Email template checksum is invalid.', 'corex'));
        }

        foreach ($this->variableKeys as $key) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_.]*$/', $key) !== 1) {
                throw new InvalidArgumentException(__('Email template variable key is invalid.', 'corex'));
            }
        }
    }
}
