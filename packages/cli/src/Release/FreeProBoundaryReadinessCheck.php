<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Evaluates Free/Core and Pro candidate boundaries for readiness output.
 */
final class FreeProBoundaryReadinessCheck
{
    public function evaluate(FreeProBoundaryMatrix $matrix): ReadinessFinding
    {
        $issues = [
            ...array_map(
                static fn (string $capability): string => 'missing:' . $capability,
                $matrix->missingCapabilities(FreeProBoundaryDefaults::requiredFreeCoreCapabilities()),
            ),
            ...array_map(
                static fn (string $capability): string => 'security-critical-pro:' . $capability,
                $matrix->securityCriticalProCandidates(),
            ),
        ];

        if ($issues !== []) {
            return new ReadinessFinding(
                'free-pro',
                ReadinessFinding::STATUS_FAIL,
                'Free/Core boundaries have blocking trust-baseline issues.',
                $issues,
                'docs',
                true,
                'Keep required adoption and security basics in Free/Core before client-site work proceeds.',
            );
        }

        return new ReadinessFinding(
            'free-pro',
            ReadinessFinding::STATUS_PASS,
            'Free/Core basics are protected and advanced commercial scope is classified separately.',
            array_map(
                static fn (FreeProBoundaryItem $item): string => sprintf('%s:%s', $item->classification, $item->capability),
                $matrix->items(),
            ),
            'docs',
            false,
            'None',
        );
    }
}

