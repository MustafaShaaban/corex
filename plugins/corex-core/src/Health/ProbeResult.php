<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * The outcome of one health probe: a status, a short label, a plain-language description, and an
 * optional list of concrete next actions. Immutable — a probe builds one and hands it back.
 */
final class ProbeResult
{
    /**
     * @param list<string> $actions
     */
    public function __construct(
        public readonly HealthStatus $status,
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly array $actions = [],
    ) {
    }
}
