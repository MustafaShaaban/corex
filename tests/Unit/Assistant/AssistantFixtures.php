<?php

/**
 * Fakes the CoreX Assistant's tests share (spec 129): settings over arrays of Config values and options,
 * cooldowns and limits over arrays the test reads back, the catalogue's services, and a model's answer.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

namespace Corex\Tests\Unit\Assistant;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\Provider;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Support\Config\ConfigInterface;

final class AssistantFixtures
{
    /**
     * @param array<string, mixed> $values  Config dot-keys => values
     * @param array<string, mixed> $options option names => values
     */
    public static function settings(array $values, array $options = [], string $siteZone = 'UTC'): AssistantSettings
    {
        return new AssistantSettings(
            new class ($values) implements ConfigInterface {
                /** @param array<string, mixed> $values */
                public function __construct(private readonly array $values)
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
            },
            static fn (string $name, mixed $default): mixed => $options[$name] ?? $default,
            $siteZone,
        );
    }

    /** @param array<string, mixed> $store */
    public static function cooldowns(array &$store, int $now = 1_000_000): Cooldowns
    {
        return new Cooldowns(
            static function () use (&$store): array {
                return $store;
            },
            static function (array $entries) use (&$store): void {
                $store = $entries;
            },
            static fn (): int => $now,
        );
    }

    /**
     * Limits over an array the test reads back, at a moment the test may move.
     *
     * @param array<string, int>   $store
     * @param array<string, int>   $caps  any of hour, day, site, paid
     */
    public static function limits(array &$store, array $caps = [], ?int &$now = null): VisitorLimits
    {
        $now ??= 86400 * 100 + 7200;

        return new VisitorLimits(
            static function (string $key) use (&$store): mixed {
                return $store[$key] ?? false;
            },
            static function (string $key, int $value) use (&$store): void {
                $store[$key] = $value;
            },
            static function () use (&$now): int {
                return $now;
            },
            static fn (): array => $caps + AssistantSettings::DEFAULT_LIMITS,
        );
    }

    public static function provider(string $id): Provider
    {
        return Provider::fromCatalogue($id, 'key-' . $id);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public static function answer(string $text): array
    {
        return ['status' => 200, 'headers' => [], 'body' => (string) json_encode(['choices' => [['message' => ['content' => $text]]]])];
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public static function claudeAnswer(string $text, string $stop = 'end_turn'): array
    {
        return ['status' => 200, 'headers' => [], 'body' => (string) json_encode([
            'content' => [['type' => 'thinking', 'thinking' => '...'], ['type' => 'text', 'text' => $text]],
            'stop_reason' => $stop,
        ])];
    }
}
