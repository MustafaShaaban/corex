<?php

/**
 * Unit tests for what the account add-on says of the activity it records (spec 068, FR-161g).
 *
 * A row of "Recent activity" was the event's internal name. The add-on words the kinds it
 * records itself, and has no sentence for any other: those are not listed.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Profile\Notification\AccountActivity;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('says what happened for each kind the add-on records', function (string $kind, string $sentence) {
    expect(AccountActivity::sentence($kind))->toBe($sentence);
})->with([
    [AccountActivity::PROFILE_UPDATED, 'Your profile was updated.'],
    [AccountActivity::OTHER_SESSIONS_SIGNED_OUT, 'Your other sessions were signed out.'],
]);

it('has a sentence for every kind it lists', function () {
    $sentences = array_map(AccountActivity::sentence(...), AccountActivity::kinds());

    expect(AccountActivity::kinds())->not->toBeEmpty()
        ->and(array_filter($sentences, static fn (string $sentence): bool => $sentence === ''))->toBe([]);
});

it('has no sentence for a kind another part of the product records', function () {
    expect(AccountActivity::sentence('submission.export.queued'))->toBe('')
        ->and(AccountActivity::kinds())->not->toContain('submission.export.queued');
});
