<?php

/**
 * Unit tests for the pure session-list formatter (Spec 068 US9, FR-158). Verifies it
 * redacts nothing sensitive (no token/verifier leaks into rows), flags the current
 * session, and sorts newest-login first.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Corex\Profile\Session\SessionList;

it('formats and sorts sessions newest-login first, marking the current one', function () {
    $raw = [
        'verifier-old' => ['login' => 100, 'expiration' => 200, 'ip' => '10.0.0.1', 'ua' => 'Old UA'],
        'verifier-new' => ['login' => 300, 'expiration' => 400, 'ip' => '10.0.0.2', 'ua' => 'New UA'],
    ];

    $sessions = SessionList::format($raw, 'verifier-new');

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['login'])->toBe(300)      // newest first
        ->and($sessions[0]['current'])->toBeTrue()
        ->and($sessions[1]['current'])->toBeFalse()
        ->and($sessions[0])->not->toHaveKey('verifier'); // never leaks the verifier
});

it('marks no session current when no verifier is given', function () {
    $raw = ['v1' => ['login' => 10, 'expiration' => 20, 'ip' => '', 'ua' => '']];

    $sessions = SessionList::format($raw);

    expect($sessions[0]['current'])->toBeFalse();
});

it('tolerates missing fields with safe defaults', function () {
    $sessions = SessionList::format(['v1' => []]);

    expect($sessions[0])->toBe([
        'current'    => false,
        'login'      => 0,
        'expiration' => 0,
        'ip'         => '',
        'ua'         => '',
    ]);
});
