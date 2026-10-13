<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Reset;

defined('ABSPATH') || exit;

/**
 * Where a reset key is kept between the email's link and the new-password form: a cookie of
 * this browser's session that scripts cannot read.
 *
 * It is how `wp-login.php` does it, and for the same reason. Left in the address, the key is
 * in the browser's history and is read by every script the page loads; an account page of a
 * client's site loads scripts `wp-login.php` does not (DECISIONS #340).
 */
final class ResetTicket
{
    /** What the cookie holds once its key has been used: the next page says so, once. */
    public const USED = 'used';

    public static function name(): string
    {
        return 'corex-resetpass-' . COOKIEHASH;
    }

    public function keep(string $login, string $key): void
    {
        // The key first: WordPress makes it of letters and numbers, and a login can hold a colon.
        $this->write($key . ':' . $login);
    }

    /**
     * The login and the key, cleaned here: a cookie is whatever the browser sends.
     *
     * @return array{login: string, key: string}|null
     */
    public function held(): ?array
    {
        [$key, $login] = explode(':', $this->contents(), 2) + ['', ''];

        $key   = (string) preg_replace('/[^a-z0-9]/i', '', $key);
        $login = sanitize_text_field($login);

        return $key !== '' && $login !== '' ? ['login' => $login, 'key' => $key] : null;
    }

    public function markUsed(): void
    {
        $this->write(self::USED);
    }

    public function wasUsed(): bool
    {
        return $this->contents() === self::USED;
    }

    public function forget(): void
    {
        $this->write('');
    }

    private function contents(): string
    {
        $sent = $_COOKIE[self::name()] ?? '';

        return is_string($sent) ? wp_unslash($sent) : '';
    }

    /**
     * Written empty, the cookie is taken away. What is written is also put where this request
     * reads it, so that what is asked after a write is answered from it.
     */
    private function write(string $contents): void
    {
        if ($contents === '') {
            unset($_COOKIE[self::name()]);
        } else {
            $_COOKIE[self::name()] = wp_slash($contents);
        }

        // A page that has begun to be sent can set no cookie: asking raises a warning and sets nothing.
        if (headers_sent()) {
            return;
        }

        setcookie(self::name(), $contents, [
            'expires'  => $contents === '' ? time() - YEAR_IN_SECONDS : 0,
            'path'     => COOKIEPATH,
            'domain'   => (string) COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
