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
 *
 * Each save waits under its own id (DECISIONS #646): every drawing of a page's form posts to an
 * address that carries a new one, and the save redirects back to that address. One place per
 * person let a second save, from another tab or browser, overwrite the first before its page was
 * drawn, and that page then said nothing about what it had just done.
 */
final class SettingsSaveFlash
{
    /** The address argument that carries a save's id to the page it redirects to. */
    public const QUERY_ARG = 'corex_saved';

    private const PREFIX = 'corex_settings_saved_';

    /** Long enough for the redirect, short enough not to greet a later visit. */
    private const SECONDS = 120;

    /** The id a newly drawn form gives the save it will send. */
    public function newSave(): string
    {
        return wp_generate_uuid4();
    }

    public function keep(int $userId, string $save, SettingsSaveResult $result): void
    {
        set_transient($this->key($userId, $save), $result->toArray(), self::SECONDS);
    }

    /** The result of that save, for this person and this page, once; null when there is none. */
    public function take(int $userId, string $page, string $save): ?SettingsSaveResult
    {
        if ($save === '') {
            return null;
        }

        $stored = get_transient($this->key($userId, $save));
        if (! is_array($stored)) {
            return null;
        }

        $result = SettingsSaveResult::fromArray($stored);
        if ($result->page !== $page) {
            return null;
        }

        delete_transient($this->key($userId, $save));

        return $result;
    }

    private function key(int $userId, string $save): string
    {
        return self::PREFIX . $userId . '_' . $save;
    }
}
