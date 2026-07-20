<?php

/**
 * Unit tests for the login slug rules.
 *
 * These rules are the only thing standing between an owner and a site they cannot log into,
 * so they are specified here rather than left to the caller. See DECISIONS #140 for the two
 * lockout paths reproduced on a real install before this existed.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginSlug;

it('accepts an ordinary slug', function () {
    expect(LoginSlug::isValid('corex-login'))->toBeTrue()
        ->and(LoginSlug::isValid('team-entry'))->toBeTrue()
        ->and(LoginSlug::isValid('a1b'))->toBeTrue()
        ->and(LoginSlug::rejectionReason('corex-login'))->toBeNull();
});

it('rejects a slug that is empty or too short to be usable', function () {
    // Trap 1 (DECISIONS #140): "!!!" sanitised to "", which slipped past the old `!== ''`
    // guard and produced a site with no login URL at all.
    expect(LoginSlug::isValid(''))->toBeFalse()
        ->and(LoginSlug::isValid('ab'))->toBeFalse()
        ->and(LoginSlug::rejectionReason(''))->not->toBeNull()
        ->and(LoginSlug::rejectionReason('ab'))->not->toBeNull();
});

it('rejects a slug that does not start alphanumeric or uses illegal characters', function () {
    expect(LoginSlug::isValid('-leading'))->toBeFalse()
        ->and(LoginSlug::isValid('has space'))->toBeFalse()
        ->and(LoginSlug::isValid('UPPER'))->toBeFalse()
        ->and(LoginSlug::isValid('under_score'))->toBeFalse()
        ->and(LoginSlug::isValid('sla/sh'))->toBeFalse();
});

it('rejects a slug longer than the permitted maximum', function () {
    expect(LoginSlug::isValid('a' . str_repeat('b', 80)))->toBeTrue()
        ->and(LoginSlug::isValid('a' . str_repeat('b', 81)))->toBeFalse();
});

it('rejects slugs that collide with WordPress routes or the shortcuts core redirects', function () {
    // /login, /dashboard, and /admin are redirected by core's wp_redirect_admin_locations.
    // Taking one as the login slug would collide with the very redirect we remove.
    foreach (['wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wp-json', 'login', 'admin', 'dashboard', 'feed'] as $reserved) {
        expect(LoginSlug::isValid($reserved))->toBeFalse()
            ->and(LoginSlug::rejectionReason($reserved))->not->toBeNull();
    }
});

it('explains why a slug was rejected rather than failing silently', function () {
    expect(LoginSlug::rejectionReason(''))->toBe(LoginSlug::REASON_EMPTY)
        ->and(LoginSlug::rejectionReason('ab'))->toBe(LoginSlug::REASON_FORMAT)
        ->and(LoginSlug::rejectionReason('wp-admin'))->toBe(LoginSlug::REASON_RESERVED);
});

it('resolves slugs without touching translation functions', function () {
    // The store resolves slugs on plugins_loaded, long before init. A __() call here makes
    // WordPress emit a "textdomain triggered too early" notice on every single request — which
    // Xdebug then dumps into the response body, corrupting login pages and REST replies alike.
    Functions\expect('__')->never();

    expect(LoginSlug::isValid('team-entry'))->toBeTrue()
        ->and(LoginSlug::rejectionReason('ab'))->toBe(LoginSlug::REASON_FORMAT)
        ->and(LoginSlug::orDefault('!!!'))->toBe(LoginSlug::DEFAULT);
});

it('falls back to the default rather than yielding an unusable slug', function () {
    expect(LoginSlug::orDefault(''))->toBe(LoginSlug::DEFAULT)
        ->and(LoginSlug::orDefault('ab'))->toBe(LoginSlug::DEFAULT)
        ->and(LoginSlug::orDefault('wp-admin'))->toBe(LoginSlug::DEFAULT)
        ->and(LoginSlug::orDefault('team-entry'))->toBe('team-entry')
        ->and(LoginSlug::isValid(LoginSlug::DEFAULT))->toBeTrue();
});
