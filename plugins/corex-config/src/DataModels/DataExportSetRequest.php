<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Config\Export\ExportWriters;
use InvalidArgumentException;

/**
 * A request for one export of several models (spec 109, slice 2).
 *
 * It takes every record of each model. Ticked rows and filters belong to one model's table, and
 * stay with the request for one model ({@see DataExportRequest}).
 */
final readonly class DataExportSetRequest
{
    /** More models than a site has; a request naming more is a mistake, not an export. */
    private const MOST_MODELS = 50;

    /**
     * @param list<array{source:string,columns:list<string>|null}> $parts Each model, with the
     *        columns asked for; null for every column the person may export.
     */
    private function __construct(
        public int $actorId,
        public array $parts,
        public string $format,
        public string $separator,
        public bool $personalDataAcknowledged,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        $request = new self(
            (int) ($payload['actor_id'] ?? 0),
            self::parts($payload['parts'] ?? []),
            strtolower((string) ($payload['format'] ?? ExportWriters::CSV)),
            (string) (($payload['separator'] ?? '') ?: ExportWriters::DEFAULT_SEPARATOR),
            (bool) ($payload['personal_data_acknowledged'] ?? false),
        );
        $request->validate();

        return $request;
    }

    /** @return list<string> */
    public function sourceKeys(): array
    {
        return array_column($this->parts, 'source');
    }

    private function validate(): void
    {
        if ($this->actorId < 1) {
            throw new InvalidArgumentException('The data export actor is invalid.');
        }
        if ($this->parts === [] || count($this->parts) > self::MOST_MODELS) {
            throw new InvalidArgumentException('A data export names at least one model.');
        }
        $keys = $this->sourceKeys();
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('A data export names a model twice.');
        }
        if (! in_array($this->format, ExportWriters::FORMATS, true) || ! isset(ExportWriters::SEPARATORS[$this->separator])) {
            throw new InvalidArgumentException('The data export format or separator is invalid.');
        }
    }

    /** @return list<array{source:string,columns:list<string>|null}> */
    private static function parts(mixed $parts): array
    {
        $clean = [];
        foreach (is_array($parts) ? $parts : [] as $part) {
            $source = is_array($part) ? (string) ($part['source'] ?? '') : '';
            if (preg_match('/^[a-z][a-z0-9-]*$/', $source) !== 1) {
                throw new InvalidArgumentException('A data export names a model that is not one.');
            }
            $columns = is_array($part['columns'] ?? null)
                ? array_values(array_unique(array_map('strval', $part['columns'])))
                : null;
            $clean[] = ['source' => $source, 'columns' => $columns === [] ? null : $columns];
        }

        return $clean;
    }
}
