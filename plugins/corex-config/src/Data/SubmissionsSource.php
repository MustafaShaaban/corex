<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * The reference DataSource: stored form submissions (`corex_submission` posts). Shapes each
 * record into id/date/form/summary; the WP_Query + meta access lives in the injected reader
 * so this shaping is unit-tested headlessly (spec 030).
 */
final class SubmissionsSource implements DataSource
{
    public function __construct(private readonly SubmissionsReader $reader)
    {
    }

    public function key(): string
    {
        return 'submissions';
    }

    public function label(): string
    {
        return __('Form submissions', 'corex');
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'date', 'label' => __('Date', 'corex')],
            ['id' => 'form', 'label' => __('Form', 'corex')],
            ['id' => 'summary', 'label' => __('Submission', 'corex')],
        ];
    }

    /**
     * @return list<array<string,scalar>>
     */
    public function rows(int $page, int $perPage): array
    {
        return array_map(
            fn (array $record): array => [
                'id'      => $record['id'],
                'date'    => $record['date'],
                'form'    => $record['form'],
                'summary' => $this->summarize($record['fields']),
            ],
            $this->reader->page(max(1, $page), max(1, $perPage)),
        );
    }

    public function total(): int
    {
        return $this->reader->total();
    }

    public function delete(int $id): bool
    {
        return $this->reader->trash($id);
    }

    /**
     * A compact, plain-text "key: value · …" summary of a submission's fields.
     *
     * @param array<string,mixed> $fields
     */
    private function summarize(array $fields): string
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            $parts[] = $name . ': ' . (is_scalar($value) ? (string) $value : (string) wp_json_encode($value));
        }

        return implode(' · ', $parts);
    }
}
