<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\RuleForAbsentValue;

/**
 * `required_if:<field>,<value>,...`: the field is required when another field's answer is one of
 * the values given (spec 129, FR-023). `required_if:channel,call,whatsapp` asks for a phone number
 * only when the visitor chose to be called or messaged.
 *
 * Asked about an absent value too, so a request that leaves the field out does not get past it. It
 * answers the `required` message key, which every form already words. Without a field to follow,
 * or with no value to match, it asks for nothing.
 */
final class RequiredIf implements RuleForAbsentValue
{
    /**
     * @param list<string>         $params    the field to follow, then the answers that make this one required
     * @param array<string, mixed> $allValues the whole submission
     */
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        $other = array_shift($params);
        $answer = $other === null ? null : ($allValues[$other] ?? null);
        if (! is_scalar($answer) || ! in_array((string) $answer, $params, true)) {
            return null;
        }

        return Required::missing($value) ? 'required' : null;
    }
}
