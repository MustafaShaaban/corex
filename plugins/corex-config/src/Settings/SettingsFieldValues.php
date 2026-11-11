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
 * shows the value in force instead and cannot be edited. The Media switches show their runtime
 * default before the first save, and the read-outs (server support, Advanced) are live facts.
 */
final class SettingsFieldValues
{
    /** Defaults corex-media applies while nothing is stored (mirrors `MediaSettings::defaults()`). */
    private const MEDIA_DEFAULTS = [
        'media.webp.enabled'      => '1',
        'media.webp.convert_jpeg' => '1',
        'media.webp.convert_png'  => '1',
        'media.webp.quality'      => '82',
        'media.webp.min_saving'   => '5',
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

        // The Media defaults are drawn before the first save, so the form matches what runs.
        // After any save the stored value wins, including an explicit "off".
        return $stored === '' ? (self::MEDIA_DEFAULTS[$key] ?? '') : $stored;
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
