<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\ControlPanel;

defined('ABSPATH') || exit;

/**
 * Turns the control panel's domain statuses into the dashboard onboarding checklist
 * (spec 044, US1) — the still-incomplete setup steps, each linking to its domain. Pure;
 * `allSet()` is true only when every domain is configured.
 */
final class OnboardingChecklist
{
    /**
     * @param list<DomainStatus> $domains
     *
     * @return list<OnboardingStep>
     */
    public function steps(array $domains): array
    {
        $steps = [];

        foreach ($domains as $domain) {
            if ($domain->isConfigured()) {
                continue;
            }

            $steps[] = new OnboardingStep(
                $this->stepLabel($domain),
                $domain->domain,
                false,
                $domain->setupLink,
            );
        }

        return $steps;
    }

    /**
     * @param list<DomainStatus> $domains
     */
    public function allSet(array $domains): bool
    {
        foreach ($domains as $domain) {
            if (! $domain->isConfigured()) {
                return false;
            }
        }

        return true;
    }

    private function stepLabel(DomainStatus $domain): string
    {
        if ($domain->missing !== []) {
            /* translators: 1: domain label, 2: comma-separated missing items */
            return sprintf(__('%1$s — add %2$s', 'corex'), $domain->label, implode(', ', $domain->missing));
        }

        /* translators: %s: domain label */
        return sprintf(__('Finish setting up %s', 'corex'), $domain->label);
    }
}
