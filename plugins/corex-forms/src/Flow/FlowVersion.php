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

    /**
     * This version's questions under another version's spam protection, for a visitor whose page
     * carried this version after a newer one went live (spec 122, DECISIONS #470).
     *
     * @param array<string,mixed> $protection
     */
    public function withProtection(array $protection): self
    {
        $configuration = $this->configuration;

        return new self(
            $this->id,
            $this->flowId,
            $this->versionNumber,
            new FlowConfiguration(
                schema: $configuration->schema,
                validation: $configuration->validation,
                routing: $configuration->routing,
                emailRoutes: $configuration->emailRoutes,
                success: $configuration->success,
                placementSnapshot: $configuration->placementSnapshot,
                protection: $protection,
                actions: $configuration->actions,
            ),
            $this->createdBy,
            $this->createdAt,
        );
    }
}
