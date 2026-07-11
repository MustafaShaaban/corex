<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

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
        private readonly SubmissionRetentionStore $reader,
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
    public function preview(bool $includeTest = false): array
    {
        $days = $this->days();

        return $this->settings->preview($days, count($this->oldIds($days, $includeTest)));
    }

    /**
     * Prune the submissions older than the current window to trash. Returns the number trashed.
     * The caller MUST have verified capability + nonce + confirmation before calling this.
     */
    public function prune(string $action = 'trash', bool $includeTest = false): int
    {
        return $this->applyIds($action, $this->oldIds($this->days(), $includeTest));
    }

    /**
     * Trash the given submission ids via the shared reader; returns how many were trashed. Separated
     * from the query so the deletion loop is unit-testable with a stub reader.
     *
     * @param list<int> $ids
     */
    public function applyIds(string $action, array $ids): int
    {
        if (! in_array($action, ['archive', 'trash', 'anonymize'], true)) {
            throw new \InvalidArgumentException('The submission retention action is invalid.');
        }
        $removed = 0;
        foreach ($ids as $id) {
            $applied = match ($action) {
                'archive' => $this->reader->archiveForRetention((int) $id),
                'anonymize' => $this->reader->anonymizeForRetention((int) $id),
                default => $this->reader->trashForRetention((int) $id),
            };
            if ($applied) {
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
    private function oldIds(int $days, bool $includeTest): array
    {
        if (! $this->settings->isEnabled($days)) {
            return [];
        }

        $args = [
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
        ];
        if (! $includeTest) {
            $args['meta_query'] = ['relation' => 'OR',
                ['key' => 'corex_is_test', 'compare' => 'NOT EXISTS'],
                ['key' => 'corex_is_test', 'value' => '0', 'compare' => '='],
            ];
        }
        $query = new \WP_Query($args);

        return array_map('intval', (array) $query->posts);
    }
}
