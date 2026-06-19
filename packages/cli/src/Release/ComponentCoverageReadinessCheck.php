<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Evaluates the native-first component coverage matrix for readiness output.
 */
final class ComponentCoverageReadinessCheck
{
    public function evaluate(ComponentCoverageMatrix $matrix): ReadinessFinding
    {
        $issues = [
            ...array_map(
                static fn (string $need): string => 'missing:' . $need,
                $matrix->missingNeeds(ComponentCoverageDefaults::requiredNeeds()),
            ),
            ...array_map(
                static fn (string $violation): string => 'native-first:' . $violation,
                $matrix->nativeFirstViolations(),
            ),
            ...array_map(
                static fn (string $item): string => 'visual-redesign:' . $item,
                $matrix->visualRedesignItems(),
            ),
        ];

        if ($issues !== []) {
            return new ReadinessFinding(
                'component-coverage',
                ReadinessFinding::STATUS_FAIL,
                'Component coverage matrix has blocking client-readiness issues.',
                $issues,
                'docs',
                true,
                'Classify missing needs with native Corex/WordPress mechanisms and keep visual redesign deferred.',
            );
        }

        return new ReadinessFinding(
            'component-coverage',
            ReadinessFinding::STATUS_PASS,
            'Component coverage matrix is complete, native-first, and outside visual redesign scope.',
            array_map(
                static fn (ComponentCoverageItem $item): string => sprintf('%s:%s', $item->need, $item->mechanism),
                $matrix->items(),
            ),
            'docs',
            false,
            'None',
        );
    }
}

