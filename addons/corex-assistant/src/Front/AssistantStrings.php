<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Front;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;

/**
 * Everything the assistant shows a visitor (spec 129, FR-013).
 *
 * English defaults in the `corex` domain, named after the assistant's name from Settings (else "<site>
 * Assistant"). A site replaces any of them per language with the `corex_assistant_strings` filter:
 *
 *     add_filter('corex_assistant_strings', function (array $strings, string $locale): array {
 *         return $locale === 'ar' ? ['name' => 'مساعد بيريجو'] + $strings : $strings;
 *     }, 10, 2);
 *
 * What the owner writes on Settings → Assistant for a language (its name, welcome and suggested
 * questions, FR-025) wins over the filter: the screen is where the owner changes the words, and an
 * edit there that code silently overrode would read as broken.
 *
 * No string names a model or its maker. `done` keeps a `%s` for the visitor's name, filled by the chat.
 */
final class AssistantStrings
{
    /**
     * @param \Closure(): string $siteName
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly \Closure $siteName,
    ) {
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function for(string $locale): array
    {
        $brand = $this->settings->brand($locale);
        $defaults = $this->defaults($brand['name'] ?? $this->name());
        $filtered = apply_filters('corex_assistant_strings', $defaults, $locale);

        return self::merge(self::merge($defaults, is_array($filtered) ? $filtered : []), $brand);
    }

    /** The assistant's name from Settings for every language, else the site's name with "Assistant". */
    private function name(): string
    {
        $name = $this->settings->name();

        return $name !== '' ? $name : sprintf(
            /* translators: %s: the site's name. */
            __('%s Assistant', 'corex'),
            ($this->siteName)(),
        );
    }

    /**
     * Only known keys, each the type its default is: a filter cannot put markup-shaped data where a
     * string goes, nor drop a string the chat needs.
     *
     * @param array<string, string|list<string>> $defaults
     * @param array<mixed>                       $given
     * @return array<string, string|list<string>>
     */
    private static function merge(array $defaults, array $given): array
    {
        $strings = $defaults;
        foreach ($defaults as $key => $default) {
            $value = $given[$key] ?? null;
            if (is_array($default) && is_array($value)) {
                $strings[$key] = array_values(array_filter(array_map('strval', array_filter($value, 'is_scalar')), static fn (string $item): bool => trim($item) !== ''));
            } elseif (is_string($default) && is_string($value) && trim($value) !== '') {
                $strings[$key] = $value;
            }
        }

        return $strings;
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function defaults(string $name): array
    {
        return [
            'name' => $name,
            'status' => __('Answers from our website · Online', 'corex'),
            /* translators: %s: the assistant's name. */
            'open' => sprintf(__('Chat with %s', 'corex'), $name),
            'teaserTitle' => __('Have a question?', 'corex'),
            /* translators: %s: the assistant's name. */
            'teaserText' => sprintf(__('Ask %s. It replies right away.', 'corex'), $name),
            'dismiss' => __('Dismiss', 'corex'),
            /* translators: %s: the assistant's name. */
            'welcome' => sprintf(__('Hello! I’m %s. I can tell you about what we do and how to get started. How can I help?', 'corex'), $name),
            'suggestions' => [
                __('What do you offer?', 'corex'),
                __('How do I get started?', 'corex'),
                __('How can I contact your team?', 'corex'),
            ],
            'placeholder' => __('Type your question…', 'corex'),
            'send' => __('Send', 'corex'),
            'note' => __('An AI assistant that answers from our website only, and can make mistakes. Please don’t share sensitive information here.', 'corex'),
            'human' => __('I’d rather talk to someone on the team', 'corex'),
            'newChat' => __('New chat', 'corex'),
            'close' => __('Close', 'corex'),
            /* translators: %s: the assistant's name. */
            'typing' => sprintf(__('%s is writing', 'corex'), $name),
            'busy' => __('I can’t answer that right now. Leave your details and our team will get back to you.', 'corex'),
            'error' => __('Something went wrong. Please try again.', 'corex'),
            'expired' => __('This page has been open for a while. Refresh it to keep chatting.', 'corex'),
            'handoffTitle' => __('Shall we contact you?', 'corex'),
            'handoffText' => __('Leave your details and someone from our team will get back to you soon.', 'corex'),
            'fieldName' => __('Your name', 'corex'),
            'fieldPhone' => __('Mobile number', 'corex'),
            'fieldCountry' => __('Country code', 'corex'),
            'fieldEmail' => __('Email', 'corex'),
            'optional' => __('(optional)', 'corex'),
            'fieldChannel' => __('Reach me by', 'corex'),
            'channelCall' => __('Call', 'corex'),
            'channelWhatsapp' => __('WhatsApp', 'corex'),
            'channelEmail' => __('Email', 'corex'),
            'fieldTime' => __('Best time to reach you (optional)', 'corex'),
            'fieldSlot' => __('Pick a time for the call (in your local time)', 'corex'),
            'morning' => __('Morning', 'corex'),
            'afternoon' => __('Afternoon', 'corex'),
            'evening' => __('Evening', 'corex'),
            'anyTime' => __('Any time that suits you', 'corex'),
            'slotNote' => __('Our team confirms the time with you.', 'corex'),
            'attach' => __('Attach a summary of this chat, so the team knows what you need', 'corex'),
            'submit' => __('Send request', 'corex'),
            'sending' => __('Sending…', 'corex'),
            'formError' => __('Please check the highlighted fields.', 'corex'),
            'nameNeeded' => __('Add your name.', 'corex'),
            'phoneNeeded' => __('Add your mobile number to be called or messaged on WhatsApp.', 'corex'),
            'emailNeeded' => __('Add your email to be contacted by email.', 'corex'),
            /* translators: %s: the visitor's name. Keep the %s: the chat fills it in. */
            'done' => __('Thank you, %s. We have your request, and our team will contact you soon.', 'corex'),
        ];
    }
}
