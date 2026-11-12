<?php

/**
 * The one-time rewrite of lockout notices stored before spec 124 (FR-020, US-SEC-10 scenario 5).
 *
 * The release before kept the typed username in a lockout notice's title, its merge key and its
 * source, and the visitor's address in its body and details. On update, each such notice is
 * rewritten to the rule a new one follows: "unknown username" (an old lockout recorded no account),
 * a short fingerprint of the site-keyed value, the network only as a fingerprint.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Notifications\Lockouts\LockoutNoticeRewrite;
use Corex\Config\Notifications\Lockouts\LockoutNoticeStore;
use Corex\Config\Notifications\Lockouts\LoginLockoutNotice;
use Corex\Config\Notifications\Lockouts\StoredLockoutNotice;
use Corex\Config\Security\LoginProtection\LoginHashKey;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

/** Lockout notices as the table holds them; it answers a read as the query does. */
final class ArrayLockoutNoticeStore implements LockoutNoticeStore
{
    /** @var array<int, StoredLockoutNotice> */
    public array $rows = [];

    /** @var array<int, LoginLockoutNotice> */
    public array $rewritten = [];

    /** @var list<array{0:int,1:int}> */
    public array $reads = [];

    public function legacyAfter(int $afterId, int $limit): array
    {
        $this->reads[] = [$afterId, $limit];
        $legacy = array_filter(
            $this->rows,
            static fn (StoredLockoutNotice $row): bool => $row->id > $afterId
                && ! in_array($row->sourceType, [LoginLockoutNotice::SOURCE_ACCOUNT, LoginLockoutNotice::SOURCE_FINGERPRINT], true),
        );
        ksort($legacy);

        return array_slice(array_values($legacy), 0, $limit);
    }

    public function rewrite(int $id, LoginLockoutNotice $notice): void
    {
        $this->rewritten[$id] = $notice;
        $old = $this->rows[$id];
        $this->rows[$id] = new StoredLockoutNotice($id, $notice->sourceType, $notice->sourceId, $notice->dedupKey, $notice->metadata, $old->latestOccurredAt);
    }
}

function rewritePolicy(): LoginProtectionPolicy
{
    return new LoginProtectionPolicy(
        new LoginProtectionSettings(
            enabled: true,
            customSlug: 'team-login',
            blockDefaultEndpoints: true,
            threshold: 5,
            windowSeconds: 300,
            lockoutSeconds: 900,
            trustedProxyMode: false,
            trustedProxyRanges: [],
            retainDays: 30,
            successfulLoginLogging: true,
        ),
        new class () implements LoginHashKey {
            public function current(): string
            {
                return 'rewrite-site-key';
            }
        },
    );
}

/** A notice as the release before this one stored it. */
function legacyNotice(int $id, string $username = 'Sara.Example', mixed $ip = '198.51.100.77'): StoredLockoutNotice
{
    $metadata = ['locked_until' => '2026-11-12T10:15:00+00:00'];
    if ($ip !== null) {
        $metadata['client_ip'] = $ip;
    }

    return new StoredLockoutNotice(
        $id,
        'login_identity',
        $username,
        'security.lockout:' . $username,
        $metadata,
        new DateTimeImmutable('2026-11-12T10:00:00+00:00'),
    );
}

it('FR-020: rewrites an old notice so no part of it holds the typed username or the address', function () {
    $store = new ArrayLockoutNoticeStore();
    $store->rows[7] = legacyNotice(7);

    (new LockoutNoticeRewrite($store, rewritePolicy()))->run();

    $notice = $store->rewritten[7];
    $stored = (string) json_encode([$notice->rendered, $notice->dedupKey, $notice->sourceType, $notice->sourceId, $notice->metadata]);
    expect(strtolower($stored))->not->toContain('sara.example')
        ->and($stored)->not->toContain('198.51.100.77')
        ->and($notice->dedupKey)->toBe('security.lockout:legacy:7')
        ->and($notice->metadata['locked_until'])->toBe('2026-11-12T10:15:00+00:00')
        ->and(array_keys($notice->metadata))->not->toContain('client_ip');
});

it('US-SEC-10 scenario 5, edge case: an old notice reads "unknown username", with the fingerprints the Lockouts panel shows for its rows', function () {
    $store = new ArrayLockoutNoticeStore();
    $store->rows[7] = legacyNotice(7);
    $policy = rewritePolicy();

    (new LockoutNoticeRewrite($store, $policy))->run();

    // The old lockout's rows hold the plain hashes; the panel shows them keyed again, and so does the notice.
    $identity = $policy->fingerprint(hash('sha256', 'sara.example'));
    $network = $policy->fingerprint(hash('sha256', '198.51.100.77'));
    expect($store->rewritten[7]->rendered['title'])->toContain('unknown username')
        ->and($store->rewritten[7]->rendered['body'])->toContain($identity)
        ->and($store->rewritten[7]->rendered['body'])->toContain($network)
        ->and($store->rewritten[7]->metadata['user_id'])->toBeNull()
        ->and($store->rewritten[7]->metadata['identity_fingerprint'])->toBe($identity)
        ->and($store->rewritten[7]->sourceType)->toBe(LoginLockoutNotice::SOURCE_FINGERPRINT);
});

it('FR-020: gives an old notice whose address cannot be read no network at all', function (mixed $ip) {
    $store = new ArrayLockoutNoticeStore();
    $store->rows[3] = legacyNotice(3, 'owner', $ip);

    (new LockoutNoticeRewrite($store, rewritePolicy()))->run();

    expect($store->rewritten[3]->metadata['network_fingerprint'])->toBe('')
        ->and($store->rewritten[3]->rendered['body'])->not->toContain('network');
})->with([
    'no address' => [null],
    'not an address' => ['somewhere'],
    'not text' => [[1, 2]],
]);

it('FR-020: works through the notices in batches, and a second run changes nothing', function () {
    $store = new ArrayLockoutNoticeStore();
    foreach (range(1, 5) as $id) {
        $store->rows[$id] = legacyNotice($id, 'user-' . $id);
    }
    $rewrite = new LockoutNoticeRewrite($store, rewritePolicy(), batchSize: 2);

    $rewrite->run();
    $firstReads = $store->reads;
    $after = $store->rows;
    $store->reads = [];
    $store->rewritten = [];
    $rewrite->run();

    expect($firstReads)->toBe([[0, 2], [2, 2], [4, 2]])
        ->and(array_map(static fn (StoredLockoutNotice $row): string => $row->dedupKey, $after))->toBe([
            1 => 'security.lockout:legacy:1',
            2 => 'security.lockout:legacy:2',
            3 => 'security.lockout:legacy:3',
            4 => 'security.lockout:legacy:4',
            5 => 'security.lockout:legacy:5',
        ])
        ->and($store->rewritten)->toBe([])
        ->and($store->rows)->toEqual($after);
});

it('leaves a notice already written to the new rule alone', function () {
    $store = new ArrayLockoutNoticeStore();
    $store->rows[9] = new StoredLockoutNotice(9, LoginLockoutNotice::SOURCE_ACCOUNT, '42', 'security.lockout:account:42', ['user_id' => 42], new DateTimeImmutable('now'));

    (new LockoutNoticeRewrite($store, rewritePolicy()))->run();

    expect($store->rewritten)->toBe([]);
});
