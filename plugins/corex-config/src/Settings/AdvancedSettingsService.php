<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * The Advanced settings section (spec 068 T203): a truthful system-diagnostics read-out and the
 * danger-zone actions, each gated behind an exact typed confirmation so a destructive reset can
 * never fire by accident (fail-closed). It composes from already-gathered real facts and never
 * performs the reset itself — the caller runs the concrete reset only after {@see confirms()}
 * returns true. WordPress-free, so it is unit-testable.
 */
final class AdvancedSettingsService
{
    /**
     * A real system-diagnostics report from gathered facts — never fabricated values.
     *
     * @param array{phpVersion:string,wpVersion:string,environment:string,memoryLimit:string,addonsActive:int,addonsTotal:int,multisite:bool} $facts
     *
     * @return list<array{label:string,value:string}>
     */
    public function diagnostics(array $facts): array
    {
        return [
            ['label' => __('PHP version', 'corex'), 'value' => (string) $facts['phpVersion']],
            ['label' => __('WordPress version', 'corex'), 'value' => (string) $facts['wpVersion']],
            ['label' => __('Environment', 'corex'), 'value' => (string) $facts['environment']],
            ['label' => __('PHP memory limit', 'corex'), 'value' => (string) $facts['memoryLimit']],
            [
                'label' => __('Active add-ons', 'corex'),
                /* translators: 1: active add-on count, 2: total installed */
                'value' => sprintf(__('%1$d of %2$d', 'corex'), max(0, (int) $facts['addonsActive']), max(0, (int) $facts['addonsTotal'])),
            ],
            ['label' => __('Multisite', 'corex'), 'value' => $facts['multisite'] ? __('Yes', 'corex') : __('No', 'corex')],
        ];
    }

    /**
     * The danger-zone actions. Each names exactly what it reverses and the phrase the operator must
     * type to run it — a reset never touches unrelated owner content (FR-141).
     *
     * @return list<array{key:string,label:string,description:string,confirmPhrase:string}>
     */
    public function dangerActions(): array
    {
        return [
            [
                'key'           => 'reset-settings',
                'label'         => __('Reset CoreX settings', 'corex'),
                'description'   => __('Clears CoreX-owned settings to their defaults. Your content, users, and pages are untouched.', 'corex'),
                'confirmPhrase' => 'RESET SETTINGS',
            ],
            [
                'key'           => 'reset-kit',
                'label'         => __('Reset the applied kit', 'corex'),
                'description'   => __('Removes only the pages CoreX created for the kit; adopted or user-authored pages are preserved.', 'corex'),
                'confirmPhrase' => 'RESET KIT',
            ],
        ];
    }

    /**
     * Fail-closed confirmation: the destructive action proceeds only when the typed phrase exactly
     * matches the expected phrase (case-sensitive, whitespace-trimmed). An empty expected phrase or
     * any mismatch denies the action.
     */
    public function confirms(string $typed, string $expected): bool
    {
        $expected = trim($expected);

        return $expected !== '' && trim($typed) === $expected;
    }
}
