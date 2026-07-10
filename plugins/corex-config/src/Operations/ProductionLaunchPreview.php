<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Operations\Confirmation;

final readonly class ProductionLaunchPreview
{
    public function __construct(
        public ReadinessSnapshot $snapshot,
        public Confirmation $confirmation,
    ) {
    }

    public function blocked(): bool
    {
        return $this->snapshot->hasBlockingChecks();
    }

    /** @return list<string> */
    public function blockingKeys(): array
    {
        return $this->snapshot->blockingKeys();
    }
}
