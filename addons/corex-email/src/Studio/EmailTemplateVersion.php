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
 *
 * `sentFromSite` marks a revision saved before spec 120, when a template's From was stored and
 * never sent: the upgrade sets it ({@see EmailTemplateRepository::keepSiteSender()}) so that such a
 * revision goes on leaving from Settings → Mail's sender. A revision saved since leaves from its
 * own From (DECISIONS #453, amended 2026-11-11).
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
        public readonly bool $sentFromSite = false,
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

    /** The From address an email of this revision leaves from, or null for Settings → Mail's sender. */
    public function sentFrom(): ?string
    {
        return $this->sentFromSite || $this->fromAddress === '' ? null : $this->fromAddress;
    }

    /** The From name an email of this revision leaves with, or null for Settings → Mail's. */
    public function sentFromName(): ?string
    {
        return $this->sentFromSite || trim($this->fromName) === '' ? null : $this->fromName;
    }
}
