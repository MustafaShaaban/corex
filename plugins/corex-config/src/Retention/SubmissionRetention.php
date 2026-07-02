<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

use Corex\Config\Data\SubmissionsReader;

defined('ABSPATH') || exit;

/**
 * Real submission retention (spec 065): stores the retention window, finds the submissions older than
 * it, previews how many would be removed (a real dry-run), and prunes them to trash — only when asked,
 * and only what the preview measured. It never deletes without a caller-supplied confirmation (enforced
 * at {@see RetentionController}) and never bypasses the trash (records go to trash, recoverable). The
 * prune loop is separated from the WordPress query so it stays unit-testable.
 */
final class SubmissionRetention
{
    private const OPTION = 'corex_retention_submissions_days';

    public function __construct(
        private readonly RetentionSettings $settings,
        private readonly SubmissionsReader $reader,
    ) {
    }

    public function days(): int
    {
        return $this->settings->sanitizeDays(get_option(self::OPTION, 0));
    }

    public function setDays(int $days): int
    {
        $clean = $this->settings->sanitizeDays($days);
        update_option(self::OPTION, $clean, false);

        return $clean;
    }

    /**
     * The dry-run preview for the current window: how many stored submissions are older than it right
     * now. Real count, never fabricated.
     *
     * @return array{days:int,enabled:bool,count:int,willPrune:bool}
     */
    public function preview(): array
    {
        $days = $this->days();

        return $this->settings->preview($days, count($this->oldIds($days)));
    }

    /**
     * Prune the submissions older than the current window to trash. Returns the number trashed.
     * The caller MUST have verified capability + nonce + confirmation before calling this.
     */
    public function prune(): int
    {
        return $this->pruneIds($this->oldIds($this->days()));
    }

    /**
     * Trash the given submission ids via the shared reader; returns how many were trashed. Separated
     * from the query so the deletion loop is unit-testable with a stub reader.
     *
     * @param list<int> $ids
     */
    public function pruneIds(array $ids): int
    {
        $removed = 0;
        foreach ($ids as $id) {
            if ($this->reader->trash((int) $id)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * The ids of submissions older than the window (bounded). Empty when retention is disabled.
     *
     * @return list<int>
     */
    private function oldIds(int $days): array
    {
        if (! $this->settings->isEnabled($days)) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => RetentionSettings::MAX_PRUNE,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'date_query'     => [[
                'column'    => 'post_date',
                'before'    => $days . ' days ago',
                'inclusive' => true,
            ]],
        ]);

        return array_map('intval', (array) $query->posts);
    }
}
