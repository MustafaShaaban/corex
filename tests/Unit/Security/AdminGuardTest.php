<?php

/**
 * Unit tests for the admin-screen capability + nonce gate (spec 028 / DECISIONS #58).
 * WordPress security functions are stubbed at the boundary.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Security\Admin\AdminGuard;

beforeEach(function () {
    $_POST = [];
    Functions\when('wp_unslash')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
});

afterEach(function () {
    $_POST = [];
});

it('authorizes by capability', function () {
    Functions\when('current_user_can')->alias(fn ($cap) => $cap === 'manage_options');

    expect((new AdminGuard())->authorized())->toBeTrue()
        ->and((new AdminGuard())->authorized('edit_posts'))->toBeFalse();
});

it('rejects a post from a user without the capability', function () {
    Functions\when('current_user_can')->justReturn(false);
    Functions\when('wp_verify_nonce')->justReturn(1);
    $_POST['corex_nonce'] = 'good';

    expect((new AdminGuard())->verifiedPost('corex_nonce', 'corex_action'))->toBeFalse();
});

it('rejects a post that is missing the nonce field', function () {
    Functions\when('current_user_can')->justReturn(true);

    expect((new AdminGuard())->verifiedPost('corex_nonce', 'corex_action'))->toBeFalse();
});

it('rejects a post whose nonce does not verify', function () {
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('wp_verify_nonce')->alias(fn ($nonce, $action) => $nonce === 'good' ? 1 : false);
    $_POST['corex_nonce'] = 'bad';

    expect((new AdminGuard())->verifiedPost('corex_nonce', 'corex_action'))->toBeFalse();
});

it('passes an authorized post with a valid nonce', function () {
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('wp_verify_nonce')->alias(fn ($nonce, $action) => $nonce === 'good' && $action === 'corex_action' ? 1 : false);
    $_POST['corex_nonce'] = 'good';

    expect((new AdminGuard())->verifiedPost('corex_nonce', 'corex_action'))->toBeTrue();
});
