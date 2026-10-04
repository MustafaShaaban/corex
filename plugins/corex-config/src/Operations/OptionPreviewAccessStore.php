<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * The preview-link record, kept in one WordPress option (spec 101).
 *
 * Not autoloaded. It is read on a front-end request only when that request carries a preview value
 * or a preview cookie, and on the Operations screen — so it has no place among the options every
 * request loads.
 *
 * What it holds is a keyed hash, a time and a user id. Anything else found under the option's name
 * is treated as no record: a value this class did not write is not a link.
 */
final class OptionPreviewAccessStore implements PreviewAccessStore
{
    public const OPTION = 'corex_preview_access';

    public function read(): ?array
    {
        $stored = get_option(self::OPTION, null);

        if (! is_array($stored) || ! isset($stored['hash']) || ! is_string($stored['hash'])) {
            return null;
        }

        // A SHA-256 HMAC in hexadecimal, which is the only thing PreviewAccess ever writes here.
        if (preg_match('/^[a-f0-9]{64}$/', $stored['hash']) !== 1) {
            return null;
        }

        return [
            'hash'   => $stored['hash'],
            'issued' => (int) ($stored['issued'] ?? 0),
            'user'   => (int) ($stored['user'] ?? 0),
        ];
    }

    public function write(string $hash, int $issued, int $user): void
    {
        update_option(self::OPTION, ['hash' => $hash, 'issued' => $issued, 'user' => $user], false);
    }

    public function delete(): void
    {
        delete_option(self::OPTION);
    }
}
