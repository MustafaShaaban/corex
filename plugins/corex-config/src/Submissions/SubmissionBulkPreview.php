<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Actor-bound, expiring snapshot of the exact records a bulk action will mutate.
 */
final readonly class SubmissionBulkPreview
{
    public string $token;
    public int $actorId;
    public string $action;
    /** @var list<int> */
    public array $submissionIds;
    /** @var array<int,string> */
    public array $expectedVersions;
    /** @var array<string,mixed> */
    public array $parameters;
    public int $expiresAt;

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->token = (string) ($payload['token'] ?? '');
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->action = (string) ($payload['action'] ?? '');
        $this->parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $this->expiresAt = (int) ($payload['expires_at'] ?? 0);
        [$this->submissionIds, $this->expectedVersions] = $this->records($payload['records'] ?? []);

        if ($this->token === '' || $this->actorId < 1 || $this->submissionIds === [] || $this->expiresAt < 1) {
            throw new InvalidArgumentException('The submission bulk preview is invalid.');
        }
    }

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        return new self($payload);
    }

    public function count(): int
    {
        return count($this->submissionIds);
    }

    public function expired(int $timestamp): bool
    {
        return $timestamp >= $this->expiresAt;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $records = [];
        foreach ($this->submissionIds as $id) {
            $records[] = ['id' => $id, 'updated_at' => $this->expectedVersions[$id]];
        }

        return [
            'token' => $this->token,
            'actor_id' => $this->actorId,
            'action' => $this->action,
            'records' => $records,
            'parameters' => $this->parameters,
            'expires_at' => $this->expiresAt,
        ];
    }

    /** @return array{list<int>,array<int,string>} */
    private function records(mixed $records): array
    {
        $ids = [];
        $versions = [];
        foreach (is_array($records) ? $records : [] as $record) {
            if (! is_array($record) || (int) ($record['id'] ?? 0) < 1) {
                continue;
            }
            $id = (int) $record['id'];
            $ids[] = $id;
            $versions[$id] = (string) ($record['updated_at'] ?? '');
        }

        return [array_values(array_unique($ids)), $versions];
    }
}
