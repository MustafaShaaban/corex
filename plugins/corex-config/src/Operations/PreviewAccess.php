<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * The preview link (spec 101, FR-011 to FR-014): one secret address that lets somebody without an
 * account see the real site while it is in Coming soon.
 *
 * Two values, kept apart on purpose.
 *
 * The **token** is the secret in the link. It is shown to the operator once, when the link is
 * created or regenerated, and is never stored: what is kept is a hash of it keyed with a secret
 * of the site's, which cannot be turned back into the link and, without the key, cannot even be
 * checked against a guess.
 *
 * The **grant** is what a browser holds after opening the link: an expiry and a signature over
 * the stored hash and that expiry. It is not the token, so a cookie that leaks gives away one
 * browser's access and not the link. It is signed over the stored hash, so regenerating, revoking
 * or clearing the link ends every grant at the next request with no list of sessions to walk. And
 * its expiry is inside the signature, so the fourteen days are enforced here rather than left to
 * a cookie lifetime the holder can edit.
 *
 * Preview access grants no capability. It is one fact handed to {@see ComingSoonDecision}, which
 * reads it only while the mode is Coming soon.
 */
final class PreviewAccess
{
    /** The query argument a preview link carries its token in. */
    public const PARAMETER = 'corex_preview';

    /** The cookie a browser's grant is kept in. */
    public const COOKIE = 'corex_preview';

    /** How long a grant lasts: fourteen days. Opening the link again renews it. */
    public const LIFETIME = 1209600;

    public function __construct(
        private readonly PreviewAccessStore $store,
        private readonly string $key,
    ) {
    }

    public function exists(): bool
    {
        return $this->store->read() !== null;
    }

    /**
     * When the current link was issued and by whom, or null when there is none.
     *
     * @return array{time:int,user:int}|null
     */
    public function issued(): ?array
    {
        $record = $this->store->read();

        return $record === null ? null : ['time' => $record['issued'], 'user' => $record['user']];
    }

    /**
     * Create the link, when there is none. Returns its token — the only time it is available —
     * or null when a link already exists, which is left as it is: replacing one is
     * {@see regenerate()}, and says so in the history.
     */
    public function create(int $userId, DateTimeImmutable $now): ?string
    {
        return $this->exists() ? null : $this->issue($userId, $now);
    }

    /**
     * Replace the link, ending the old one and every grant made from it. Returns the new token,
     * or null when there was no link to replace.
     */
    public function regenerate(int $userId, DateTimeImmutable $now): ?string
    {
        return $this->exists() ? $this->issue($userId, $now) : null;
    }

    /**
     * Remove the link, leaving none. False when there was none to remove.
     */
    public function revoke(): bool
    {
        if (! $this->exists()) {
            return false;
        }

        $this->store->delete();

        return true;
    }

    /**
     * Remove the link without ceremony: what leaving Coming soon does (FR-012a).
     */
    public function clear(): void
    {
        $this->store->delete();
    }

    /**
     * Whether a token is the current link's.
     */
    public function accepts(string $token): bool
    {
        $record = $this->store->read();

        return $record !== null
            && $token !== ''
            && hash_equals($record['hash'], $this->hash($token));
    }

    /**
     * The grant for a browser that has just opened the link, or null when the token is not the
     * link's.
     */
    public function grant(string $token, DateTimeImmutable $now): ?string
    {
        if (! $this->accepts($token)) {
            return null;
        }

        $expires = $this->expires($now);

        return $expires . '.' . $this->sign((string) $this->store->read()['hash'], $expires);
    }

    /**
     * When a grant made now stops being honoured, as a timestamp.
     */
    public function expires(DateTimeImmutable $now): int
    {
        return $now->getTimestamp() + self::LIFETIME;
    }

    /**
     * Whether a grant is one this site made, from the link that exists now, and has not run out.
     */
    public function honours(string $grant, DateTimeImmutable $now): bool
    {
        if (preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $grant, $parts) !== 1) {
            return false;
        }

        $record  = $this->store->read();
        $expires = (int) $parts[1];

        return $record !== null
            && $expires > $now->getTimestamp()
            && hash_equals($this->sign($record['hash'], $expires), $parts[2]);
    }

    private function issue(int $userId, DateTimeImmutable $now): string
    {
        // 256 bits from the system's source, written in the URL-safe alphabet.
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->store->write($this->hash($token), $now->getTimestamp(), $userId);

        return $token;
    }

    private function hash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->key);
    }

    private function sign(string $storedHash, int $expires): string
    {
        return hash_hmac('sha256', $storedHash . '|' . $expires, $this->key);
    }
}
