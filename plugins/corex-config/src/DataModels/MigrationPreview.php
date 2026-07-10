<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class MigrationPreview
{
    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->token = (string) ($payload['token'] ?? '');
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->action = (string) ($payload['action'] ?? '');
        $this->sourceKey = (string) ($payload['source_key'] ?? '');
        $this->definition = MigrationDefinition::from((array) ($payload['definition'] ?? []));
        $this->runId = (int) ($payload['run_id'] ?? 0);
        $this->expiresAt = (int) ($payload['expires_at'] ?? 0);
        $this->productionWarning = true;
        if ($this->token === '' || $this->actorId < 1 || $this->expiresAt < 1
            || ! in_array($this->action, [MigrationRun::ACTION_APPLY, MigrationRun::ACTION_ROLLBACK], true)) {
            throw new InvalidArgumentException('Migration preview is invalid.');
        }
    }

    public string $token;
    public int $actorId;
    public string $action;
    public string $sourceKey;
    public MigrationDefinition $definition;
    public int $runId;
    public int $expiresAt;
    public bool $productionWarning;

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self { return new self($payload); }
    public function expired(int $now): bool { return $now >= $this->expiresAt; }
    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'token' => $this->token, 'actor_id' => $this->actorId, 'action' => $this->action,
            'source_key' => $this->sourceKey, 'definition' => $this->definition->toArray(),
            'run_id' => $this->runId, 'expires_at' => $this->expiresAt,
        ];
    }
}
