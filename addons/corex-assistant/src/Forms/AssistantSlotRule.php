<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Forms;

defined('ABSPATH') || exit;

use Corex\Assistant\Booking\OpenSlots;
use Corex\Assistant\Booking\SlotMoment;
use Corex\Forms\Validation\Rule;

/**
 * `assistant_slot`: the time a visitor picked is still on offer (spec 129, FR-021). Refused when another
 * visitor has asked for it since the list was drawn, when it is past the notice or outside the hours, and
 * when it is not a time at all. No time ("any time") passes.
 *
 * Answers `slot_taken`, which the chat turns into "that time was just taken" and a fresh list. This is the
 * early answer; the claim itself is made once every answer has passed ({@see AssistantCallbackForm::prepare()}).
 */
final class AssistantSlotRule implements Rule
{
    public const NAME = 'assistant_slot';

    public const TAKEN = 'slot_taken';

    public function __construct(private readonly OpenSlots $slots)
    {
    }

    /**
     * @param list<string>         $params    none
     * @param array<string, mixed> $allValues the whole submission
     */
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        $slot = SlotMoment::read($value);

        return $slot !== null && $this->slots->isOpen($slot) ? null : self::TAKEN;
    }
}
