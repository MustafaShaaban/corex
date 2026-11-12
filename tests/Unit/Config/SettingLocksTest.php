<?php

/**
 * Where a setting's value in force is locked (spec 119, US-SET-02): the site's `.env` first, the
 * network second, and nowhere when neither holds the key.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Support\Config\SettingLock;
use Corex\Support\Config\SettingLocks;
use Corex\Support\Config\Source;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/** @param array<string,mixed> $values */
function lockSource(array $values): Source
{
    return new class ($values) implements Source {
        /** @param array<string,mixed> $values */
        public function __construct(private readonly array $values)
        {
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }

        public function get(string $key): mixed
        {
            return $this->values[$key] ?? null;
        }
    };
}

it('answers the .env value, and says it is set there', function () {
    $lock = (new SettingLocks(lockSource(['brand.company_name' => 'Acme']), lockSource(['brand.company_name' => 'Net'])))
        ->lockOf('brand.company_name');

    expect($lock?->by)->toBe(SettingLock::BY_DOTENV)
        ->and($lock?->value)->toBe('Acme')
        ->and($lock?->label())->toBe('Set in the site\'s .env file');
});

it('answers the network value when .env does not hold the key', function () {
    $lock = (new SettingLocks(lockSource([]), lockSource(['brand.tagline' => 'Net'])))->lockOf('brand.tagline');

    expect($lock?->by)->toBe(SettingLock::BY_NETWORK)
        ->and($lock?->value)->toBe('Net')
        ->and($lock?->label())->toBe('Locked by the network administrator');
});

it('answers nothing for a key neither layer holds', function () {
    expect((new SettingLocks(lockSource(['other' => 'x']), lockSource([])))->lockOf('brand.tagline'))->toBeNull();
});

it('reads a switch set in .env as on or off', function (mixed $value, string $text) {
    expect((new SettingLocks(lockSource(['media.webp.enabled' => $value]), lockSource([])))->lockOf('media.webp.enabled')?->value)
        ->toBe($text);
})->with([
    'true'   => [true, '1'],
    'false'  => [false, '0'],
    'string' => ['1', '1'],
]);
