<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Security;

defined('ABSPATH') || exit;

/**
 * Header-injection defense (spec FR-006). A subject, from, reply-to, or recipient
 * display name containing a CR, LF, or any other control character could smuggle
 * extra headers (e.g. an injected Bcc), so such a message is rejected before
 * delivery. Pure and stateless.
 */
final class HeaderGuard
{
    /**
     * @param array<string,string> $fields header field name => value
     *
     * @return string|null a rejection reason, or null when every field is clean
     */
    public function inspect(array $fields): ?string
    {
        foreach ($fields as $name => $value) {
            // Reject CR/LF and every other C0/C1 control char (incl. NUL and DEL).
            if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                return sprintf('Illegal characters in the %s field.', $name);
            }
        }

        return null;
    }
}
