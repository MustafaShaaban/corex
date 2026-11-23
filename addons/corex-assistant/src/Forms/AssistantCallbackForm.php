<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Forms;

defined('ABSPATH') || exit;

use Corex\Assistant\Booking\BookingHours;
use Corex\Assistant\Booking\OpenSlots;
use Corex\Assistant\Booking\SlotMoment;
use Corex\Forms\Form;
use Corex\Forms\Validation\AnswersRefused;

/**
 * The assistant's hand-off (spec 129, FR-011): a visitor asks the team to call or write, from inside
 * the chat, at a time they pick when the owner has set booking hours.
 *
 * A CoreX form, so it has the forms engine's honeypot, sanitising, throttle, storage in the Submissions
 * inbox and the team's notification. The default listeners are kept: the team is told whether or not
 * the visitor gave an email (Reply-To is set only when they did), and the visitor is answered in the
 * chat, not by email. The chat draws its own fields and posts here; the labels below are what the
 * inbox and the team's email show.
 *
 * The number is required when the visitor asks for a call or WhatsApp, the email when they ask for an
 * email (`required_if`). A picked time arrives as `slot` (the moment, UTC seconds) and `zone` (the
 * visitor's time zone). The `assistant_slot` rule refuses a time no longer on offer; {@see prepare()}
 * claims it, so two visitors cannot both be given it, and writes `time`, the line the team reads ("Thu 12
 * Nov, 7:00 pm Riyadh = Thu 12 Nov, 6:00 pm Cairo"), so a request written by hand cannot put another line
 * in the inbox. It is a requested time the team confirms, not a booking.
 */
final class AssistantCallbackForm extends Form
{
    public const SLUG = 'corex-assistant-callback';

    public string $slug = self::SLUG;

    /**
     * @param \Closure(int, string): string $describe a moment and the visitor's zone, as the team reads them
     */
    public function __construct(
        private readonly OpenSlots $slots,
        private readonly \Closure $describe,
    ) {
    }

    public function label(): string
    {
        return __('Assistant callback request', 'corex');
    }

    /**
     * Storage and the team's notification, unless a site sends its own: the
     * `corex_assistant_callback_listeners` filter takes and returns the listeners' container ids, so a
     * site with branded emails puts its own listener in place of the notification. The claim of the
     * requested time is settled last, whatever the filter returns ({@see SlotClaimListener}).
     *
     * @return list<class-string>
     */
    public function listeners(): array
    {
        $listeners = apply_filters('corex_assistant_callback_listeners', parent::listeners());
        $listeners = is_array($listeners) ? array_values(array_filter($listeners, 'is_string')) : parent::listeners();

        return [...array_diff($listeners, [SlotClaimListener::class]), SlotClaimListener::class];
    }

    /**
     * The time asked for, made the team's (spec 129, FR-021, FR-022): claimed, so the next visitor is not
     * offered it, and written on the server in both zones. With no time picked, `time` is what the visitor
     * typed. A zone this site does not know is dropped.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     *
     * @throws AnswersRefused when another visitor claimed the time a moment earlier
     */
    public function prepare(array $values): array
    {
        $zone = is_string($values['zone'] ?? null) ? trim($values['zone']) : '';
        $zone = BookingHours::knownZone($zone) ? $zone : '';
        $slot = SlotMoment::read($values['slot'] ?? null);
        if ($slot === null) {
            return ['slot' => '', 'zone' => $zone] + $values;
        }
        if (! $this->slots->claim($slot)) {
            throw new AnswersRefused(['slot' => AssistantSlotRule::TAKEN]);
        }

        return ['time' => ($this->describe)($slot, $zone), 'slot' => SlotMoment::write($slot), 'zone' => $zone] + $values;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'rules' => ['required', 'max:120'],
                'label' => __('Full name', 'corex'),
            ],
            'channel' => [
                'type' => 'select',
                // The engine refuses an answer that is not one of the options below.
                'rules' => ['required'],
                'label' => __('Reach me by', 'corex'),
                'options' => [
                    'call' => __('Phone call', 'corex'),
                    'whatsapp' => __('WhatsApp', 'corex'),
                    'email' => __('Email', 'corex'),
                ],
            ],
            'phone' => [
                'type' => 'phone',
                'rules' => ['required_if:channel,call,whatsapp', 'phone'],
                'label' => __('Phone', 'corex'),
            ],
            'email' => [
                'type' => 'email',
                'rules' => ['required_if:channel,email', 'email', 'max:160'],
                'label' => __('Email', 'corex'),
            ],
            'time' => [
                'type' => 'text',
                'rules' => ['max:160'],
                'label' => __('Requested time', 'corex'),
            ],
            'slot' => [
                'type' => 'text',
                'rules' => ['max:40', AssistantSlotRule::NAME],
                'label' => __('Requested time (UTC)', 'corex'),
            ],
            'zone' => [
                'type' => 'text',
                'rules' => ['max:64'],
                'label' => __('Visitor time zone', 'corex'),
            ],
            'transcript' => [
                'type' => 'textarea',
                'rules' => ['max:6000'],
                'label' => __('Chat (from the visitor’s browser)', 'corex'),
            ],
            'page' => [
                'type' => 'text',
                'rules' => ['max:300'],
                'label' => __('Sent from', 'corex'),
            ],
        ];
    }
}
