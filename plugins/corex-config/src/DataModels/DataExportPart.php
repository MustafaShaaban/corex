<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * One model of an export: which source, which of its columns, and how many records were counted
 * when the export was asked for (spec 109, slice 2).
 */
final readonly class DataExportPart
{
    /**
     * @param list<string> $columns             The keys of the fields exported, in the file's order.
     * @param list<string> $personalDataClasses The kinds of personal data among those columns.
     * @param bool         $leftOut             True when the model could not be read when its turn
     *                                          came: its add-on was switched off, or the person
     *                                          lost the right to export it.
     */
    public function __construct(
        public string $sourceKey,
        public array $columns,
        public int $recordCount,
        public array $personalDataClasses,
        public bool $leftOut = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $this->sourceKey) !== 1 || $this->columns === [] || $this->recordCount < 0) {
            throw new InvalidArgumentException('A data export part needs a source, columns and a count.');
        }
    }

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        return new self(
            (string) ($payload['source_key'] ?? ''),
            array_values(array_map('strval', (array) ($payload['columns'] ?? []))),
            (int) ($payload['record_count'] ?? 0),
            array_values(array_map('strval', (array) ($payload['personal_data_classes'] ?? []))),
            (bool) ($payload['left_out'] ?? false),
        );
    }

    public function leftOut(): self
    {
        return new self($this->sourceKey, $this->columns, $this->recordCount, $this->personalDataClasses, true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'columns' => $this->columns,
            'record_count' => $this->recordCount,
            'personal_data_classes' => $this->personalDataClasses,
            'left_out' => $this->leftOut,
        ];
    }
}
