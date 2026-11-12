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
 * Only the fields a request sends are read, and a field is a change only when its value differs from
 * the one in force: a value equal to it is neither checked nor written. So the route's own answer can
 * be posted back with one field changed, and a value stored before a rule existed (a 30-second window
 * set from the command line, a trusted proxy list the cap or the `/0` rule refuses) is kept while the
 * request does not change it. The counting window and the lockout are sent in whole minutes and
 * stored in seconds (minutes x 60). The previous screen's seconds fields and its
 * `enabled`/`block_default_endpoints` pair are still read, in their old meaning, until slice 10 redraws
 * the screen. Either may come beside its new equivalent when the two agree; a pair that disagrees is
 * refused.
 *
 * A changed value outside its range ({@see self::RANGES}), a trusted proxy line that is not an
 * address or range, or an unusable login address is a problem; a request with any problem changes
 * nothing. A request with no field this route knows is refused too: it saves nothing, and saying so
 * is better than an answer that reads as saved. Whether the address is already taken on the site is
 * not asked here: it needs WordPress, so {@see LoginSettingsSaver} asks it.
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

    /** The most trusted proxy lines kept: the shared rule's, so the command line keeps the same. */
    public const MAX_PROXIES = TrustedProxyRange::MAX_LINES;

    /** The most of a refused proxy line repeated back, in bytes, cut on a character boundary. */
    private const ECHOED_LINE_BYTES = 64;

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

    /** Every field a save reads; a request with none of them has nothing to save. */
    private const FIELDS = [
        ...self::SWITCH_FIELDS,
        ...self::PREVIOUS_FIELDS,
        'custom_slug',
        'max_attempts',
        'window_minutes',
        'window_seconds',
        'lockout_minutes',
        'lockout_seconds',
        'retention_days',
        'trusted_proxies',
        'trusted_proxy_mode',
        'successful_login_logging',
    ];

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
     * @param array<string,mixed>     $body    the request's fields
     * @param LoginProtectionSettings $current the settings in force
     */
    public static function read(array $body, LoginProtectionSettings $current): self
    {
        if (array_intersect_key($body, array_flip(self::FIELDS)) === []) {
            return new self([], null, [
                'request' => new LoginSettingsProblem('request', LoginSettingsProblem::REASON_NOTHING_TO_SAVE),
            ]);
        }

        $changes = [];
        $problems = [];

        $switches = self::switches($body, $current, $problems);
        self::address($body, $current, $switches ?? $current->switches(), $changes, $problems);
        self::numbers($body, $current, $changes, $problems);
        self::proxies($body, $current, $changes, $problems);
        self::flag($body, 'successful_login_logging', $current->successfulLoginLogging, $changes, $problems);

        return new self($changes, $problems === [] ? $switches : null, $problems);
    }

    public function refused(): bool
    {
        return $this->problems !== [];
    }

    /**
     * The switches the request asks for, the ones it leaves out kept as they are; or null when they
     * are the ones in force.
     *
     * Beside the switches, an old key equal to the one in force is the route's own answer posted
     * back and says nothing; one that differs must say what the switches would write, or the two
     * shapes disagree and the old key is refused. Alone, the old pair is read in its old meaning,
     * and when it equals the pair in force it changes no switch, so the previous screen saving Max
     * attempts does not fold a combination it cannot show into one it can.
     *
     * @param array<string,mixed>                $body
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function switches(array $body, LoginProtectionSettings $current, array &$problems): ?LoginSwitches
    {
        $on = [];
        foreach (array_intersect_key($body, array_flip([...self::SWITCH_FIELDS, ...self::PREVIOUS_FIELDS])) as $field => $value) {
            $flag = self::onOrOff($value);
            if ($flag === null) {
                $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_NOT_ON_OR_OFF);

                continue;
            }
            $on[$field] = $flag;
        }

        if (array_intersect_key($problems, array_flip([...self::SWITCH_FIELDS, ...self::PREVIOUS_FIELDS])) !== []) {
            return null;
        }

        $inForce = ['enabled' => $current->enabled, 'block_default_endpoints' => $current->blockDefaultEndpoints];
        $previous = array_intersect_key($on, $inForce);
        $changedPrevious = array_diff_assoc($previous, $inForce);

        if (array_intersect_key($on, array_flip(self::SWITCH_FIELDS)) !== []) {
            $switches = new LoginSwitches(
                $on['hide_wp_login'] ?? $current->hideWpLogin,
                $on['hide_admin'] ?? $current->hideAdmin,
                $on['limit_attempts'] ?? $current->limitAttempts,
            );
            $written = [
                'enabled'                 => $switches->previousEnabled(),
                'block_default_endpoints' => $switches->previousBlockDefaultEndpoints(),
            ];
            foreach (array_diff_assoc($changedPrevious, $written) as $field => $flag) {
                $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_TWO_SHAPES);
            }

            return $switches == $current->switches() ? null : $switches;
        }

        if ($changedPrevious === []) {
            return null;
        }

        return LoginSwitches::fromPreviousRelease(
            $previous['enabled'] ?? $current->enabled,
            $previous['block_default_endpoints'] ?? $current->blockDefaultEndpoints,
        );
    }

    /**
     * @param array<string,mixed>                $body
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function address(
        array $body,
        LoginProtectionSettings $current,
        LoginSwitches $switches,
        array &$changes,
        array &$problems,
    ): void {
        if (! array_key_exists('custom_slug', $body)) {
            return;
        }

        $raw = $body['custom_slug'];
        $slug = is_string($raw) ? sanitize_title(trim($raw)) : '';
        if ($slug !== '' && $slug === $current->customSlug) {
            return;
        }

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
    private static function numbers(array $body, LoginProtectionSettings $current, array &$changes, array &$problems): void
    {
        $inForce = [
            'threshold'       => $current->threshold,
            'window_seconds'  => $current->windowSeconds,
            'lockout_seconds' => $current->lockoutSeconds,
            'retain_days'     => $current->retainDays,
        ];

        foreach (self::NUMBERS as $field => [$key, $seconds]) {
            if (! array_key_exists($field, $body)) {
                continue;
            }

            $twin = self::TWO_UNITS[$field] ?? null;
            if ($twin !== null && array_key_exists($twin, $body)) {
                // The route's own answer: no minutes for a length that is not a whole minute, and
                // the seconds beside it, which are read instead.
                if ($body[$field] === null) {
                    continue;
                }

                $minutes = self::wholeNumber($body[$field]);
                $twinSeconds = self::wholeNumber($body[$twin]);
                if ($minutes === null || $twinSeconds === null || $minutes * 60 !== $twinSeconds) {
                    $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_TWO_UNITS);

                    continue;
                }
            }

            // Sent beside an agreeing minutes field, a seconds field says the same; it is read once,
            // there. Beside a `null` minutes field it is the one read.
            $minutesField = array_search($field, self::TWO_UNITS, true);
            if ($minutesField !== false && array_key_exists($minutesField, $body) && $body[$minutesField] !== null) {
                continue;
            }

            [$min, $max] = self::RANGES[$field];
            $number = self::wholeNumber($body[$field]);
            if ($number !== null && $number * $seconds === $inForce[$key]) {
                continue;
            }

            if ($number === null || $number < $min || $number > $max) {
                $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_OUT_OF_RANGE, min: $min, max: $max);

                continue;
            }

            $changes[$key] = $number * $seconds;
        }
    }

    /**
     * The trusted proxies, one address or range per line, blank lines left out, each stored in its
     * one spelling; and the switch that trusts them, which follows the list unless the request sends
     * it. A list equal to the one in force, compared in that spelling, is not a change.
     *
     * @param array<string,mixed>                $body
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function proxies(array $body, LoginProtectionSettings $current, array &$changes, array &$problems): void
    {
        if (array_key_exists('trusted_proxies', $body)) {
            $lines = self::lines($body['trusted_proxies']);

            if ($lines === null) {
                $problems['trusted_proxies'] = new LoginSettingsProblem('trusted_proxies', LoginSettingsProblem::REASON_NOT_A_LIST);
            } elseif (self::spelled(array_values(array_filter($lines, static fn (string $line): bool => $line !== '')))
                !== self::spelled($current->trustedProxyRanges)
            ) {
                self::ranges($lines, $changes, $problems);
            }
        }

        if (array_key_exists('trusted_proxy_mode', $body)) {
            self::flag($body, 'trusted_proxy_mode', $current->trustedProxyMode, $changes, $problems);
        } elseif (array_key_exists('trusted_proxy_ranges', $changes)) {
            $changes['trusted_proxy_mode'] = $changes['trusted_proxy_ranges'] !== [];
        }
    }

    /**
     * Each line in its stored spelling, or as it is when it has none.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function spelled(array $lines): array
    {
        return array_map(
            static fn (string $line): string => TrustedProxyRange::canonical(trim($line)) ?? trim($line),
            $lines,
        );
    }

    /**
     * @param array<int,string>                  $lines    line number => line, as sent
     * @param array<string,mixed>                $changes
     * @param array<string,LoginSettingsProblem> $problems
     */
    private static function ranges(array $lines, array &$changes, array &$problems): void
    {
        $ranges = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));

        if (TrustedProxyRange::tooMany(count($ranges))) {
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
            $changes['trusted_proxy_ranges'] = self::spelled($ranges);

            return;
        }

        $refused = array_map(
            static fn (string $line): string => mb_strcut($line, 0, self::ECHOED_LINE_BYTES, 'UTF-8'),
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
    private static function flag(array $body, string $field, bool $inForce, array &$changes, array &$problems): void
    {
        if (! array_key_exists($field, $body)) {
            return;
        }

        $flag = self::onOrOff($body[$field]);
        if ($flag === null) {
            $problems[$field] = new LoginSettingsProblem($field, LoginSettingsProblem::REASON_NOT_ON_OR_OFF);

            return;
        }

        if ($flag !== $inForce) {
            $changes[$field] = $flag;
        }
    }

    /**
     * True or false as JSON or a form sends it, or null when it is neither. An empty string is
     * neither: `filter_var()` reads it as false, which would turn a switch off nobody turned off.
     */
    private static function onOrOff(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ((! is_int($value) && ! is_string($value)) || $value === '') {
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
