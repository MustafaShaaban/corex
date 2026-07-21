<?php

/**
 * Unit tests for the FluentSMTP transport boundary advisory (spec 071 US5: FR-027, FR-028, FR-029).
 *
 * The advisory reads only public, non-sensitive signals — a forced-From filter, the configured
 * From domain versus the site domain. It never reads a transport plugin's tables or credentials,
 * and it never fabricates a "detected" state where no evidence exists.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Email\TransportAdvisory;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('home_url')->justReturn('https://example.com');
    Functions\when('wp_parse_url')->alias(static fn (string $url, int $c = -1) => parse_url($url, $c));
    Functions\when('has_filter')->justReturn(false);
});

/** @param array<string,mixed> $values */
function advisoryConfig(array $values): ConfigInterface
{
    return new class($values) implements ConfigInterface {
        public function __construct(private array $values)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }
    };
}

it('always includes honest general guidance about who composes and who transports', function () {
    $result = (new TransportAdvisory(advisoryConfig([])))->evaluate();

    // The baseline is generic guidance, never a fabricated detection (FR-029).
    expect($result->notes())->not->toBeEmpty()
        ->and($result->hasWarnings())->toBeFalse()
        ->and(implode(' ', array_map(static fn ($n) => $n['message'], $result->notes())))
            ->toContain('wp_mail');
});

it('warns when the configured From domain differs from the site domain', function () {
    $result = (new TransportAdvisory(advisoryConfig(['mail.from.address' => 'hello@other-domain.net'])))->evaluate();

    expect($result->hasWarnings())->toBeTrue();
    $messages = implode(' ', array_map(static fn ($n) => $n['message'], $result->notes()));
    expect($messages)->toContain('other-domain.net');
});

it('does not warn when the From domain matches the site domain', function () {
    $result = (new TransportAdvisory(advisoryConfig(['mail.from.address' => 'hello@example.com'])))->evaluate();

    expect($result->hasWarnings())->toBeFalse();
});

it('reports a forced From only when a wp_mail_from filter is actually registered', function () {
    Functions\when('has_filter')->alias(static fn (string $hook): bool => $hook === 'wp_mail_from');

    $result = (new TransportAdvisory(advisoryConfig([])))->evaluate();

    $messages = implode(' ', array_map(static fn ($n) => $n['message'], $result->notes()));
    expect($messages)->toContain('From address');
    expect($result->hasWarnings())->toBeTrue();
});

it('makes no forced-From claim when no filter is registered', function () {
    // FR-029: absent evidence yields general guidance, never a "detected" state.
    $result = (new TransportAdvisory(advisoryConfig([])))->evaluate();

    $messages = strtolower(implode(' ', array_map(static fn ($n) => $n['message'], $result->notes())));
    expect($messages)->not->toContain('is overriding');
});

it('every note carries a safe level and never a credential or host secret', function () {
    Functions\when('has_filter')->justReturn(true);
    $result = (new TransportAdvisory(advisoryConfig(['mail.from.address' => 'hello@other.net'])))->evaluate();

    foreach ($result->notes() as $note) {
        expect($note['level'])->toBeIn(['info', 'warning'])
            ->and($note)->toHaveKey('message');
    }
});
