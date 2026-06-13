<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * A lightweight description of an applicable kit for the activation prompt and the dashboard card (spec 042):
 * its name/label, whether it has been applied, how many pages it declares, and the modules it needs. Immutable;
 * carries no behavior. (The transient "pending apply" prompt state is owned by the Add-ons UX, not here.)
 */
final class KitSummary
{
    /**
     * @param list<string> $requiredModules
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly bool $applied,
        public readonly int $pageCount,
        public readonly array $requiredModules,
    ) {
    }
}
