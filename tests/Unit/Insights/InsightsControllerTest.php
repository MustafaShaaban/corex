<?php

/**
 * Unit tests for the insights REST controller's pure seams (spec 037: FR-006). The cap + nonce
 * gates, the unknown-provider guard, and the secret-free payload are checked headlessly; the
 * WP_REST_* callbacks are the thin boundary.
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\InsightProvider;
use Corex\Config\Insights\InsightRegistry;
use Corex\Config\Insights\InsightResult;
use Corex\Config\Insights\InsightsController;
use Corex\Config\Insights\InsightStore;
use Brain\Monkey\Functions;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function fakeProvider(): InsightProvider
{
    return new class implements InsightProvider {
        public function id(): string
        {
            return 'fake';
        }

        public function label(): string
        {
            return 'Fake';
        }

        public function run(string $url): InsightResult
        {
            return new InsightResult('fake', 'Fake', 88, 'ok', [], [], 123);
        }
    };
}

function controller(): InsightsController
{
    $registry = new InsightRegistry();
    $registry->register(fakeProvider());

    return new InsightsController($registry, new InsightStore());
}

it('gates reads on capability and runs on capability + a valid nonce', function () {
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('wp_verify_nonce')->justReturn(1);

    expect(controller()->canManage())->toBeTrue()
        ->and(controller()->verifiedNonce('abc'))->toBeTrue();

    Functions\when('wp_verify_nonce')->justReturn(false);
    expect(controller()->verifiedNonce('bad'))->toBeFalse();
});

it('runs a known provider, stores it, and returns a secret-free payload', function () {
    Functions\when('home_url')->justReturn('https://example.com/');
    Functions\when('get_option')->justReturn([]);
    Functions\when('update_option')->justReturn(true);

    $payload = controller()->result('fake');

    expect($payload)->not->toBeNull()
        ->and($payload['score'])->toBe(88)
        ->and($payload['grade'])->toBe('B')
        ->and(array_keys($payload))->not->toContain('key')
        ->and(array_keys($payload))->not->toContain('token');
});

it('returns null for an unknown provider (the route maps this to 404)', function () {
    expect(controller()->result('nope'))->toBeNull();
});
