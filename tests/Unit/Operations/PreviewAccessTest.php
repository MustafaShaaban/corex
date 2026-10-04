<?php

/**
 * Unit tests for preview access (spec 101, T037; FR-011 to FR-014): the one secret link that lets
 * somebody without an account see the real site while it is in Coming soon.
 *
 * Against an in-memory store and a fixed key, with no WordPress. Two things are held here: the
 * link (a token, in an address) and the access a browser is given for opening it (a grant, in a
 * cookie). They are different values on purpose, and several of these tests are about the gap
 * between them.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\PreviewAccess;
use Corex\Tests\Fixtures\Operations\InMemoryPreviewAccessStore;

beforeEach(function () {
    $this->store  = new InMemoryPreviewAccessStore();
    $this->access = new PreviewAccess($this->store, 'a-key-only-the-site-knows');
    $this->now    = new DateTimeImmutable('2026-10-04T12:00:00+00:00');
});

it('has no link until an operator creates one', function () {
    // FR-011, US4.5. Turning the mode on issues nothing: there is no default link to guess.
    expect($this->access->exists())->toBeFalse()
        ->and($this->access->issued())->toBeNull()
        ->and($this->access->accepts(''))->toBeFalse()
        ->and($this->access->accepts('anything'))->toBeFalse()
        ->and($this->access->grant('anything', $this->now))->toBeNull();
});

it('creates a link whose token is accepted, and remembers when and by whom', function () {
    $token = $this->access->create(7, $this->now);

    expect($token)->toBeString()
        ->and(strlen($token))->toBeGreaterThanOrEqual(43)
        ->and($this->access->exists())->toBeTrue()
        ->and($this->access->accepts($token))->toBeTrue()
        ->and($this->access->issued())->toBe(['time' => $this->now->getTimestamp(), 'user' => 7]);
});

it('does not accept a different token, or part of the right one', function () {
    $token = $this->access->create(7, $this->now);

    expect($this->access->accepts($token . 'x'))->toBeFalse()
        ->and($this->access->accepts(substr($token, 0, -1)))->toBeFalse()
        ->and($this->access->accepts(strtoupper($token)))->toBeFalse()
        ->and($this->access->accepts(''))->toBeFalse();
});

it('issues a different token every time', function () {
    $first = $this->access->create(7, $this->now);
    $this->access->revoke();
    $second = $this->access->create(7, $this->now);

    expect($second)->not->toBe($first);
});

it('will not create a second link over one that exists', function () {
    // Two tabs open on the screen: creating from the stale one must not silently end the link
    // the first tab just handed to the client. Replacing a link is regenerating, and says so.
    $token = $this->access->create(7, $this->now);

    expect($this->access->create(8, $this->now))->toBeNull()
        ->and($this->access->accepts($token))->toBeTrue()
        ->and($this->access->issued()['user'])->toBe(7);
});

it('ends the old token and starts a new one when the link is regenerated', function () {
    $old = $this->access->create(7, $this->now);
    $new = $this->access->regenerate(9, $this->now->modify('+1 day'));

    expect($new)->toBeString()
        ->and($new)->not->toBe($old)
        ->and($this->access->accepts($old))->toBeFalse()
        ->and($this->access->accepts($new))->toBeTrue()
        ->and($this->access->issued())->toBe(['time' => $this->now->modify('+1 day')->getTimestamp(), 'user' => 9]);
});

it('has nothing to regenerate when there is no link', function () {
    expect($this->access->regenerate(7, $this->now))->toBeNull()
        ->and($this->access->exists())->toBeFalse();
});

it('leaves no link when it is revoked', function () {
    $token = $this->access->create(7, $this->now);

    expect($this->access->revoke())->toBeTrue()
        ->and($this->access->exists())->toBeFalse()
        ->and($this->access->accepts($token))->toBeFalse()
        // Nothing was there the second time, and it says so rather than claiming a revocation.
        ->and($this->access->revoke())->toBeFalse();
});

it('leaves no link when it is cleared', function () {
    $token = $this->access->create(7, $this->now);

    $this->access->clear();

    expect($this->access->exists())->toBeFalse()
        ->and($this->access->accepts($token))->toBeFalse();
});

it('keeps nothing that equals the token or can be turned back into it', function () {
    // FR-013. What is stored is a keyed hash: with the database and without the key, the link
    // cannot even be checked, and with both it still cannot be read back.
    $token  = $this->access->create(7, $this->now);
    $stored = $this->store->everythingStored();

    expect($stored)->not->toContain($token)
        ->and($stored)->not->toContain(base64_encode($token))
        ->and($stored)->not->toContain(bin2hex($token))
        ->and($stored)->not->toContain(hash('sha256', $token))
        ->and($stored)->toContain(hash_hmac('sha256', $token, 'a-key-only-the-site-knows'));
});

it('depends on the site key, so a copied database row opens nothing on another site', function () {
    $token   = $this->access->create(7, $this->now);
    $elsewhere = new PreviewAccess($this->store, 'another-site-key');

    expect($elsewhere->accepts($token))->toBeFalse();
});

// The grant — what a browser holds after opening the link.

it('gives a browser that opens the link a grant that is honoured, and is not the token', function () {
    $token = $this->access->create(7, $this->now);

    $grant = $this->access->grant($token, $this->now);

    // The cookie does not carry the link. Somebody who reads the cookie has this browser's
    // access, for as long as it lasts, and cannot hand the link on.
    expect($grant)->toBeString()
        ->and($grant)->not->toContain($token)
        ->and($this->access->honours($grant, $this->now))->toBeTrue()
        ->and($this->access->accepts($grant))->toBeFalse();
});

it('gives no grant for a token that is not the link', function () {
    $this->access->create(7, $this->now);

    expect($this->access->grant('not-the-link', $this->now))->toBeNull();
});

it('honours a grant for fourteen days and not a second longer', function () {
    // "A bounded period" (FR-011), enforced here and not left to the cookie's own expiry, which
    // the browser that holds it can edit.
    $token = $this->access->create(7, $this->now);
    $grant = $this->access->grant($token, $this->now);

    expect(PreviewAccess::LIFETIME)->toBe(14 * 24 * 60 * 60)
        ->and($this->access->expires($this->now))->toBe($this->now->getTimestamp() + PreviewAccess::LIFETIME)
        ->and($this->access->honours($grant, $this->now->modify('+13 days 23 hours')))->toBeTrue()
        ->and($this->access->honours($grant, $this->now->modify('+14 days')))->toBeFalse()
        ->and($this->access->honours($grant, $this->now->modify('+15 days')))->toBeFalse();
});

it('does not honour a grant whose expiry was changed', function () {
    $token = $this->access->create(7, $this->now);
    $grant = $this->access->grant($token, $this->now);

    [$expires, $signature] = explode('.', $grant, 2);
    $extended = ((int) $expires + 365 * 24 * 60 * 60) . '.' . $signature;

    expect($this->access->honours($extended, $this->now))->toBeFalse()
        ->and($this->access->honours($grant, $this->now))->toBeTrue();
});

it('does not honour anything malformed', function (string $grant) {
    $this->access->create(7, $this->now);

    expect($this->access->honours($grant, $this->now))->toBeFalse();
})->with(['', '.', 'abc', '123', '.abc', '9999999999.', '9999999999.zzz', 'not-a-number.abcdef']);

it('ends every grant when the link is regenerated, revoked or cleared', function (string $how) {
    // FR-012 and FR-012a, at the next request and with no list of sessions to walk: the grant is
    // signed over the stored hash, so replacing or removing the hash is what ends it.
    $token = $this->access->create(7, $this->now);
    $grant = $this->access->grant($token, $this->now);

    match ($how) {
        'regenerated' => $this->access->regenerate(7, $this->now),
        'revoked'     => $this->access->revoke(),
        'cleared'     => $this->access->clear(),
    };

    expect($this->access->honours($grant, $this->now))->toBeFalse();
})->with(['regenerated', 'revoked', 'cleared']);

it('honours no grant when there is no link at all', function () {
    expect($this->access->honours((time() + 3600) . '.' . str_repeat('a', 64), $this->now))->toBeFalse();
});
