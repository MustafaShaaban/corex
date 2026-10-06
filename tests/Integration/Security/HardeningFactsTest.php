<?php

/**
 * The "No default admin account" fact, asked of WordPress itself.
 *
 * `HardeningChecksTest` hands the engine a fact and checks what it makes of it; nothing checked
 * that the fact was gathered correctly. It was not: `HardeningFacts` compared
 * `username_exists('admin')` with `null`, and WordPress answers `false` for a login nobody has.
 * So the fact was false on every site, the check could never pass, and a site whose only account
 * is called something else was told on every evaluation that it was not ready for production.
 * Found on 2026-10-06 on a client site with no `admin` account.
 *
 * The install these tests run on has an `admin` account or it does not, and that is not theirs to
 * change. The `username_exists` filter decides what WordPress answers about that one login for
 * the length of a test.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\HardeningFacts;

/**
 * Make WordPress answer about the `admin` login as it does about `$as`, until the test ends.
 */
function answerForAdminLoginAs(string $as): Closure
{
    // Asked once, before the filter is on: `$as` may itself be `admin`.
    $answer = username_exists($as);
    $filter = static fn (mixed $userId, string $login): mixed => $login === 'admin' ? $answer : $userId;

    add_filter('username_exists', $filter, 10, 2);

    return $filter;
}

beforeEach(function () {
    $this->filter = null;
});

afterEach(function () {
    if ($this->filter !== null) {
        remove_filter('username_exists', $this->filter, 10);
    }
});

it('reports the default admin account as absent when no user has that login', function () {
    // What WordPress says about a login nobody has, not this test's idea of it.
    $this->filter = answerForAdminLoginAs('corex-no-such-login-' . wp_generate_password(8, false));

    expect(HardeningFacts::gather()['defaultAdminAbsent'])->toBeTrue();
});

it('reports the default admin account as present when a user has that login', function () {
    $existing = get_users(['number' => 1, 'fields' => ['user_login']])[0]->user_login;

    $this->filter = answerForAdminLoginAs($existing);

    expect(HardeningFacts::gather()['defaultAdminAbsent'])->toBeFalse();
});
