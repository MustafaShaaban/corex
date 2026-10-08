<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Closure;
use Corex\Config\Export\ExportAttribution;
use DateTimeZone;

/**
 * What a document says about a submissions export before its records (spec 103, FR-019): its
 * title, what was exported and under which filters, how many, and who exported it and when.
 */
final readonly class SubmissionExportAbout
{
    /** @param Closure():DateTimeZone $siteTimezone */
    public function __construct(private SubmissionOwnerNames $names, private Closure $siteTimezone)
    {
    }

    /**
     * @param array<string,string> $forms The forms the export holds: slug => name.
     */
    public function title(array $forms): string
    {
        return count($forms) === 1
            /* translators: %s: a form's name. */
            ? sprintf(__('%s submissions', 'corex'), (string) reset($forms))
            : __('Submissions', 'corex');
    }

    /**
     * @param array<string,string> $forms The forms the export holds: slug => name.
     *
     * @return list<array{0:string,1:string}> A label and what it says, in reading order.
     */
    public function facts(SubmissionExportRun $run, array $forms): array
    {
        return [
            [__('What was exported', 'corex'), $this->scope($run, $forms)],
            [__('Records', 'corex'), (string) $run->recordCount],
            [__('Exported by', 'corex'), ExportAttribution::line(
                $this->names->nameOf('user', (string) $run->actorId),
                $run->createdAt->setTimezone(($this->siteTimezone)()),
            )],
        ];
    }

    /** @param array<string,string> $forms */
    private function scope(SubmissionExportRun $run, array $forms): string
    {
        $words = match ($run->scope) {
            'selected' => sprintf(
                /* translators: %d: how many submissions were ticked. */
                _n('%d selected submission', '%d selected submissions', count($run->selectedIds), 'corex'),
                count($run->selectedIds),
            ),
            'filtered' => $this->filters($run->query, $forms),
            default => __('Every submission the person exporting could see', 'corex'),
        };

        return $run->includeTest
            /* translators: %s: what an export covered. */
            ? sprintf(__('%s, with submissions marked as tests', 'corex'), $words)
            : $words;
    }

    /**
     * The filters an export was made under, in words.
     *
     * @param array<string,mixed>  $query
     * @param array<string,string> $forms
     */
    private function filters(array $query, array $forms): string
    {
        $parts = array_filter([
            $this->form((string) ($query['flow'] ?? ''), $forms),
            $this->labelled(__('Status', 'corex'), SubmissionExportTable::statusName((string) ($query['status'] ?? ''))),
            $this->labelled(__('Owner', 'corex'), (string) ($query['owner'] ?? '')),
            $this->dates((string) ($query['date_from'] ?? ''), (string) ($query['date_to'] ?? '')),
            $this->labelled(__('Search', 'corex'), (string) ($query['search'] ?? '')),
        ]);

        return $parts === []
            ? __('Every submission in view, with no filter on', 'corex')
            : implode('; ', $parts);
    }

    /**
     * The form an export was filtered to. The filter holds an id or a slug; the name is the one
     * the exported submissions carry, when they are all of one form.
     *
     * @param array<string,string> $forms
     */
    private function form(string $filter, array $forms): string
    {
        if ($filter === '') {
            return '';
        }

        return $this->labelled(__('Form', 'corex'), count($forms) === 1 ? (string) reset($forms) : $filter);
    }

    private function labelled(string $label, string $value): string
    {
        return $value === '' ? '' : $label . ': ' . $value;
    }

    private function dates(string $from, string $to): string
    {
        if ($from !== '' && $to !== '') {
            /* translators: 1: the first day of a date range. 2: its last day. */
            return sprintf(__('%1$s to %2$s', 'corex'), $from, $to);
        }
        if ($from !== '') {
            /* translators: %s: a date. */
            return sprintf(__('From %s', 'corex'), $from);
        }

        /* translators: %s: a date. */
        return $to === '' ? '' : sprintf(__('Until %s', 'corex'), $to);
    }
}
