<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class DataExportRequest
{
    public const SCOPE_FILTERED = 'filtered';
    public const SCOPE_SELECTED = 'selected';
    public const SCOPE_ALL = 'all';

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->sourceKey = (string) ($payload['source_key'] ?? '');
        $this->scope = (string) ($payload['scope'] ?? '');
        $this->selectedIds = self::ids($payload['selected_ids'] ?? []);
        $this->query = is_array($payload['query'] ?? null) ? $payload['query'] : [];
        $this->columns = array_values(array_unique(array_map('strval', (array) ($payload['columns'] ?? []))));
        $this->format = strtolower((string) ($payload['format'] ?? 'csv'));
        $this->personalDataAcknowledged = (bool) ($payload['personal_data_acknowledged'] ?? false);
        $this->validate();
    }

    public int $actorId;
    public string $sourceKey;
    public string $scope;
    /** @var list<int> */ public array $selectedIds;
    /** @var array<string,mixed> */ public array $query;
    /** @var list<string> */ public array $columns;
    public string $format;
    public bool $personalDataAcknowledged;

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        return new self($payload);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'actor_id' => $this->actorId,
            'source_key' => $this->sourceKey,
            'scope' => $this->scope,
            'selected_ids' => $this->selectedIds,
            'query' => $this->query,
            'columns' => $this->columns,
            'format' => $this->format,
            'personal_data_acknowledged' => $this->personalDataAcknowledged,
        ];
    }

    private function validate(): void
    {
        if ($this->actorId < 1 || preg_match('/^[a-z][a-z0-9-]*$/', $this->sourceKey) !== 1) {
            throw new InvalidArgumentException('The data export actor or source is invalid.');
        }
        if (! in_array($this->scope, [self::SCOPE_FILTERED, self::SCOPE_SELECTED, self::SCOPE_ALL], true)
            || ! in_array($this->format, ['csv', 'xlsx'], true) || $this->columns === []) {
            throw new InvalidArgumentException('The data export scope, format, or columns are invalid.');
        }
        if (($this->scope === self::SCOPE_SELECTED) !== ($this->selectedIds !== [])) {
            throw new InvalidArgumentException('Selected data exports require exact record IDs.');
        }
    }

    /** @return list<int> */
    private static function ids(mixed $ids): array
    {
        if (! is_array($ids)) {
            throw new InvalidArgumentException('Data export record IDs are invalid.');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (array_filter($ids, static fn (int $id): bool => $id < 1) !== [] || count($ids) > 500) {
            throw new InvalidArgumentException('Data export record IDs are invalid or exceed 500.');
        }
        sort($ids);

        return $ids;
    }
}
