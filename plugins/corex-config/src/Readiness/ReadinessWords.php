<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Readiness;

defined('ABSPATH') || exit;

use Corex\Config\Operations\ReadinessSnapshot;

/**
 * The words for the launch checklist's states, in one place (spec 118, DECISIONS #431).
 *
 * One set of states, two voices. The Overview reads the checklist as a to-do list: Done, Pending,
 * Blocked (US-OVR-02). The screens where the owner decides to go live (Operations & Security, the
 * Setup Wizard's launch step and the Dashboard widget) read it as a gate: Ready, Pending, Blocks
 * going live (US-SEC-09). A check has the same state in both; only the word for it changes.
 */
final class ReadinessWords
{
    public const TODO = 'todo';
    public const GATE = 'gate';

    /** The chip each state is drawn with, the same in both voices. */
    public static function chip(string $state): string
    {
        return match ($state) {
            ReadinessSnapshot::PASS           => 'done',
            ReadinessSnapshot::BLOCKING       => 'blocked',
            ReadinessSnapshot::UNAVAILABLE    => 'unchecked',
            ReadinessSnapshot::NOT_APPLICABLE => 'inapplicable',
            default                           => 'pending',
        };
    }

    public static function state(string $state, string $voice): string
    {
        $gate = $voice === self::GATE;

        return match ($state) {
            ReadinessSnapshot::PASS           => $gate ? __('Ready', 'corex') : __('Done', 'corex'),
            ReadinessSnapshot::BLOCKING       => $gate ? __('Blocks going live', 'corex') : __('Blocked', 'corex'),
            ReadinessSnapshot::UNAVAILABLE    => __('Could not check', 'corex'),
            ReadinessSnapshot::NOT_APPLICABLE => __('Not counted', 'corex'),
            default                           => __('Pending', 'corex'),
        };
    }

    /** "4 of 9" on the Overview, "4 of 9 ready" where going live is decided. */
    public static function count(int $done, int $total, string $voice): string
    {
        return $voice === self::GATE
            ? sprintf(
                /* translators: 1: checks that are ready, 2: checks counted. */
                _n('%1$s of %2$s ready', '%1$s of %2$s ready', $total, 'corex'),
                number_format_i18n($done),
                number_format_i18n($total),
            )
            : sprintf(
                /* translators: 1: checks that are done, 2: checks counted. */
                _n('%1$s of %2$s', '%1$s of %2$s', $total, 'corex'),
                number_format_i18n($done),
                number_format_i18n($total),
            );
    }

    /** What still stands between the site and going live, or that nothing does. */
    public static function blocking(int $blocking): string
    {
        return $blocking === 0
            ? __('Nothing blocks going live', 'corex')
            : sprintf(
                /* translators: %s: number of checks that block going live. */
                _n('%s check still blocks going live', '%s checks still block going live', $blocking, 'corex'),
                number_format_i18n($blocking),
            );
    }

    public static function group(string $group): string
    {
        return $group === ReadinessSnapshot::GROUP_SITE
            ? __('Site and content', 'corex')
            : __('Security and hosting', 'corex');
    }
}
