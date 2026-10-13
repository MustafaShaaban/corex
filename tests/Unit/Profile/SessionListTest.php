<?php

/**
 * Unit tests for the pure session-list formatter (Spec 068 US9, FR-158, FR-161h). It is handed
 * what WordPress hands over: `WP_Session_Tokens::get_all()`, a plain list of session records
 * with no token or verifier anywhere in it, and the record `WP_Session_Tokens::get()` gives for
 * the request's own session. Until DECISIONS #349 these tests keyed the list by verifier, which
 * WordPress never does, and passed while no session was ever marked on a site.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Corex\Profile\Session\SessionList;

const LAPTOP_SESSION = ['expiration' => 200, 'ip' => '10.0.0.1', 'ua' => 'Laptop UA', 'login' => 100];
const PHONE_SESSION  = ['expiration' => 400, 'ip' => '10.0.0.2', 'ua' => 'Phone UA', 'login' => 300];

/**
 * @param list<array{current:bool,login:int,expiration:int,ip:string,ua:string}> $sessions
 *
 * @return list<string> The browser of each session marked as the one in use.
 */
function browsersMarkedCurrent(array $sessions): array
{
    $current = array_filter($sessions, static fn (array $session): bool => $session['current']);

    return array_values(array_column($current, 'ua'));
}

it('lists the newest sign-in first', function () {
    $sessions = SessionList::format([LAPTOP_SESSION, PHONE_SESSION]);

    expect(array_column($sessions, 'login'))->toBe([300, 100]);
});

it('marks the session whose record is the request\'s own, wherever it is listed', function (array $current, string $browser) {
    $sessions = SessionList::format([LAPTOP_SESSION, PHONE_SESSION], $current);

    expect(browsersMarkedCurrent($sessions))->toBe([$browser]);
})->with([
    'the older sign-in' => [LAPTOP_SESSION, 'Laptop UA'],
    'the newer sign-in' => [PHONE_SESSION, 'Phone UA'],
]);

it('marks one session when two hold the same record', function () {
    $sessions = SessionList::format([LAPTOP_SESSION, PHONE_SESSION, LAPTOP_SESSION], LAPTOP_SESSION);

    expect(browsersMarkedCurrent($sessions))->toBe(['Laptop UA']);
});

it('marks no session when the request\'s own is not one of them', function (?array $current) {
    $sessions = SessionList::format([LAPTOP_SESSION, PHONE_SESSION], $current);

    expect(browsersMarkedCurrent($sessions))->toBe([]);
})->with([
    'a request with no session'      => [null],
    'a session that is not listed'   => [['expiration' => 900, 'ip' => '10.0.0.3', 'ua' => 'Tablet UA', 'login' => 800]],
    'a session listed, but for less' => [['expiration' => 200, 'ip' => '10.0.0.1', 'ua' => 'Laptop UA']],
]);

it('gives a row five fields and nothing else a record carries', function () {
    $record = LAPTOP_SESSION + ['added_by_a_plugin' => 'kept out of the row'];

    $sessions = SessionList::format([$record], $record);

    expect($sessions)->toBe([[
        'current'    => true,
        'login'      => 100,
        'expiration' => 200,
        'ip'         => '10.0.0.1',
        'ua'         => 'Laptop UA',
    ]]);
});

it('tolerates missing fields with safe defaults', function () {
    $sessions = SessionList::format([[]]);

    expect($sessions[0])->toBe([
        'current'    => false,
        'login'      => 0,
        'expiration' => 0,
        'ip'         => '',
        'ua'         => '',
    ]);
});
