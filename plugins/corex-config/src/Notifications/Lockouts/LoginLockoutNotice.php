<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Lockouts;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * What a lockout notice stores: its words, the key that merges repeats, its source and its details
 * (spec 124, FR-016, FR-020).
 *
 * It names the account the typed username belongs to, or says it was an unknown username and gives
 * the fingerprint the Lockouts panel shows for it; the network is only ever a fingerprint. The
 * typed username and the address are never given to it, so it cannot keep them. The producer and the
 * one-time rewrite of older notices both make their rows from here, so a notice reads the same
 * whichever wrote it.
 */
final readonly class LoginLockoutNotice
{
    public const TYPE = 'security.lockout';

    public const SOURCE_ACCOUNT = 'login_account';

    public const SOURCE_FINGERPRINT = 'login_fingerprint';

    /**
     * Merge keys start `security.lockout.` (a dot). The release before wrote `security.lockout:`
     * (a colon) and the typed username, so a username such as `account:1` or `legacy:12` made a key
     * of that shape. No old key can start with a dot here, so no old notice can take a new one's
     * key, and the rewrite of old ones can never collide with one.
     */
    private const KEY_ACCOUNT = self::TYPE . '.account:';

    private const KEY_IDENTITY = self::TYPE . '.identity:';

    private const KEY_LEGACY = self::TYPE . '.legacy:';

    /** What every merge key written before spec 124 starts with: the type, a colon, what was typed. */
    public const OLD_KEY_PREFIX = self::TYPE . ':';

    /**
     * @param array{title:string,body:string} $rendered
     * @param array<string,mixed>             $metadata
     */
    private function __construct(
        public array $rendered,
        public string $dedupKey,
        public string $sourceType,
        public string $sourceId,
        public array $metadata,
    ) {
    }

    /**
     * @param ?int    $userId      The account the username belongs to, or null for an unknown username.
     * @param ?string $accountName That account's name; unused without an account.
     * @param string  $network     The network's fingerprint, or '' when it is not known.
     */
    public static function of(
        ?int $userId,
        ?string $accountName,
        string $identity,
        string $network,
        DateTimeImmutable $lockedUntil,
    ): self {
        $metadata = [
            'locked_until'         => $lockedUntil->format(DATE_ATOM),
            'user_id'              => $userId,
            'identity_fingerprint' => $identity,
            'network_fingerprint'  => $network,
        ];

        if ($userId !== null) {
            return new self(
                [
                    'title' => sprintf(
                        /* translators: %s: the name of the account that was locked out. */
                        __('Repeated login failures locked out “%s”', 'corex'),
                        (string) $accountName,
                    ),
                    'body'  => self::accountBody($network),
                ],
                self::KEY_ACCOUNT . $userId,
                self::SOURCE_ACCOUNT,
                (string) $userId,
                $metadata,
            );
        }

        return new self(
            [
                'title' => __('Repeated login failures locked out an unknown username', 'corex'),
                'body'  => self::unknownBody($identity, $network),
            ],
            self::KEY_IDENTITY . $identity,
            self::SOURCE_FINGERPRINT,
            $identity,
            $metadata,
        );
    }

    /** The same notice under the merge key of the old notice it rewrites, which is its own. */
    public function asLegacy(int $noticeId): self
    {
        return new self($this->rendered, self::KEY_LEGACY . $noticeId, $this->sourceType, $this->sourceId, $this->metadata);
    }

    /** The merge key of an unknown username's lockouts, by its fingerprint. */
    public static function identityKey(string $identity): string
    {
        return self::KEY_IDENTITY . $identity;
    }

    private static function accountBody(string $network): string
    {
        if ($network === '') {
            return __('Review the attempts in Operations & Security.', 'corex');
        }

        return sprintf(
            /* translators: %s: a short fingerprint of the network the attempts came from, never its address. */
            __('The attempts came from the network with fingerprint %s. Review them in Operations & Security.', 'corex'),
            $network,
        );
    }

    private static function unknownBody(string $identity, string $network): string
    {
        if ($network === '') {
            return sprintf(
                /* translators: %s: a short fingerprint of the username that was typed, never the username. */
                __('Username fingerprint %s. Review the attempts in Operations & Security.', 'corex'),
                $identity,
            );
        }

        return sprintf(
            /* translators: 1: a short fingerprint of the username that was typed, never the username. 2: a short fingerprint of the network the attempts came from, never its address. */
            __('Username fingerprint %1$s, from the network with fingerprint %2$s. Review the attempts in Operations & Security.', 'corex'),
            $identity,
            $network,
        );
    }
}
