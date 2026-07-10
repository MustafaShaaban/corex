<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Success;

defined('ABSPATH') || exit;

use Closure;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Normalizes built-in and registered visitor success states.
 */
final class SuccessStateRegistry
{
    /** @var array<string,array{label:string,normalizer:Closure}> */
    private array $states = [];

    public function __construct()
    {
        $this->register('inline', 'inline', static fn (array $config): array => [
            'type' => 'inline',
            'message' => (string) ($config['message'] ?? ''),
        ]);
        $this->register('page', 'page', static fn (array $config): array => [
            'type' => 'page',
            'page_id' => (int) ($config['page_id'] ?? 0),
        ]);
        $this->register('url', 'url', static fn (array $config): array => [
            'type' => 'url',
            'url' => (string) ($config['url'] ?? ''),
        ]);
    }

    public function register(string $key, string $label, callable $normalizer): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key) || isset($this->states[$key])) {
            throw new InvalidArgumentException(sprintf('Invalid or duplicate success state: "%s".', $key));
        }

        $this->states[$key] = ['label' => $label, 'normalizer' => Closure::fromCallable($normalizer)];
    }

    /**
     * @param array<string,mixed> $configuration
     * @return array<string,mixed>
     */
    public function normalize(string $key, array $configuration): array
    {
        if (! isset($this->states[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown success state: "%s".', $key));
        }

        $normalized = ($this->states[$key]['normalizer'])($configuration);
        if (! is_array($normalized)) {
            throw new UnexpectedValueException('Success state normalizers must return an array.');
        }

        return $normalized;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->states);
    }

    public function has(string $key): bool
    {
        return isset($this->states[$key]);
    }
}
