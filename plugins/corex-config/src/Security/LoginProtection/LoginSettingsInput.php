<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * What a login settings request asks to change (spec 124, FR-015, User Story 7).
 *
 * Only the fields a request sends are read, and each becomes a change to its stored key; a field it
 * does not send is not a change, so the store leaves it as it is. The counting window and the lockout
 * are sent in whole minutes and stored in seconds (minutes x 60), so a length that is not a whole
 * minute (a 30-second window set from the command line) stays as it is until a request sends that
 * field. The previous screen's seconds fields and its `enabled`/`block_default_endpoints` pair are
 * still read, in their old meaning, until slice 10 redraws the screen.
 *
 * A value outside its range ({@see self::RANGES}), a trusted proxy line that is not an address or
 * range, or an unusable login address is a problem; a request with any problem changes nothing.
 * Whether the address is already taken on the site is not asked here: it needs WordPress, so
 * {@see LoginSettingsSaver} asks it.
 */
final readonly class LoginSettingsInput
{
    /**
     * Each number's inclusive range, in the unit of its request field.
     *
     * The minutes allow one minute to one day for the window and one minute to seven days for a
     * lockout, the bounds the route has refused in seconds since spec 068 (60 to 86,400 and 60 to
     * 604,800), which the previous screen's seconds fields keep.
     */
    public const RANGES = [
        'max_attempts'    => [1, 50],
        'window_minutes'  => [1, 1440],
        'lockout_minutes' => [1, 10080],
        'window_seconds'  => [60, 86400],
        'lockout_seconds' => [60, 604800],
        'retention_days'  => [1, 365],
    ];

    /** The most trusted proxy lines kept: enough for a CDN's published ranges, and a bound on the option. */
    public const MAX_PROXIES = 100;

    /** The most of a refused proxy line repeated back. */
    private const ECHOED_LINE_LENGTH = 64;

    /** Each number's request field and the stored key it changes, and how many seconds one unit is. */
    private const NUMBERS = [
        'max_attempts'    => ['threshold', 1],
        'window_minutes'  => ['window_seconds', 60],
        'window_seconds'  => ['window_seconds', 1],
        'lockout_minutes' => ['lockout_seconds', 60],
        'lockout_seconds' => ['lockout_seconds', 1],
        'retention_days'  => ['retain_days', 1],
    ];

    /** A length the screen sends in minutes and the previous screen sent in seconds. */
    private const TWO_UNITS = [
        'window_minutes'  => 'window_seconds',
        'lockout_minutes' => 'lockout_seconds',
    ];

    private const SWITCH_FIELDS = ['hide_wp_login', 'hide_admin', 'limit_attempts'];

    private const PREVIOUS_FIELDS = ['enabled', 'block_default_endpoints'];

    /**
     * @param array<string,mixed>                $changes  stored key => value to write
     * @param array<string,LoginSettingsProblem> $problems request field => why it is refused
     */
    private function __construct(
        public array $changes,
        public ?LoginSwitches $switches,
        public array $problems,
    ) {
    }

    /**
     * @param array<string,mixed> $body    the request's fields
     * @param LoginProtectionSettings $current the settings in force, for the switches a request leaves out
     */
    public static function read(array $body, LoginProtectionSettings $current): self
    {
        $changes = [];
        $problems = [];

        $switches = self::switches($body, $current, $problems);
        self::address($body, $switches ?? $current->switches(), $changes, $problems);
        self::numbers($body, $changes, $problems);
        self::proxies($body, $changes, $problems);
        self::flag($body, 'successful_login_logging', $changes, $problems);

        return new self($changes, $problems === [] ? $switches : null, $problems);
    }

    public function refused(): bool
    {
        return $this->problems !== [];
    }

    /**
     * The switches the request asks for, the ones it leaves out kept as they are; or null when it
     * sends none.
     *
     * @param array<string,mixed>                $body
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function switches(array $body, LoginProtectionSettings $current, array &$problems): ?LoginSwitches
    {
        $sent = array_intersect_key($body, array_flip(self::SWITCH_FIELDS));
        $previous = array_intersect_key($body, array_flip(self::PREVIOUS_FIELDS));

        if ($sent !== [] && $previous !== []) {
            $problems['enabled'] = new LoginSettingsProblem('enabled', LoginSettingsProblem::REASON_TWO_SHAPES);

            return null;
        }

        $on = [];
        foreach ($sent + $previous as $field => $value) {
            $flag = self::onOrOff($value);
            if ($flag === null) {
                $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_NOT_ON_OR_OFF);

                continue;
            }
            $on[$field] = $flag;
        }

        if ($sent !== []) {
            return new LoginSwitches(
                $on['hide_wp_login'] ?? $current->hideWpLogin,
                $on['hide_admin'] ?? $current->hideAdmin,
                $on['limit_attempts'] ?? $current->limitAttempts,
            );
        }

        if ($previous !== []) {
            return LoginSwitches::fromPreviousRelease(
                $on['enabled'] ?? $current->enabled,
                $on['block_default_endpoints'] ?? $current->blockDefaultEndpoints,
            );
        }

        return null;
    }

    /**
     * @param array<string,mixed>                $body
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function address(array $body, LoginSwitches $switches, array &$changes, array &$problems): void
    {
        if (! array_key_exists('custom_slug', $body)) {
            return;
        }

        $raw = $body['custom_slug'];
        $slug = is_string($raw) ? sanitize_title(trim($raw)) : '';
        $reason = is_string($raw) ? LoginSlug::rejectionReason($slug) : LoginSlug::REASON_FORMAT;

        if ($reason === null) {
            $changes['custom_slug'] = $slug;

            return;
        }

        $problems['custom_slug'] = new LoginSettingsProblem(
            'custom_slug',
            $switches->hideWpLogin ? LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE : LoginSettingsProblem::REASON_ADDRESS,
            detail: $reason,
        );
    }

    /**
     * @param array<string,mixed>                $body
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function numbers(array $body, array &$changes, array &$problems): void
    {
        foreach (self::NUMBERS as $field => [$key, $seconds]) {
            if (! array_key_exists($field, $body)) {
                continue;
            }

            $twin = self::TWO_UNITS[$field] ?? null;
            if ($twin !== null && array_key_exists($twin, $body)) {
                $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_TWO_UNITS);

                continue;
            }

            // Sent beside its minutes field, a seconds field is refused there, once.
            $minutes = array_search($field, self::TWO_UNITS, true);
            if ($minutes !== false && array_key_exists($minutes, $body)) {
                continue;
            }

            [$min, $max] = self::RANGES[$field];
            $number = self::wholeNumber($body[$field]);
            if ($number === null || $number < $min || $number > $max) {
                $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_OUT_OF_RANGE, min: $min, max: $max);

                continue;
            }

            $changes[$key] = $number * $seconds;
        }
    }

    /**
     * The trusted proxies, one address or range per line, blank lines left out; and the switch that
     * trusts them, which follows the list unless the request sends it.
     *
     * @param array<string,mixed>                $body
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function proxies(array $body, array &$changes, array &$problems): void
    {
        if (array_key_exists('trusted_proxies', $body)) {
            $lines = self::lines($body['trusted_proxies']);

            if ($lines === null) {
                $problems['trusted_proxies'] = new LoginSettingsProblem('trusted_proxies', LoginSettingsProblem::REASON_NOT_A_LIST);
            } else {
                self::ranges($lines, $changes, $problems);
            }
        }

        if (array_key_exists('trusted_proxy_mode', $body)) {
            self::flag($body, 'trusted_proxy_mode', $changes, $problems);
        } elseif (array_key_exists('trusted_proxy_ranges', $changes)) {
            $changes['trusted_proxy_mode'] = $changes['trusted_proxy_ranges'] !== [];
        }
    }

    /**
     * @param array<int,string>                  $lines    line number => line, as sent
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function ranges(array $lines, array &$changes, array &$problems): void
    {
        $ranges = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));

        if (count($ranges) > self::MAX_PROXIES) {
            $problems['trusted_proxies'] = new LoginSettingsProblem(
                'trusted_proxies',
                LoginSettingsProblem::REASON_TOO_MANY_PROXIES,
                max: self::MAX_PROXIES,
            );

            return;
        }

        $reasons = array_filter(array_map(
            static fn (string $line): ?string => $line === '' ? null : TrustedProxyRange::rejectionReason($line),
            $lines,
        ));

        if ($reasons === []) {
            $changes['trusted_proxy_ranges'] = $ranges;

            return;
        }

        $refused = array_map(
            static fn (string $line): string => substr($line, 0, self::ECHOED_LINE_LENGTH),
            array_intersect_key($lines, $reasons),
        );

        // The sentence is about the first refused line; every refused line is in `lines`.
        $first = (int) array_key_first($reasons);
        $problems['trusted_proxies'] = new LoginSettingsProblem(
            'trusted_proxies',
            $reasons[$first] === TrustedProxyRange::REASON_TRUSTS_EVERY_ADDRESS
                ? LoginSettingsProblem::REASON_TRUSTS_EVERY_ADDRESS
                : LoginSettingsProblem::REASON_NOT_A_PROXY,
            line: $first,
            value: $refused[$first],
            lines: $refused,
        );
    }

    /**
     * The lines of a list or of text, trimmed and numbered from 1 as the owner sees them, blank
     * ones included so the numbers match; or null when it is neither.
     *
     * @return array<int,string>|null
     */
    private static function lines(mixed $sent): ?array
    {
        if (is_string($sent)) {
            $sent = preg_split('/\r\n|\r|\n/', $sent) ?: [];
        }

        if (! is_array($sent)) {
            return null;
        }

        $lines = [];
        $number = 1;
        foreach ($sent as $line) {
            if (! is_string($line)) {
                return null;
            }
            $lines[$number++] = trim($line);
        }

        // A last line break leaves an empty line that is no line of the owner's.
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param array<string,mixed>                $body
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function flag(array $body, string $field, array &$changes, array &$problems): void
    {
        if (! array_key_exists($field, $body)) {
            return;
        }

        $flag = self::onOrOff($body[$field]);
        if ($flag === null) {
            $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_NOT_ON_OR_OFF);

            return;
        }

        $changes[$field] = $flag;
    }

    /** True or false as JSON or a form sends it, or null when it is neither. */
    private static function onOrOff(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /** A whole number as JSON or a form sends it, or null when it is not one. */
    private static function wholeNumber(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d{1,9}$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }
}
