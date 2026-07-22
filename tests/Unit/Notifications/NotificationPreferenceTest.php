<?php

/**
 * Unit tests for notification preferences (spec 072 US5: FR-020).
 *
 * A user's per-category in-app preference, within policy: a category can be muted, but the mandatory
 * categories (security / system / operations) can never be — they are always shown.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationPreference;

it('allows every category by default', function () {
    $pref = NotificationPreference::defaults();

    expect($pref->allowsInApp(NotificationCategory::JOBS))->toBeTrue()
        ->and($pref->allowsInApp(NotificationCategory::SUBMISSIONS))->toBeTrue();
});

it('mutes a category the user turned off', function () {
    $pref = NotificationPreference::fromMap([ NotificationCategory::JOBS => false ]);

    expect($pref->allowsInApp(NotificationCategory::JOBS))->toBeFalse()
        ->and($pref->allowsInApp(NotificationCategory::SUBMISSIONS))->toBeTrue();
});

it('never mutes a mandatory category, even when asked to', function () {
    $pref = NotificationPreference::fromMap([
        NotificationCategory::SECURITY => false,
        NotificationCategory::SYSTEM   => false,
    ]);

    expect($pref->allowsInApp(NotificationCategory::SECURITY))->toBeTrue()
        ->and($pref->allowsInApp(NotificationCategory::SYSTEM))->toBeTrue()
        ->and($pref->isMandatory(NotificationCategory::SECURITY))->toBeTrue()
        ->and($pref->isMandatory(NotificationCategory::JOBS))->toBeFalse();
});

it('drops unknown categories and round-trips through its array form', function () {
    $pref = NotificationPreference::fromMap([
        NotificationCategory::JOBS => false,
        'not-a-category'           => false,
    ]);

    expect($pref->toArray())->toBe([ NotificationCategory::JOBS => false ])
        ->and( NotificationPreference::fromMap( $pref->toArray() )->allowsInApp( NotificationCategory::JOBS ) )->toBeFalse();
});
