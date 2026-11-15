<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Forms;

defined('ABSPATH') || exit;

use Corex\Forms\Form;

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
 * email ({@see RequiredForChannel}). A picked time arrives three ways: `slot` (the moment, in UTC),
 * `zone` (the visitor's time zone), and `time`, the line the team reads ("Thu, Nov 12, 7:00 PM Riyadh =
 * Thu, Nov 12, 6:00 PM Cairo"), written by the visitor's browser from the first two. It is a requested
 * time the team confirms; `slot` is the part to trust, since the visitor's browser wrote the line.
 */
final class AssistantCallbackForm extends Form
{
    public const SLUG = 'corex-assistant-callback';

    public string $slug = self::SLUG;

    public function label(): string
    {
        return __('Assistant callback request', 'corex');
    }

    /**
     * Storage and the team's notification, unless a site sends its own: the
     * `corex_assistant_callback_listeners` filter takes and returns the listeners' container ids, so a
     * site with branded emails puts its own listener in place of the notification.
     *
     * @return list<class-string>
     */
    public function listeners(): array
    {
        $listeners = apply_filters('corex_assistant_callback_listeners', parent::listeners());

        return is_array($listeners) ? array_values(array_filter($listeners, 'is_string')) : parent::listeners();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        $needs = RequiredForChannel::NAME . ':';

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
                'rules' => [$needs . 'call,whatsapp', 'phone'],
                'label' => __('Phone', 'corex'),
            ],
            'email' => [
                'type' => 'email',
                'rules' => [$needs . 'email', 'email', 'max:160'],
                'label' => __('Email', 'corex'),
            ],
            'time' => [
                'type' => 'text',
                'rules' => ['max:160'],
                'label' => __('Requested time', 'corex'),
            ],
            'slot' => [
                'type' => 'text',
                'rules' => ['max:40'],
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
