<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * A telephone number in E.164 shape (#148 item 4).
 *
 * Before this there was no phone validation at all, so a phone field could only be length-capped
 * and `12345` and `call me` both passed — the team receives numbers it cannot dial.
 *
 * **Formatting characters are stripped before the pattern runs.** People write `+20 101 699 9700`,
 * `(020) 7946-0958` and `+1.555.0100`, and a rule that rejects a correct number for its spaces
 * teaches them the form is broken rather than that their number is. Only separators are removed;
 * letters are not, so `call me` still fails.
 *
 * Empty is deferred to `required`, like every other rule here: a rule that also enforced presence
 * would make an optional phone field impossible to express.
 */
final class Phone implements Rule
{
    /** Separators people put in phone numbers. Nothing here changes which digits were meant. */
    private const FORMATTING = [' ', '(', ')', '-', '.', "\u{00a0}"];

    /**
     * E.164: an optional `+`, a non-zero leading digit, then up to 14 more.
     */
    private const PATTERN = '/^\+?[1-9]\d{1,14}$/';

    /**
     * @param array<int,string>   $params
     * @param array<string,mixed> $allValues
     */
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $digits = str_replace(self::FORMATTING, '', $raw);

        return preg_match(self::PATTERN, $digits) === 1 ? null : 'phone';
    }
}
