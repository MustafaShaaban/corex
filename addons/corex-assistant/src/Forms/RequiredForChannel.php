<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Forms;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\RuleForAbsentValue;

/**
 * `required_for:<channel>,<channel>`: the field is required when the visitor chose one of those ways to
 * be reached (spec 129, User Story 2). The callback asks for a number to call or message on WhatsApp,
 * and for an email to write to; each is optional otherwise.
 *
 * Asked about an absent value too, so leaving the field out of the request does not get around it. It
 * answers the `required` message key, which the forms engine already words.
 */
final class RequiredForChannel implements RuleForAbsentValue
{
    public const NAME = 'required_for';

    /**
     * @param list<string>         $params    the channels that need this field
     * @param array<string, mixed> $allValues the whole submission, for the chosen channel
     */
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        $channel = is_string($allValues['channel'] ?? null) ? $allValues['channel'] : '';
        if (! in_array($channel, $params, true)) {
            return null;
        }

        return is_scalar($value) && trim((string) $value) !== '' ? null : 'required';
    }
}
