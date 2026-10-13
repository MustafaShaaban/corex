<?php

/**
 * The name a new account goes by, made from its login (DECISIONS #335). Headless: the rule
 * reads no WordPress state.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Corex\Profile\Account\PublicName;

it('names an account whose login is an email address by the part before the @', function (string $login, string $name) {
    expect(PublicName::forLogin($login))->toBe($name);
})->with([
    'a plain address'        => ['dalia.hassan@example.com', 'dalia.hassan'],
    'a plus in the address'  => ['name+shop@example.com', 'name+shop'],
    'an apostrophe'          => ["o'brien@example.com", "o'brien"],
    'a subdomain'            => ['dalia@mail.example.co.uk', 'dalia'],
]);

it('names an account by a login that is not an email address', function (string $login) {
    expect(PublicName::forLogin($login))->toBe($login);
})->with([
    'a username'              => ['salma'],
    'a username with spaces'  => ['Salma K.'],
    'an @ and no domain'      => ['salma@home'],
    'an @ at the start'       => ['@salma'],
]);
