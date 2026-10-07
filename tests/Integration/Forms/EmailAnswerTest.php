<?php

/**
 * What an email answer becomes before the rules see it, against WordPress's real cleaners.
 *
 * The rule this pins: nothing `EmailAnswer::clean()` returns is an address the visitor did not
 * type. `SubmitLifecycleTest` shows what that means for a submission; this is the table behind it,
 * including the cases a submission test cannot tell apart.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Support\EmailAnswer;

it('hands the rules what was typed, or nothing, and never a different address', function (mixed $typed, string $expected) {
    expect(EmailAnswer::clean($typed))->toBe($expected);
})->with([
    'an ordinary address is untouched' => ['m@example.com', 'm@example.com'],
    'surrounding space is not part of the answer' => ["  m@example.com\n", 'm@example.com'],
    'a plus tag and a percent sign survive' => ['a%40b+news@example.com', 'a%40b+news@example.com'],
    'a comma typed for a dot stays wrong' => ['sal,ma@example.com', 'sal,ma@example.com'],
    'a letter the cleaner would drop stays' => ['josé@example.com', 'josé@example.com'],
    'an underscore in the domain stays' => ['salma@exa_mple.com', 'salma@exa_mple.com'],
    'text with no address stays text' => ['not-an-email', 'not-an-email'],
    // Stripping the markup leaves a clean address nobody typed, so nothing is kept.
    'markup around an address is dropped whole' => ['<b>salma</b>@example.com', ''],
    'an empty answer stays empty' => ['', ''],
    'a list is not an email answer' => [['m@example.com'], ''],
]);

/**
 * For the callers that need an address or nothing — a subscription, an account, a reply — and
 * hand it to a service that would find a cleaned address valid.
 */
it('gives a caller the address as typed, or nothing', function (mixed $typed, string $expected) {
    expect(EmailAnswer::address($typed))->toBe($expected);
})->with([
    'an ordinary address' => ['m@example.com', 'm@example.com'],
    'surrounding space is not part of it' => [" m@example.com\t", 'm@example.com'],
    'a comma typed for a dot is not an address' => ['sal,ma@example.com', ''],
    'a letter the cleaner would drop' => ['josé@example.com', ''],
    'a quoted name the cleaner would unquote' => ['"sal ma"@example.com', ''],
    'no address at all' => ['not-an-email', ''],
    'nothing typed' => ['', ''],
    'a list' => [['m@example.com'], ''],
]);
