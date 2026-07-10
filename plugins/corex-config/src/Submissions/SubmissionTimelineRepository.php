<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DomainException;

/**
 * Append-only submission timeline stored with the private submission record.
 */
final class SubmissionTimelineRepository implements SubmissionTimelineStore
{
    private const META_KEY = 'corex_submission_timeline';

    public function append(int $submissionId, string $stage, string $outcome, array $summary): array
    {
        $this->assertSubmission($submissionId);
        $events = $this->events($submissionId);
        $event = [
            'id' => $this->nextId($events),
            'submission_id' => $submissionId,
            'stage' => sanitize_key($stage),
            'outcome' => sanitize_key($outcome),
            'summary' => $summary,
            'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
        $events[] = $event;
        update_post_meta($submissionId, self::META_KEY, $events);

        return $event;
    }

    public function forSubmission(int $submissionId, bool $includeRestricted): array
    {
        $this->assertSubmission($submissionId);
        $events = $this->events($submissionId);

        if ($includeRestricted) {
            return $events;
        }

        return array_values(array_filter(
            $events,
            static fn (array $event): bool => ($event['summary']['visibility'] ?? '') !== 'restricted',
        ));
    }

    private function assertSubmission(int $submissionId): void
    {
        if ($submissionId < 1 || get_post_type($submissionId) !== 'corex_submission') {
            throw new DomainException(__('Submission was not found.', 'corex'));
        }
    }

    /** @return list<array<string,mixed>> */
    private function events(int $submissionId): array
    {
        $events = get_post_meta($submissionId, self::META_KEY, true);

        return is_array($events) ? array_values(array_filter($events, 'is_array')) : [];
    }

    /** @param list<array<string,mixed>> $events */
    private function nextId(array $events): int
    {
        $ids = array_map(static fn (array $event): int => (int) ($event['id'] ?? 0), $events);

        return $ids === [] ? 1 : max($ids) + 1;
    }
}
