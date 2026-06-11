<?php

/**
 * Unit tests for FeatureFlags: truthy-only coercion, default-off, and the layered
 * override (a flag follows whatever Config resolves — env > option > default).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\FeatureFlags;

/**
 * @param array<string,mixed> $map dot-key => value
 */
function flagsFor(array $map): FeatureFlags
{
    $config = new class($map) implements ConfigInterface {
        /** @param array<string,mixed> $map */
        public function __construct(private array $map)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return array_key_exists($key, $this->map) ? $this->map[$key] : $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->map);
        }
    };

    return new FeatureFlags($config);
}

it('treats only truthy values as enabled', function (mixed $value, bool $expected) {
    expect(flagsFor(['features.x' => $value])->enabled('x'))->toBe($expected);
})->with([
    [true, true],
    [1, true],
    ['1', true],
    ['true', true],
    ['on', true],
    ['YES', true],
    [false, false],
    [0, false],
    ['0', false],
    ['false', false],
    ['off', false],
    ['', false],
    ['banana', false],
]);

it('is off when the flag is absent (default false)', function () {
    expect(flagsFor([])->enabled('missing'))->toBeFalse();
    expect(flagsFor([])->disabled('missing'))->toBeTrue();
});

it('honours an explicit default for an absent flag', function () {
    expect(flagsFor([])->enabled('missing', true))->toBeTrue();
});

it('follows the layered Config value (override beats the registry default)', function () {
    // 'pro' is false in the registry, but Config resolved 'on' (e.g. from an option/env).
    $flags = flagsFor([
        'features'     => ['pro' => false, 'mail_queue' => false],
        'features.pro' => 'on',
    ]);

    expect($flags->enabled('pro'))->toBeTrue();
    expect($flags->enabled('mail_queue'))->toBeFalse();
});

it('reports every registered flag via all()', function () {
    $flags = flagsFor([
        'features'            => ['pro' => false, 'mail_queue' => false],
        'features.mail_queue' => '1',
    ]);

    expect($flags->all())->toBe(['pro' => false, 'mail_queue' => true]);
});
