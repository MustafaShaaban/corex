<?php

/**
 * A login address can be well-formed and still be taken (spec 077, T024 / FR-018).
 *
 * `LoginSlug` answers what a pattern can answer. This is the half that needs the database: `about`
 * passes every rule in `LoginSlug` and is also one of the most common page slugs on the internet.
 * The collision does not show up at save time today — it shows up later, as a login serving
 * somebody's About page.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Config\Security\LoginProtection\LoginSlugAvailability;

beforeEach(function () {
    $this->availability = new LoginSlugAvailability();
    $this->created      = [];
});

afterEach(function () {
    foreach ($this->created as $id) {
        wp_delete_post($id, true);
    }
});

it('accepts an address nothing answers at', function () {
    expect($this->availability->isAvailable('corex-login-' . wp_generate_password(8, false)))
        ->toBeTrue();
});

it('refuses an address a published page already uses', function () {
    $slug = 'corex-collision-' . wp_generate_password(6, false);

    $this->created[] = wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => 'Collision fixture',
        'post_name'   => $slug,
    ]);

    expect($this->availability->rejectionReason($slug))
        ->toBe(LoginSlugAvailability::REASON_TAKEN_BY_PAGE);
});

it('ignores a draft, because a draft answers at no address', function () {
    $slug = 'corex-draft-' . wp_generate_password(6, false);

    $this->created[] = wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'draft',
        'post_title'  => 'Draft fixture',
        'post_name'   => $slug,
    ]);

    // A collision is about what serves a request. An unpublished page serves nothing, so refusing
    // the address would be refusing for a reason that is not true.
    expect($this->availability->isAvailable($slug))->toBeTrue();
});

it('does not care about leading or trailing slashes', function () {
    $slug = 'corex-slash-' . wp_generate_password(6, false);

    $this->created[] = wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => 'Slash fixture',
        'post_name'   => $slug,
    ]);

    expect($this->availability->isAvailable('/' . $slug))->toBeFalse()
        ->and($this->availability->isAvailable($slug . '/'))->toBeFalse();
});

it('leaves empty to LoginSlug rather than answering twice', function () {
    // Two classes answering the same question is how they drift. Emptiness is `LoginSlug`'s to
    // reject, and it does.
    expect($this->availability->rejectionReason(''))->toBeNull()
        ->and(LoginSlug::rejectionReason(''))->toBe(LoginSlug::REASON_EMPTY);
});

it('still leaves shape and reserved names to LoginSlug', function () {
    // The two halves must not overlap: this one knows nothing about patterns or WordPress's
    // reserved paths, and must not start guessing at them.
    expect(LoginSlug::rejectionReason('wp-admin'))->toBe(LoginSlug::REASON_RESERVED)
        ->and(LoginSlug::rejectionReason('no'))->toBe(LoginSlug::REASON_FORMAT);
});

it('refuses an address a rewrite rule already routes', function () {
    // Rewrite rules only exist with pretty permalinks, so a plain-permalink install has nothing to
    // collide with — and saying so is more honest than asserting against an empty rule set.
    if (! get_option('permalink_structure')) {
        expect(true)->toBeTrue();

        return;
    }

    // The category base is a rewrite rule on every WordPress install with permalinks, so this
    // needs no fixture — and it is exactly the kind of collision an owner would not think to check.
    $base = (string) (get_option('category_base') ?: 'category');

    expect($this->availability->rejectionReason($base))
        ->toBe(LoginSlugAvailability::REASON_TAKEN_BY_ROUTE);
});
