<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Pure view model for the Submissions Inbox (spec 063, Phase 2). Shapes REAL stored submissions
 * (read by the corex-config boundary from the `corex_submission` records) into inbox rows and a
 * truthful summary. It never invents a submission — an empty reader yields an empty inbox — and it
 * builds each row's preview only from the submission's own field values. WordPress-free, unit-testable.
 */
final class SubmissionsInbox
{
    private const PREVIEW_FIELDS = 2;
    private const PREVIEW_MAX    = 60;

    /**
     * @param list<array{id:int,date:string,form:string,fields:array<string,mixed>}> $submissions
     *
     * @return list<array{id:int,date:string,form:string,preview:string,fieldCount:int}>
     */
    public function rows(array $submissions): array
    {
        $rows = [];

        foreach ($submissions as $submission) {
            $fields   = $submission['fields'];
            $rows[]   = [
                'id'         => $submission['id'],
                'date'       => $submission['date'],
                'form'       => $submission['form'],
                'preview'    => $this->preview($fields),
                'fieldCount' => count($fields),
            ];
        }

        return $rows;
    }

    /**
     * @param array{total:int,recent:int,recentDays:int} $counts
     *
     * @return array{total:int,recent:int,recentDays:int,isEmpty:bool}
     */
    public function summary(array $counts): array
    {
        return [
            'total'      => max(0, $counts['total']),
            'recent'     => max(0, $counts['recent']),
            'recentDays' => max(0, $counts['recentDays']),
            'isEmpty'    => $counts['total'] <= 0,
        ];
    }

    /**
     * A short, safe preview built from the first couple of scalar field values. Arrays (multi-value
     * fields) collapse to a count marker; long values are truncated. Escaping happens at the boundary.
     *
     * @param array<string,mixed> $fields
     */
    private function preview(array $fields): string
    {
        $parts = [];

        foreach ($fields as $value) {
            if (count($parts) >= self::PREVIEW_FIELDS) {
                break;
            }

            $text = is_array($value)
                ? sprintf('[%d values]', count($value))
                : trim((string) $value);

            if ($text === '') {
                continue;
            }

            $parts[] = $text;
        }

        $preview = implode(' · ', $parts);

        if (mb_strlen($preview) > self::PREVIEW_MAX) {
            $preview = mb_substr($preview, 0, self::PREVIEW_MAX - 1) . '…';
        }

        return $preview;
    }
}
