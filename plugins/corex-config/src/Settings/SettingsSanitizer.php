<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * The pure per-type sanitization + validation for a settings write (spec 068 T197/T198). It never
 * persists — it returns a clean value to store, or null when the input is invalid and must be
 * rejected (so a bad email, URL, or unknown select option is never saved). Write-only secrets that
 * arrive empty are reported so the caller can preserve the stored value rather than clearing it.
 * The only WordPress calls are the core sanitizers, so it stays unit-testable.
 */
final class SettingsSanitizer
{
    /**
     * @param array<string,string> $options select field option keys
     *
     * @return string|null the clean value, or null when the input is invalid and must be rejected
     */
    public function sanitize(string $raw, string $type, array $options = []): ?string
    {
        if ($raw === '') {
            return '';
        }

        return match ($type) {
            // What was typed is what is judged. Cleaned first, an address WordPress could
            // "repair" was saved as another one: sales@münchen.de as sales@mnchen.de.
            'email'        => is_email(trim($raw)) ? sanitize_email(trim($raw)) : null,
            'url', 'media' => esc_url_raw($raw) !== '' ? esc_url_raw($raw) : null,
            'select'       => in_array($raw, array_keys($options), true) ? $raw : null,
            // Written into a style attribute of an email, so one form only (spec 106).
            'hex-colour'   => preg_match('/^#[0-9a-f]{6}$/i', trim($raw)) === 1 ? strtolower(trim($raw)) : null,
            default        => sanitize_text_field($raw),
        };
    }

    /**
     * Whether a sanitized value for the given field type must be skipped rather than saved — an
     * empty write-only secret is preserved (the stored key is never cleared by re-saving the form).
     */
    public function shouldPreserve(string $value, string $type): bool
    {
        return $value === '' && $type === 'password';
    }
}
