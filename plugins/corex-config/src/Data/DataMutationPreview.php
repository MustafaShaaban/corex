<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class DataMutationPreview
{
    /**
     * @param list<int|string>    $recordIds
     * @param array<string,mixed> $values
     */
    private function __construct(
        public string $token,
        public int $actorId,
        public string $sourceKey,
        public string $operation,
        public array $recordIds,
        public array $values,
        public int $expiresAt,
    ) {
        if ($this->token === '' || $this->expiresAt < 1) {
            throw new InvalidArgumentException('The data mutation preview is invalid.');
        }
    }

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        $request = DataMutationRequest::from($payload);

        return new self(
            token: (string) ($payload['token'] ?? ''),
            actorId: $request->actorId,
            sourceKey: $request->sourceKey,
            operation: $request->operation,
            recordIds: $request->recordIds,
            values: $request->values,
            expiresAt: (int) ($payload['expires_at'] ?? 0),
        );
    }

    public function expired(int $timestamp): bool
    {
        return $timestamp >= $this->expiresAt;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'actor_id' => $this->actorId,
            'source_key' => $this->sourceKey,
            'operation' => $this->operation,
            'record_ids' => $this->recordIds,
            'values' => $this->values,
            'expires_at' => $this->expiresAt,
        ];
    }
}
