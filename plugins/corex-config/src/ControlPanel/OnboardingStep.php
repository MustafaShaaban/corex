<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\ControlPanel;

defined('ABSPATH') || exit;

/**
 * One step on the Corex dashboard onboarding checklist (spec 044) — a setup task that
 * still needs attention, linking to the domain that owns it. Pure value object.
 */
final class OnboardingStep
{
    public function __construct(
        public readonly string $label,
        public readonly string $domain,
        public readonly bool $done,
        public readonly string $link,
    ) {
    }
}
