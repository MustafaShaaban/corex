<?php

/**
 * Unit tests for where a Guides support message goes (spec 087, FR-002 / FR-003).
 *
 * The contract under test is the precedence: a site owner's saved address beats the address the
 * add-on ships with, a site that has never opened Settings still has a working one, and a stored
 * value that is not deliverable is treated as no address rather than as an address.
 *
 * @package Corex\Tests\Unit\Guides
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Guides\Support\SupportSettings;
use Corex\Support\Config\ConfigInterface;

/**
 * A Config double whose only job is to answer with, or without, a stored value — which is exactly
 * the distinction {@see SupportSettings} exists to resolve.
 *
 * @param array<string,mixed> $stored
 */
function guidesConfig(array $stored = []): ConfigInterface
{
    return new class ($stored) implements ConfigInterface {
        /** @param array<string,mixed> $stored */
        public function __construct(private readonly array $stored)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->stored[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->stored);
        }
    };
}

beforeEach(function () {
    Functions\when('is_email')->alias(
        static fn (string $value): string|false => filter_var($value, FILTER_VALIDATE_EMAIL) ?: false,
    );
});

it('falls back to the address the add-on ships with', function () {
    $settings = new SupportSettings(guidesConfig());

    expect($settings->recipient())->toBe('Mustafashaaban22@gmail.com')
        ->and($settings->enabled())->toBeTrue()
        ->and($settings->configured())->toBeTrue();
});

it('prefers a saved address over the shipped one', function () {
    $settings = new SupportSettings(guidesConfig([
        SupportSettings::EMAIL_KEY => 'ops@example.test',
    ]));

    expect($settings->recipient())->toBe('ops@example.test');
});

/**
 * The failure this guards against is quiet: a typo'd address is accepted, the form reports success,
 * and the message is dropped by the transport with nobody to tell. Treating it as no address means
 * the panel says support is not set up — which is true, and actionable.
 */
it('treats an undeliverable stored address as no address at all', function () {
    $settings = new SupportSettings(guidesConfig([
        SupportSettings::EMAIL_KEY => 'ops-at-example-dot-test',
    ]));

    expect($settings->recipient())->toBe('')
        ->and($settings->configured())->toBeFalse();
});

it('trims an address that was pasted with whitespace', function () {
    $settings = new SupportSettings(guidesConfig([
        SupportSettings::EMAIL_KEY => "  ops@example.test\n",
    ]));

    expect($settings->recipient())->toBe('ops@example.test');
});

/**
 * The options layer stores a checkbox as '1' or '', never as a boolean, so a naive cast would make
 * every unticked box true and the switch would not switch anything off.
 */
it('reads the enable switch as the options layer actually stores it', function (mixed $stored, bool $expected) {
    $settings = new SupportSettings(guidesConfig([SupportSettings::ENABLED_KEY => $stored]));

    expect($settings->enabled())->toBe($expected);
})->with([
    'ticked, as an option'   => ['1', true],
    'unticked, as an option' => ['', false],
    'literal zero string'    => ['0', false],
    'real boolean false'     => [false, false],
    'real boolean true'      => [true, true],
]);

it('is not configured when the form is switched off, however good the address is', function () {
    $settings = new SupportSettings(guidesConfig([
        SupportSettings::EMAIL_KEY   => 'ops@example.test',
        SupportSettings::ENABLED_KEY => '',
    ]));

    expect($settings->recipient())->toBe('ops@example.test')
        ->and($settings->configured())->toBeFalse();
});
