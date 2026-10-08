<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Closure;
use Corex\Config\Export\ExportAttribution;
use Corex\Data\DataField;
use DateTimeZone;

/**
 * What a document says about a Data export before its records (spec 103, FR-019): what was
 * exported and under which filters, how many, and who exported it and when.
 */
final readonly class DataExportAbout
{
    /**
     * @param Closure(int):string    $nameOf       A person's name by their id; '' when they cannot be named.
     * @param Closure():DateTimeZone $siteTimezone
     */
    public function __construct(private Closure $nameOf, private Closure $siteTimezone)
    {
    }

    /**
     * @param list<DataField> $fields Every field the source declares, for the label a filter is shown under.
     *
     * @return list<array{0:string,1:string}> A label and what it says, in reading order.
     */
    public function facts(DataExportRun $run, array $fields): array
    {
        return [
            [__('What was exported', 'corex'), $this->scope($run, $fields)],
            [__('Records', 'corex'), (string) ($run->exportedRows ?: $run->recordCount)],
            [__('Exported by', 'corex'), ExportAttribution::line(
                ($this->nameOf)($run->actorId),
                $run->createdAt->setTimezone(($this->siteTimezone)()),
            )],
        ];
    }

    /** @param list<DataField> $fields */
    private function scope(DataExportRun $run, array $fields): string
    {
        if ($run->scope === DataExportRequest::SCOPE_SELECTED) {
            return sprintf(
                /* translators: %d: how many records were ticked. */
                _n('%d selected record', '%d selected records', count($run->selectedIds), 'corex'),
                count($run->selectedIds),
            );
        }
        if ($run->scope !== DataExportRequest::SCOPE_FILTERED) {
            return __('Every record the person exporting could see', 'corex');
        }

        $parts = $this->filters($run->query, $fields);

        return $parts === []
            ? __('Every record in view, with no filter on', 'corex')
            : implode('; ', $parts);
    }

    /**
     * @param array<string,mixed> $query
     * @param list<DataField>     $fields
     *
     * @return list<string>
     */
    private function filters(array $query, array $fields): array
    {
        $labels = [];
        foreach ($fields as $field) {
            $labels[$field->key] = $field->label;
        }

        $parts  = [];
        $search = (string) ($query['search'] ?? '');
        if ($search !== '') {
            $parts[] = __('Search', 'corex') . ': ' . $search;
        }
        foreach ((array) ($query['filters'] ?? []) as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $parts[] = ($labels[$key] ?? (string) $key) . ': ' . $value;
            }
        }

        return $parts;
    }
}
