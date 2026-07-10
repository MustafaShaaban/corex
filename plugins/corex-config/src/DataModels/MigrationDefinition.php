<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class MigrationDefinition
{
    /** @param list<string> $plan */
    public function __construct(
        public string $key,
        public string $version,
        public string $description,
        public array $plan,
        public bool $transactional,
        public bool $rollbackSupported,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $key) !== 1 || $version === '' || $description === '' || $plan === []) {
            throw new InvalidArgumentException('Migration definition is invalid.');
        }
        foreach ($plan as $step) {
            if (! is_string($step) || trim($step) === '') {
                throw new InvalidArgumentException('Migration plan steps must be non-empty strings.');
            }
        }
    }

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        return new self(
            (string) ($payload['key'] ?? ''), (string) ($payload['version'] ?? ''),
            (string) ($payload['description'] ?? ''), array_values((array) ($payload['plan'] ?? [])),
            (bool) ($payload['transactional'] ?? false), (bool) ($payload['rollback_supported'] ?? false),
        );
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key, 'version' => $this->version, 'description' => $this->description,
            'plan' => $this->plan, 'transactional' => $this->transactional,
            'rollback_supported' => $this->rollbackSupported,
        ];
    }
}
