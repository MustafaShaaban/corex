<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowResponseCounter;
use Corex\Models\Model;
use Corex\Repositories\PostRepository;
use DateTimeImmutable;

/**
 * Persists submissions through the data layer (Principle III: the repository is the
 * only layer that touches the data source). Stores a private `corex_submission` post
 * plus the form slug and each validated value as `corex_field_*` meta — queryable by slug.
 */
final class SubmissionRepository extends PostRepository implements SubmissionStore, FlowResponseCounter
{
    protected function model(): string
    {
        return Submission::class;
    }

    /**
     * The {@see SubmissionStore} seam (spec 045): persist and return the id. The default
     * post-meta driver.
     *
     * @param array<string,mixed> $values
     */
    public function save(string $slug, array $values): int
    {
        return $this->store($slug, $values)->id();
    }

    /**
     * @param array<string,mixed> $values validated values, keyed by canonical field name
     */
    public function store(string $slug, array $values): Model
    {
        $submission = $this->create([
            'title'  => sprintf('%s — %s', $slug, current_time('mysql')),
            'status' => 'private',
        ]);

        $this->fields->set($submission->id(), 'corex_form_slug', $slug);

        foreach ($values as $name => $value) {
            $this->fields->set($submission->id(), 'corex_field_' . $name, $value);
        }

        return $submission;
    }

    public function storeFlow(FlowSubmissionRecord $record): int
    {
        $submission = $this->create([
            'title' => sprintf('%s — %s', $record->flowSlug, current_time('mysql')),
            'status' => 'private',
        ]);
        $id = $submission->id();
        $this->fields->set($id, 'corex_form_slug', $record->flowSlug);
        $this->fields->set($id, 'corex_flow_id', $record->flowId);
        $this->fields->set($id, 'corex_flow_version_id', $record->flowVersionId);
        $this->fields->set($id, 'corex_flow_label_snapshot', $record->flowLabel);
        $this->fields->set($id, 'corex_is_test', $record->isTest ? 1 : 0);
        $this->fields->set($id, 'corex_submission_status', 'new');
        $this->fields->set($id, 'corex_values_json', $record->values);
        $this->fields->set($id, 'corex_submitter_name', $this->projection($record->values, ['name', 'full_name', 'first_name']));
        $this->fields->set($id, 'corex_submitter_email', $this->projection($record->values, ['email', 'email_address']));
        $this->fields->set($id, 'corex_submission_updated_at', gmdate(DATE_ATOM));
        $this->writeMetadata($id, $record->metadata);
        foreach ($record->values as $name => $value) {
            $this->fields->set($id, 'corex_field_' . sanitize_key((string) $name), $value);
        }

        return $id;
    }

    /**
     * What a flow email of this submission was addressed from: the flow and version it was made
     * with, its answers and its routing (spec 112, FR-032a). Null for a submission no flow made.
     *
     * @return array{flow_id:int,version_id:int,values:array<string,mixed>,routing:array<string,mixed>}|null
     */
    public function emailSource(int $submissionId): ?array
    {
        $flowId    = (int) $this->fields->get($submissionId, 'corex_flow_id', 0);
        $versionId = (int) $this->fields->get($submissionId, 'corex_flow_version_id', 0);
        if ($flowId < 1 || $versionId < 1) {
            return null;
        }

        return [
            'flow_id'    => $flowId,
            'version_id' => $versionId,
            'values'     => (array) $this->fields->get($submissionId, 'corex_values_json', []),
            'routing'    => (array) $this->fields->get($submissionId, 'corex_routing_json', []),
        ];
    }

    /** @param array<string,mixed> $metadata */
    public function updatePipelineMetadata(int $submissionId, array $metadata): void
    {
        if ($this->find($submissionId) === null) {
            throw new \DomainException(__('Submission was not found.', 'corex'));
        }

        $this->writeMetadata($submissionId, $metadata);
    }

    /**
     * Append a timeline event in the one canonical shape the admin screens read
     * (`{id, submission_id, stage, outcome, summary, created_at}`) — the same shape
     * `Corex\Config\Submissions\SubmissionTimelineRepository` writes, so the pipeline's own
     * events are no longer a foreign shape the UI has to special-case.
     *
     * @param array<string,mixed> $summary
     */
    public function appendTimeline(int $submissionId, string $stage, string $outcome, array $summary = []): void
    {
        if ($this->find($submissionId) === null) {
            throw new \DomainException(__('Submission was not found.', 'corex'));
        }
        $timeline = get_post_meta($submissionId, 'corex_submission_timeline', true);
        $timeline = is_array($timeline) ? $timeline : [];
        $ids = array_map(static fn (mixed $event): int => is_array($event) ? (int) ($event['id'] ?? 0) : 0, $timeline);
        $timeline[] = [
            'id' => ($ids === [] ? 0 : max($ids)) + 1,
            'submission_id' => $submissionId,
            'stage' => sanitize_key($stage),
            'outcome' => sanitize_key($outcome),
            'summary' => $summary,
            'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
        $this->fields->set($submissionId, 'corex_submission_timeline', $timeline);
    }

    /**
     * Stored responses of a flow: kept (not in the trash) and not a marked test (spec 122, FR-030).
     *
     * Counted in SQL and not through `WP_Query`, which shows a private post only to someone who may
     * read it: asked while a visitor submits, it found none, and a cap never closed a form.
     */
    public function responses(int $flowId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} flow ON flow.post_id = p.ID AND flow.meta_key = 'corex_flow_id' AND flow.meta_value = %s
             LEFT JOIN {$wpdb->postmeta} test ON test.post_id = p.ID AND test.meta_key = 'corex_is_test'
             WHERE p.post_type = 'corex_submission' AND p.post_status = 'private'
             AND (test.meta_value IS NULL OR test.meta_value <> '1')",
            (string) $flowId,
        ));
    }

    /**
     * Runs `$work` while no other request runs it for the same flow: a capped flow's count and its
     * store happen under one MySQL named lock, so two last submissions never both fit (FR-030).
     * MySQL lets the lock go if the request dies, so nothing is left to clear.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function oneAtATime(int $flowId, callable $work): mixed
    {
        global $wpdb;

        $lock = 'corex_flow_cap_' . $flowId;
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, 10)) !== 1) {
            throw new \RuntimeException('CoreX could not count the responses of this form.');
        }
        try {
            return $work();
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /**
     * Whether anything is assigned to this person directly or to one of their roles (spec 122,
     * FR-013): what opens an assigned-only inbox for someone who does not manage submissions.
     *
     * @param list<string> $roles
     */
    public function hasAssigned(int $userId, array $roles): bool
    {
        global $wpdb;

        $roles = array_values(array_filter($roles, static fn (string $role): bool => $role !== ''));
        $roleClause = $roles === []
            ? ''
            : $wpdb->prepare(
                " OR (type.meta_value = 'role' AND owner.meta_value IN (" . implode(', ', array_fill(0, count($roles), '%s')) . '))',
                ...$roles,
            );

        // In SQL, for the reason `responses()` gives: the person asking cannot read these posts yet.
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} type ON type.post_id = p.ID AND type.meta_key = 'corex_owner_type'
                 INNER JOIN {$wpdb->postmeta} owner ON owner.post_id = p.ID AND owner.meta_key = 'corex_owner_key'
                 WHERE p.post_type = 'corex_submission' AND p.post_status = 'private'
                 AND ((type.meta_value = 'user' AND owner.meta_value = %s)",
                (string) $userId,
            ) . $roleClause . ') LIMIT 1',
        ) !== null;
    }

    /**
     * Flow submissions whose owner names nobody: stored with a route's type (flow owner, page
     * owner, field value…) and not a user, a team or a role (spec 122, FR-014).
     *
     * @return list<int>
     */
    public function withUnresolvedOwner(): array
    {
        global $wpdb;

        $types = ['flow_owner', 'page_owner', 'post_author', 'department', 'field_value', 'email', 'extension'];

        // In SQL: it runs on whichever request comes first after the update, a visitor's included.
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} flow ON flow.post_id = p.ID AND flow.meta_key = 'corex_flow_id'
             INNER JOIN {$wpdb->postmeta} type ON type.post_id = p.ID AND type.meta_key = 'corex_owner_type'
             WHERE p.post_type = 'corex_submission' AND p.post_status IN ('private', 'trash')
             AND type.meta_value IN (" . implode(', ', array_fill(0, count($types), '%s')) . ')',
            ...$types,
        )));
    }

    public function flowOf(int $submissionId): int
    {
        return (int) $this->fields->get($submissionId, 'corex_flow_id', 0);
    }

    /** Gives a submission to a user and says why on its timeline. Nothing else of it changes. */
    public function reassignToUser(int $submissionId, int $userId, string $reason): void
    {
        $this->fields->set($submissionId, 'corex_owner_type', 'user');
        $this->fields->set($submissionId, 'corex_owner_key', (string) $userId);
        $inbox = (array) $this->fields->get($submissionId, 'corex_inbox_json', []);
        $this->fields->set($submissionId, 'corex_inbox_json', [...$inbox, 'owner_type' => 'user', 'owner_key' => (string) $userId]);
        $this->appendTimeline($submissionId, 'routing', 'info', ['message' => $reason]);
    }


    /** @param array<string,mixed> $metadata */
    private function writeMetadata(int $submissionId, array $metadata): void
    {
        $this->fields->set($submissionId, 'corex_pipeline_metadata', $metadata);
        $map = [
            'hidden' => 'corex_hidden_metadata_json',
            'utm' => 'corex_utm_json',
            'consent' => 'corex_consent_snapshot_json',
            'spam' => 'corex_spam_json',
            'routing' => 'corex_routing_json',
            'email' => 'corex_email_json',
            'notification_delivery' => 'corex_notification_delivery',
            'inbox' => 'corex_inbox_json',
        ];
        foreach ($map as $source => $metaKey) {
            if (array_key_exists($source, $metadata)) {
                $this->fields->set($submissionId, $metaKey, $metadata[$source]);
            }
        }
        $inbox = $metadata['inbox'] ?? null;
        if (is_array($inbox)) {
            $this->fields->set($submissionId, 'corex_owner_type', (string) ($inbox['owner_type'] ?? 'none'));
            $this->fields->set($submissionId, 'corex_owner_key', (string) ($inbox['owner_key'] ?? ''));
        }
    }

    /** @param array<string,mixed> $values @param list<string> $keys */
    private function projection(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            if (is_scalar($values[$key] ?? null)) {
                return sanitize_text_field((string) $values[$key]);
            }
        }

        return '';
    }
}
