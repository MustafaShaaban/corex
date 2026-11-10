<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;

/**
 * The times a visitor may still ask for (spec 129, FR-021): the owner's booking hours from now, less the
 * times other visitors have asked for. What the slots route lists, what the callback's `assistant_slot`
 * rule accepts, and where the callback claims the time it was sent.
 */
final class OpenSlots
{
    /**
     * @param \Closure(): int $now
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly SlotClaims $claims,
        private readonly \Closure $now,
    ) {
    }

    /** The zone the booking hours are kept in. */
    public function zone(): string
    {
        return $this->settings->booking()->timezone;
    }

    /** @return list<int> UTC seconds, in order */
    public function open(): array
    {
        $claimed = array_flip($this->claims->claimed());

        return array_values(array_filter(
            $this->settings->booking()->slots(($this->now)()),
            static fn (int $slot): bool => ! isset($claimed[$slot]),
        ));
    }

    public function isOpen(int $slot): bool
    {
        return in_array($slot, $this->open(), true);
    }

    /** Claims a time for the request being taken; false when another visitor has it. Past claims go first. */
    public function claim(int $slot): bool
    {
        $this->claims->forgetBefore(($this->now)());

        return $this->claims->claim($slot);
    }
}
