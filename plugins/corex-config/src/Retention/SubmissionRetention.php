<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

defined('ABSPATH') || exit;

/**
 * Real submission retention (spec 065): stores the retention window, finds the submissions that are
 * due under it, previews how many there are (a real dry-run), and applies the chosen action to them —
 * only when asked, and, for the same marked-test choice, never to a record the preview did not count.
 * It never acts without a caller-supplied confirmation (enforced at {@see RetentionController}) and never bypasses the trash
 * (trashed records are recoverable). The loop is separated from the WordPress query so it stays
 * unit-testable.
 *
 * A submission is due while it is older than the window and still holds its personal data. Trashing
 * takes it out of the stored set and anonymizing finishes it; archiving does neither, so an archived
 * record stays due and can still be anonymized or trashed (DECISIONS #232).
 */
final class SubmissionRetention
{
    private const OPTION = 'corex_retention_submissions_days';

    private const STATE_META = 'corex_retention_state';

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
     * The dry-run preview for the current window: how many stored submissions are due right now,
     * before an action is chosen. With the same `$includeTest`, every action acts on these or on a
     * subset of them. Real count, never fabricated, and bounded by one run's size.
     *
     * @return array{days:int,enabled:bool,count:int,willPrune:bool}
     */
    public function preview(bool $includeTest = false): array
    {
        $days = $this->days();

        return $this->settings->preview(
            $days,
            count($this->oldIds($days, $includeTest, RetentionSettings::FINISHED_STATES)),
        );
    }

    /**
     * Apply the action to the due submissions it still has something to do for, oldest first.
     * Returns the number handled. The caller MUST have verified capability + nonce + confirmation
     * before calling this.
     */
    public function prune(string $action = 'trash', bool $includeTest = false): int
    {
        $skip = $this->settings->statesToSkip($action);

        return $this->applyIds($action, $this->oldIds($this->days(), $includeTest, $skip));
    }

    /**
     * Apply the action to the given submission ids via the shared reader; returns how many it was
     * applied to. Separated from the query so the loop is unit-testable with a stub reader.
     *
     * @param list<int> $ids
     */
    public function applyIds(string $action, array $ids): int
    {
        $this->settings->assertAction($action);
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
     * The ids of submissions older than the window whose retention state is not one to skip, oldest
     * first (bounded). Empty when retention is disabled.
     *
     * @param list<string> $skipStates
     * @return list<int>
     */
    private function oldIds(int $days, bool $includeTest, array $skipStates): array
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
            // Oldest first: a bounded run takes the records that have been due longest.
            'orderby'        => ['date' => 'ASC', 'ID' => 'ASC'],
            'date_query'     => [[
                'column'    => 'post_date',
                'before'    => $days . ' days ago',
                'inclusive' => true,
            ]],
            'meta_query'     => $this->metaClauses($includeTest, $skipStates),
        ]);

        return array_map('intval', (array) $query->posts);
    }

    /**
     * @param list<string> $skipStates
     * @return list<array<int|string,mixed>>
     */
    private function metaClauses(bool $includeTest, array $skipStates): array
    {
        $clauses = [['relation' => 'OR',
            ['key' => self::STATE_META, 'compare' => 'NOT EXISTS'],
            ['key' => self::STATE_META, 'value' => $skipStates, 'compare' => 'NOT IN'],
        ]];
        if (! $includeTest) {
            $clauses[] = ['relation' => 'OR',
                ['key' => 'corex_is_test', 'compare' => 'NOT EXISTS'],
                ['key' => 'corex_is_test', 'value' => '0', 'compare' => '='],
            ];
        }

        return $clauses;
    }
}
