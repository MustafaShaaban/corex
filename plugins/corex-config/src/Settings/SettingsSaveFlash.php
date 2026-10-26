<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Carries a save's result across the redirect that follows it (spec 119, US-SET-01, FR-006).
 *
 * The page used to answer the post itself, so reloading it offered to send the form again. Now the
 * save redirects, and what it did waits here, for the person who saved, until that page is drawn.
 */
final class SettingsSaveFlash
{
    private const PREFIX = 'corex_settings_saved_';

    /** Long enough for the redirect, short enough not to greet a later visit. */
    private const SECONDS = 120;

    public function keep(int $userId, SettingsSaveResult $result): void
    {
        set_transient(self::PREFIX . $userId, $result->toArray(), self::SECONDS);
    }

    /** The result for this person and this page, once; null when there is none. */
    public function take(int $userId, string $page): ?SettingsSaveResult
    {
        $stored = get_transient(self::PREFIX . $userId);
        if (! is_array($stored)) {
            return null;
        }

        $result = SettingsSaveResult::fromArray($stored);
        if ($result->page !== $page) {
            return null;
        }

        delete_transient(self::PREFIX . $userId);

        return $result;
    }
}
