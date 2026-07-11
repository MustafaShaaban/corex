<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Data\DataSourceCapabilities;
use InvalidArgumentException;

final readonly class DataMutationRequest
{
    private const OPERATIONS = [
        DataSourceCapabilities::CREATE,
        DataSourceCapabilities::UPDATE,
        DataSourceCapabilities::DELETE,
        DataSourceCapabilities::BULK_UPDATE,
        DataSourceCapabilities::BULK_DELETE,
    ];

    /**
     * @param list<int|string>   $recordIds
     * @param array<string,mixed> $values
     */
    private function __construct(
        public int $actorId,
        public string $sourceKey,
        public string $operation,
        public array $recordIds,
        public array $values,
    ) {
        if ($this->actorId < 1 || preg_match('/^[a-z][a-z0-9-]*$/', $this->sourceKey) !== 1) {
            throw new InvalidArgumentException('The data mutation actor or source is invalid.');
        }
        if (! in_array($this->operation, self::OPERATIONS, true)) {
            throw new InvalidArgumentException('The data mutation operation is invalid.');
        }
    }

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        $values = $payload['values'] ?? [];
        if (! is_array($values)) {
            throw new InvalidArgumentException('Data mutation values must be an object.');
        }

        return new self(
            actorId: (int) ($payload['actor_id'] ?? 0),
            sourceKey: (string) ($payload['source_key'] ?? ''),
            operation: (string) ($payload['operation'] ?? ''),
            recordIds: self::ids($payload['record_ids'] ?? []),
            values: $values,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'actor_id' => $this->actorId,
            'source_key' => $this->sourceKey,
            'operation' => $this->operation,
            'record_ids' => $this->recordIds,
            'values' => $this->values,
        ];
    }

    /** @return list<int|string> */
    private static function ids(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new InvalidArgumentException('Data mutation record IDs must be a list.');
        }
        $ids = [];
        foreach ($raw as $id) {
            if ((! is_int($id) && ! is_string($id)) || $id === '' || $id === 0) {
                throw new InvalidArgumentException('Data mutation record IDs are invalid.');
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids, SORT_REGULAR));
        sort($ids, SORT_REGULAR);

        return $ids;
    }
}
