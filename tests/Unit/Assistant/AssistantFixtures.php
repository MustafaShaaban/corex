<?php

/**
 * Fakes the CoreX Assistant's tests share (spec 129): settings over an array of Config values,
 * cooldowns over an array the test reads back, the catalogue's services, and a model's answer.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

namespace Corex\Tests\Unit\Assistant;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\Provider;
use Corex\Support\Config\ConfigInterface;

final class AssistantFixtures
{
    /** @param array<string, mixed> $values Config dot-keys => values */
    public static function settings(array $values): AssistantSettings
    {
        return new AssistantSettings(new class ($values) implements ConfigInterface {
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
        });
    }

    /** @param array<string, int> $store */
    public static function cooldowns(array &$store, int $now = 1_000_000): Cooldowns
    {
        return new Cooldowns(
            static function () use (&$store): array {
                return $store;
            },
            static function (array $until) use (&$store): void {
                $store = $until;
            },
            static fn (): int => $now,
        );
    }

    public static function provider(string $id): Provider
    {
        $entry = Provider::CATALOGUE[$id];

        return new Provider($id, $entry['label'], $entry['base'], $entry['model'], 'key-' . $id, $entry['extra']);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public static function answer(string $text): array
    {
        return ['status' => 200, 'headers' => [], 'body' => (string) json_encode(['choices' => [['message' => ['content' => $text]]]])];
    }
}
