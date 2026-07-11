<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable published or historical flow configuration snapshot.
 */
final readonly class FlowVersion
{
    public string $checksum;

    /**
     * This constructor is an immutable persistence record, not collaborator injection.
     */
    public function __construct(
        public int $id,
        public int $flowId,
        public int $versionNumber,
        public FlowConfiguration $configuration,
        public int $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
        if ($flowId < 1 || $versionNumber < 1) {
            throw new InvalidArgumentException('Flow version identity is invalid.');
        }

        $this->checksum = $configuration->checksum();
    }
}
