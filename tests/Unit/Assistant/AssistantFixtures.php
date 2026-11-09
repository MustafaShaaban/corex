<?php

/**
 * Fakes the CoreX Assistant's tests share (spec 129): settings over an array of Config values,
 * cooldowns and the paid-answer count over arrays the test reads back, a router over them, the
 * catalogue's services, and a model's answer in each format.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

namespace Corex\Tests\Unit\Assistant;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\ChatRouter;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\ModelClient;
use Corex\Assistant\Chain\Provider;
use Corex\Assistant\Limits\PaidAnswers;
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

    /** @param array<string, int> $store transient key => count */
    public static function paid(array &$store, int $cap = 50, int $now = 1_000_000): PaidAnswers
    {
        return new PaidAnswers(
            static function (string $key) use (&$store): mixed {
                return $store[$key] ?? false;
            },
            static function (string $key, int $value) use (&$store): void {
                $store[$key] = $value;
            },
            static fn (): int => $now,
            static fn (): int => $cap,
        );
    }

    /**
     * @param array<string, mixed> $store the cooldowns
     * @param \Closure(): int      $clock
     */
    public static function router(ModelClient $client, array &$store, \Closure $clock, ?PaidAnswers $paid = null): ChatRouter
    {
        $counts = [];

        return new ChatRouter($client, self::cooldowns($store), $clock, $paid ?? self::paid($counts));
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

    /**
     * A Messages API answer: content blocks, a thinking block first as Claude may send one.
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function messagesAnswer(string $text, string $stop = 'end_turn'): array
    {
        $content = [['type' => 'thinking', 'thinking' => '', 'signature' => 's']];
        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        return ['status' => 200, 'headers' => [], 'body' => (string) json_encode(['content' => $content, 'stop_reason' => $stop])];
    }
}
