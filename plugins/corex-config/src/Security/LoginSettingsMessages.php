<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginSettingsProblem;
use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Config\Security\LoginProtection\LoginSlugAvailability;

/**
 * The sentences a refused login settings save answers with, one per field (spec 124, FR-015, User
 * Story 7 scenario 3): a number's range, the trusted proxy line that is wrong, or why a login address
 * cannot be used.
 *
 * The problems carry codes because they are found before translations load; this is the layer that
 * talks to a person, so it is the layer that translates.
 */
final class LoginSettingsMessages
{
    /**
     * @param array<string,LoginSettingsProblem> $problems
     *
     * @return array<string,string> field => sentence
     */
    public function errors(array $problems): array
    {
        return array_map(fn (LoginSettingsProblem $problem): string => $this->sentence($problem), $problems);
    }

    /**
     * The range, the reason and the refused proxy lines as data, for a screen that marks the field.
     *
     * @param array<string,LoginSettingsProblem> $problems
     *
     * @return array{fields: array<string,array<string,mixed>>}
     */
    public function details(array $problems): array
    {
        return ['fields' => array_map(function (LoginSettingsProblem $problem): array {
            $details = $problem->toDetails();

            if (isset($details['lines'])) {
                $details['lines'] = array_map(
                    static fn (array $line): array => ['line' => $line['line'], 'value' => sanitize_text_field($line['value'])],
                    $details['lines'],
                );
            }

            return $details;
        }, $problems)];
    }

    public function sentence(LoginSettingsProblem $problem): string
    {
        return match ($problem->reason) {
            LoginSettingsProblem::REASON_OUT_OF_RANGE => $this->range($problem),
            LoginSettingsProblem::REASON_NOT_ON_OR_OFF => __('This setting must be on or off.', 'corex'),
            LoginSettingsProblem::REASON_NOT_A_PROXY => sprintf(
                /* translators: 1: line number in the trusted proxies box, 2: the text on that line. */
                __('Line %1$s, "%2$s", is not an IPv4 or IPv6 address or range. Write one per line, for example 203.0.113.7 or 203.0.113.0/24.', 'corex'),
                number_format_i18n((int) $problem->line),
                sanitize_text_field($problem->value),
            ),
            LoginSettingsProblem::REASON_TRUSTS_EVERY_ADDRESS => sprintf(
                /* translators: 1: line number in the trusted proxies box, 2: the range on that line. */
                __('Line %1$s, "%2$s", trusts every address. A range that trusts every address would let anyone choose the address their sign-in attempts are counted by. List only your own proxies.', 'corex'),
                number_format_i18n((int) $problem->line),
                sanitize_text_field($problem->value),
            ),
            LoginSettingsProblem::REASON_TOO_MANY_PROXIES => sprintf(
                /* translators: %s: the most trusted proxy lines kept. */
                __('List at most %s trusted proxies.', 'corex'),
                number_format_i18n((int) $problem->max),
            ),
            LoginSettingsProblem::REASON_NOT_A_LIST => __('Write the trusted proxies as text, one address or range per line.', 'corex'),
            LoginSettingsProblem::REASON_TWO_UNITS => $problem->field === 'lockout_minutes'
                ? __('Send the lockout length in minutes or in seconds, not both.', 'corex')
                : __('Send the counting window in minutes or in seconds, not both.', 'corex'),
            LoginSettingsProblem::REASON_TWO_SHAPES => __('Send the three login switches or the older "enabled" and "block default endpoints" settings, not both.', 'corex'),
            LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE => sprintf(
                /* translators: %s: why the login address cannot be used. */
                __('wp-login.php can only be hidden behind a login address of your own. %s', 'corex'),
                $this->address($problem->detail),
            ),
            default => $this->address($problem->detail),
        };
    }

    private function range(LoginSettingsProblem $problem): string
    {
        $min = number_format_i18n((int) $problem->min);
        $max = number_format_i18n((int) $problem->max);

        return match ($problem->field) {
            /* translators: 1: the fewest attempts allowed, 2: the most. */
            'max_attempts' => sprintf(__('Maximum failed attempts must be a whole number from %1$s to %2$s.', 'corex'), $min, $max),
            /* translators: 1: the shortest counting window in minutes, 2: the longest. */
            'window_minutes' => sprintf(__('The counting window must be a whole number of minutes from %1$s to %2$s.', 'corex'), $min, $max),
            /* translators: 1: the shortest lockout in minutes, 2: the longest. */
            'lockout_minutes' => sprintf(__('The lockout length must be a whole number of minutes from %1$s to %2$s.', 'corex'), $min, $max),
            /* translators: 1: the shortest counting window in seconds, 2: the longest. */
            'window_seconds' => sprintf(__('The counting window must be a whole number of seconds from %1$s to %2$s.', 'corex'), $min, $max),
            /* translators: 1: the shortest lockout in seconds, 2: the longest. */
            'lockout_seconds' => sprintf(__('The lockout length must be a whole number of seconds from %1$s to %2$s.', 'corex'), $min, $max),
            /* translators: 1: the fewest days sign-in records are kept, 2: the most. */
            default => sprintf(__('Keep sign-in records for a whole number of days from %1$s to %2$s.', 'corex'), $min, $max),
        };
    }

    /**
     * Why a login address cannot be used. LoginSlug and LoginSlugAvailability return codes because
     * they run on plugins_loaded, long before translations load.
     */
    private function address(string $reason): string
    {
        return match ($reason) {
            LoginSlug::REASON_RESERVED => __(
                'That login address is reserved by WordPress. Choose a different one.',
                'corex',
            ),
            LoginSlugAvailability::REASON_TAKEN_BY_PAGE => __(
                'A published page already uses that address. Choose a different one, or the login and that page would compete for the same URL.',
                'corex',
            ),
            LoginSlugAvailability::REASON_TAKEN_BY_ROUTE => __(
                'Something on this site already routes that address: a post type archive, a category base or another plugin. Choose a different one.',
                'corex',
            ),
            default => __(
                'Use 3 to 81 characters for the login address: lowercase letters, numbers, and hyphens, starting with a letter or number.',
                'corex',
            ),
        };
    }
}
