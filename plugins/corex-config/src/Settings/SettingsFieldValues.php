<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Support\Config\SettingLocks;

/**
 * What each Settings field shows (spec 119, US-SET-01 and US-SET-02).
 *
 * A field shows the site's stored value, unless the site's `.env` or the network locks it, when it
 * shows the value in force instead and cannot be edited. An add-on's settings (Media, Assistant)
 * show their runtime default before the first save, and the read-outs (server support, Advanced) are live facts.
 */
final class SettingsFieldValues
{
    /**
     * What an add-on applies while nothing is stored, so the screen draws what runs: corex-media's
     * (mirrors `MediaSettings::defaults()`) and corex-assistant's (mirrors
     * `AssistantSettings::DEFAULTS`: the free services on, the paid ones off, the limits, the
     * booking hours). Named here because the screen draws them with the add-on off too.
     */
    public const DEFAULTS = [
        'media.webp.enabled'           => '1',
        'media.webp.convert_jpeg'      => '1',
        'media.webp.convert_png'       => '1',
        'media.webp.quality'           => '82',
        'media.webp.min_saving'        => '5',
        'assistant.gemini.on'          => '1',
        'assistant.groq.on'            => '1',
        'assistant.openrouter.on'      => '1',
        'assistant.mistral.on'         => '1',
        'assistant.openai.on'          => '0',
        'assistant.anthropic.on'       => '0',
        'assistant.limit.hour'         => '20',
        'assistant.limit.day'          => '60',
        'assistant.daily_cap'          => '600',
        'assistant.paid_cap'           => '50',
        'assistant.booking.days'       => 'mon-fri',
        'assistant.booking.start'      => '09:00',
        'assistant.booking.end'        => '17:00',
        'assistant.booking.slot'       => '30',
        'assistant.booking.notice'     => '120',
        'assistant.booking.horizon'    => '7',
    ];

    public function __construct(
        private readonly SettingsStore $store,
        private readonly SettingLocks $locks,
        private readonly SettingsDiagnostics $diagnostics,
    ) {
    }

    /**
     * The value the form draws for a field the site edits: what a save compares the post with, so
     * that only a changed value is written (spec 114, FR-028).
     */
    public function shown(string $key): string
    {
        if ($key === 'media.webp.support') {
            // A live read-out the corex-media add-on gives through a filter (Principle IX).
            return (string) apply_filters('corex_media_support_summary', '');
        }

        if (str_starts_with($key, 'advanced.')) {
            return $this->diagnostics->value($key);
        }

        $stored = $this->store->get($key);

        // An add-on's defaults are drawn before the first save, so the form matches what runs.
        // After any save the stored value wins, including an explicit "off".
        return $stored === '' ? (self::DEFAULTS[$key] ?? '') : $stored;
    }

    /** How the field is drawn. */
    public function state(string $key, string $type): FieldState
    {
        if ($type === 'info') {
            return FieldState::plain($this->shown($key));
        }

        $lock = $this->locks->lockOf($key);

        if ($type === 'password') {
            // Write-only: never drawn back, whether it is stored or set in `.env`.
            return new FieldState('', '', $lock, $lock === null && $this->store->get($key) !== '');
        }

        if ($lock !== null) {
            return new FieldState($lock->value, $lock->value, $lock);
        }

        $shown = $this->shown($key);

        return new FieldState($shown, $shown);
    }
}
