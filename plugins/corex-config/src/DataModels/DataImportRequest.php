<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class DataImportRequest
{
    public const UNKNOWN_REJECT = 'reject';
    public const UNKNOWN_IGNORE = 'ignore';

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->sourceKey = (string) ($payload['source_key'] ?? '');
        $this->header = self::strings($payload['header'] ?? []);
        $this->rows = self::rows($payload['rows'] ?? []);
        $this->mapping = self::mapping($payload['mapping'] ?? []);
        $this->unknownPolicy = (string) ($payload['unknown_policy'] ?? self::UNKNOWN_REJECT);
        $this->fileName = basename((string) ($payload['file_name'] ?? 'import.csv'));
        $this->validate();
    }

    public int $actorId;
    public string $sourceKey;
    /** @var list<string> */
    public array $header;
    /** @var list<list<string>> */
    public array $rows;
    /** @var array<string,string> */
    public array $mapping;
    public string $unknownPolicy;
    public string $fileName;

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
            'header' => $this->header,
            'rows' => $this->rows,
            'mapping' => $this->mapping,
            'unknown_policy' => $this->unknownPolicy,
            'file_name' => $this->fileName,
        ];
    }

    private function validate(): void
    {
        if ($this->actorId < 1 || preg_match('/^[a-z][a-z0-9-]*$/', $this->sourceKey) !== 1) {
            throw new InvalidArgumentException('The import actor or source is invalid.');
        }
        if ($this->header === [] || count($this->header) > 200 || count($this->rows) > 5000) {
            throw new InvalidArgumentException('The import dimensions are invalid.');
        }
        if (count(array_unique($this->header)) !== count($this->header)) {
            throw new InvalidArgumentException('The import header contains duplicate columns.');
        }
        if (! in_array($this->unknownPolicy, [self::UNKNOWN_REJECT, self::UNKNOWN_IGNORE], true)) {
            throw new InvalidArgumentException('The import unknown-column policy is invalid.');
        }
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException('The import header is invalid.');
        }

        return array_values(array_map(static fn (mixed $value): string => trim((string) $value), $values));
    }

    /** @return list<list<string>> */
    private static function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            throw new InvalidArgumentException('The import rows are invalid.');
        }

        return array_values(array_map(static function (mixed $row): array {
            if (! is_array($row)) {
                throw new InvalidArgumentException('An import row is invalid.');
            }

            return array_values(array_map('strval', $row));
        }, $rows));
    }

    /** @return array<string,string> */
    private static function mapping(mixed $mapping): array
    {
        if (! is_array($mapping)) {
            throw new InvalidArgumentException('The import mapping is invalid.');
        }
        $result = [];
        foreach ($mapping as $column => $field) {
            $result[trim((string) $column)] = trim((string) $field);
        }

        return $result;
    }
}
