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
    /** It could not be read when its turn came. */
    public const BECAUSE_UNREADABLE = 'unreadable';

    /** It belongs to a screen the person may not open, so it was never read (spec 112, FR-030). */
    public const BECAUSE_RESTRICTED = 'restricted';

    /**
     * @param list<string> $columns             The keys of the fields exported, in the file's order.
     * @param list<string> $personalDataClasses The kinds of personal data among those columns.
     * @param bool         $leftOut             True when the model was not written: it could not be
     *                                          read when its turn came (its add-on was switched off,
     *                                          the person lost the right to export it), or it was
     *                                          never read at all.
     * @param string|null  $leftOutBecause      One of the `BECAUSE_*`, for a part left out.
     * @param string       $label               The model's name, kept for a part never read.
     */
    public function __construct(
        public string $sourceKey,
        public array $columns,
        public int $recordCount,
        public array $personalDataClasses,
        public bool $leftOut = false,
        public ?string $leftOutBecause = null,
        public string $label = '',
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $this->sourceKey) !== 1 || ($this->columns === [] && ! $this->leftOut) || $this->recordCount < 0) {
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
            isset($payload['left_out_because']) ? (string) $payload['left_out_because'] : null,
            (string) ($payload['label'] ?? ''),
        );
    }

    /** A model of an export of several that belongs to a screen the person may not open. */
    public static function restricted(string $sourceKey, string $label): self
    {
        return new self($sourceKey, [], 0, [], true, self::BECAUSE_RESTRICTED, $label);
    }

    public function leftOut(): self
    {
        return new self($this->sourceKey, $this->columns, $this->recordCount, $this->personalDataClasses, true, self::BECAUSE_UNREADABLE, $this->label);
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
            'left_out_because' => $this->leftOutBecause,
            'label' => $this->label,
        ];
    }
}
