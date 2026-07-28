<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

/**
 * The validation messages a rendered form carries for its own runtime (#148 item 2).
 *
 * The runtime used to translate its messages through `wp.i18n`, which needs a **built** JS
 * translation catalogue — `wp_set_script_translations()` plus a generated `corex-*.json`. Nothing
 * in this repository generates one, and the failure mode is silent: every validation message stays
 * English on a translated site, whatever the locale, and nothing reports why. That is a trap rather
 * than a design, because it only surfaces on the sites least able to notice a missing translation
 * is a bug rather than a gap in their own catalogue.
 *
 * Rendering the map into the form instead means the strings go through PHP's `__()` and the site's
 * existing `.mo` catalogue, with nothing new to build. The runtime keeps its `wp.i18n` table as a
 * fallback, so a form rendered by something that does not emit this attribute still gets a message.
 *
 * Keyed by rule, not by field: the runtime reports the rule that failed, and a per-field map would
 * be one entry per field per rule for the same handful of sentences.
 */
final class ValidationMessages
{
    /**
     * @return array<string,string> rule key => translated message
     */
    public static function all(): array
    {
        return [
            'required' => __('This field is required.', 'corex'),
            'email' => __('Enter a valid email address.', 'corex'),
            'url' => __('Enter a valid web address.', 'corex'),
            'phone' => __('Enter a valid phone number.', 'corex'),
            'numeric' => __('Enter a number.', 'corex'),
            'max' => __('This value is too long.', 'corex'),
            'min' => __('This value is too short.', 'corex'),
            'pattern' => __('This value is not in the expected format.', 'corex'),
            'default' => __('Please check this field.', 'corex'),
        ];
    }

    /**
     * The map as a JSON attribute value.
     */
    public static function toAttribute(): string
    {
        return (string) wp_json_encode(self::all());
    }
}
