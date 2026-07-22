<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionExportSource;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Config\Retention\SubmissionRetentionStore;
use DomainException;
use WP_Query;

/**
 * The WordPress boundary for reading form submissions: queries `corex_submission` posts
 * (newest first, paginated) and reads their `corex_form_slug` + `corex_field_*` meta. The
 * row-shaping lives in SubmissionsSource; this is the only class here that touches WP_Query.
 */
final class WpSubmissionsReader implements SubmissionsReader, SubmissionWorkflowStore, SubmissionExportSource, SubmissionRetentionStore
{
    /**
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    public function page(int $page, int $perPage): array
    {
        $query = new WP_Query([
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => min(max($perPage, 1), 100),
            'paged'          => max($page, 1),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
            'meta_query'     => $this->nonTestClause(),
        ]);

        $rows = [];
        foreach ($query->posts as $post) {
            $meta   = get_post_meta($post->ID);
            $fields = [];
            foreach ($meta as $key => $value) {
                if (str_starts_with($key, 'corex_field_')) {
                    $fields[substr($key, strlen('corex_field_'))] = maybe_unserialize($value[0] ?? '');
                }
            }

            $rows[] = [
                'id'     => (int) $post->ID,
                'date'   => (string) $post->post_date,
                'form'   => (string) ($meta['corex_form_slug'][0] ?? ''),
                'fields' => $fields,
            ];
        }

        return $rows;
    }

    public function total(): int
    {
        $query = new WP_Query([
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $this->nonTestClause(),
        ]);

        return (int) $query->found_posts;
    }

    public function trash(int $id): bool
    {
        return get_post_type($id) === 'corex_submission' && wp_trash_post($id) !== false;
    }

    public function trashForRetention(int $id): bool
    {
        return $this->trash($id);
    }

    public function archiveForRetention(int $id): bool
    {
        if (get_post_type($id) !== 'corex_submission') {
            return false;
        }
        update_post_meta($id, 'corex_submission_status', 'archived');
        update_post_meta($id, 'corex_retention_state', 'archived');
        update_post_meta($id, 'corex_submission_updated_at', gmdate(DATE_ATOM));
        $this->appendRetentionEvent($id, 'archived');

        return true;
    }

    public function anonymizeForRetention(int $id): bool
    {
        if (get_post_type($id) !== 'corex_submission') {
            return false;
        }
        foreach (array_keys(get_post_meta($id)) as $key) {
            if (str_starts_with((string) $key, 'corex_field_') || in_array($key, [
                'corex_values_json', 'corex_submitter_name', 'corex_submitter_email',
                'corex_hidden_metadata_json', 'corex_utm_json', 'corex_consent_snapshot_json',
                'corex_submission_notes',
            ], true)) {
                delete_post_meta($id, (string) $key);
            }
        }
        update_post_meta($id, 'corex_values_json', ['anonymized' => true]);
        update_post_meta($id, 'corex_retention_state', 'anonymized');
        update_post_meta($id, 'corex_submission_updated_at', gmdate(DATE_ATOM));
        wp_update_post(['ID' => $id, 'post_title' => sprintf('Anonymized submission #%d', $id)]);
        $this->appendRetentionEvent($id, 'anonymized');

        return true;
    }

    /**
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    public function query(DataQuery $query): array
    {
        $found = new WP_Query($this->args($query, false));

        $rows = [];
        foreach ($found->posts as $post) {
            $rows[] = $this->shape($post);
        }

        return $rows;
    }

    public function count(DataQuery $query): int
    {
        $args                   = $this->args($query, true);
        $args['fields']         = 'ids';
        $args['posts_per_page'] = 1;

        return (int) (new WP_Query($args))->found_posts;
    }

    /**
     * @return array{id:int,date:string,form:string,fields:array<string,mixed>}|null
     */
    public function find(int $id): ?array
    {
        $post = get_post($id);

        if ($post === null || $post->post_type !== 'corex_submission') {
            return null;
        }

        return $this->shape($post);
    }

    /**
     * @return list<string>
     */
    public function fieldKeys(int $sample): array
    {
        $query = new WP_Query([
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => min(max($sample, 1), 100),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => $this->nonTestClause(),
        ]);

        $keys = [];
        foreach ($query->posts as $id) {
            foreach (array_keys(get_post_meta((int) $id)) as $metaKey) {
                if (str_starts_with((string) $metaKey, 'corex_field_')) {
                    $keys[substr((string) $metaKey, strlen('corex_field_'))] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * @return array<string,int>
     */
    public function dailyCounts(int $days): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d 00:00:00', time() - (max($days, 1) - 1) * DAY_IN_SECONDS);

        /** @var list<array{d:string,c:string}> $rows */
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(p.post_date) AS d, COUNT(*) AS c FROM {$wpdb->posts} p"
                . ' WHERE p.post_type = %s AND p.post_status = %s AND p.post_date >= %s'
                . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm"
                . " WHERE pm.post_id = p.ID AND pm.meta_key = 'corex_is_test' AND pm.meta_value = '1')"
                . ' GROUP BY DATE(p.post_date)',
                'corex_submission',
                'private',
                $since,
            ),
            ARRAY_A,
        ) ?: [];

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['d']] = (int) $row['c'];
        }

        return $counts;
    }

    public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
    {
        $found = new WP_Query($this->inboxArgs($query, $scope));

        return [
            'items' => array_map(
                fn (\WP_Post $post): array => $this->shapeInbox($post, $scope->canViewRestrictedNotes),
                $found->posts,
            ),
            'total' => (int) $found->found_posts,
        ];
    }

    public function findInbox(int $id, SubmissionAccessScope $scope): ?array
    {
        $post = get_post($id);
        if (! $post instanceof \WP_Post || $post->post_type !== 'corex_submission' || $post->post_status !== 'private') {
            return null;
        }

        $record = $this->shapeInbox($post, $scope->canViewRestrictedNotes);

        return $scope->allows($record) ? $record : null;
    }

    public function findWorkflow(int $id): ?array
    {
        $post = get_post($id);

        return $post instanceof \WP_Post && $post->post_type === 'corex_submission' && $post->post_status === 'private'
            ? $this->shapeInbox($post, true)
            : null;
    }

    public function updateWorkflow(int $id, array $changes, string $expectedUpdatedAt): array
    {
        $record = $this->findWorkflow($id);
        if ($record === null) {
            throw new DomainException(__('Submission was not found.', 'corex'));
        }
        if (! hash_equals((string) $record['updated_at'], $expectedUpdatedAt)) {
            throw new DomainException(__('The submission changed after it was loaded.', 'corex'));
        }

        $metaKeys = [
            'status' => 'corex_submission_status',
            'read_at' => 'corex_read_at',
            'read_by' => 'corex_read_by',
            'owner_type' => 'corex_owner_type',
            'owner_key' => 'corex_owner_key',
            'retention_state' => 'corex_retention_state',
            'exported_at' => 'corex_exported_at',
        ];
        foreach ($changes as $key => $value) {
            if (isset($metaKeys[$key])) {
                update_post_meta($id, $metaKeys[$key], $value);
            }
        }
        update_post_meta($id, 'corex_submission_updated_at', gmdate(DATE_ATOM));

        return $this->findWorkflow($id) ?? throw new DomainException(__('Submission was not found.', 'corex'));
    }

    public function addWorkflowNote(int $id, int $authorId, string $body, string $visibility): array
    {
        if ($this->findWorkflow($id) === null) {
            throw new DomainException(__('Submission was not found.', 'corex'));
        }

        $notes = get_post_meta($id, 'corex_submission_notes', true);
        $notes = is_array($notes) ? array_values($notes) : [];
        $note = [
            'id' => $this->nextNoteId($notes),
            'submission_id' => $id,
            'author_id' => $authorId,
            'body' => $body,
            'visibility' => $visibility,
            'created_at' => gmdate(DATE_ATOM),
            'updated_at' => gmdate(DATE_ATOM),
        ];
        $notes[] = $note;
        update_post_meta($id, 'corex_submission_notes', $notes);
        update_post_meta($id, 'corex_submission_updated_at', gmdate(DATE_ATOM));

        return $note;
    }

    public function markExported(array $submissionIds, string $exportedAt): void
    {
        foreach (array_unique(array_map('intval', $submissionIds)) as $id) {
            if ($id > 0 && get_post_type($id) === 'corex_submission') {
                update_post_meta($id, 'corex_exported_at', $exportedAt);
            }
        }
    }

    /**
     * Build the WP_Query args for a DataQuery: a `form` filter via the slug meta, a date
     * sort, and pagination. The free-text search uses WP_Query's `s` (post fields); meta
     * value search is a documented limitation of the post-meta driver (a custom-table
     * driver would index it). All values are passed as args (no SQL string-building).
     *
     * @return array<string,mixed>
     */
    private function args(DataQuery $query, bool $forCount): array
    {
        $args = [
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => $forCount ? 1 : $query->perPage,
            'paged'          => $query->page,
            'orderby'        => 'date',
            'order'          => $query->sortColumn === 'date' && $query->sortDir === 'asc' ? 'ASC' : 'DESC',
            'no_found_rows'  => false,
            'meta_query'     => $this->nonTestClause(),
        ];

        $metaQuery = ['relation' => 'AND', $this->nonTestClause()];
        if ($query->search !== '') {
            $metaQuery[] = $this->searchClause($query->search);
        }
        $form = $query->filters['form'] ?? '';
        if ($form !== '') {
            $metaQuery[] = ['key' => 'corex_form_slug', 'value' => $form, 'compare' => '='];
        }
        $args['meta_query'] = $metaQuery;

        return $args;
    }

    /** @return array<string,mixed> */
    private function nonTestClause(): array
    {
        return ['relation' => 'OR',
            ['key' => 'corex_is_test', 'compare' => 'NOT EXISTS'],
            ['key' => 'corex_is_test', 'value' => '0', 'compare' => '='],
        ];
    }

    /** @return array<string,mixed> */
    private function inboxArgs(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
    {
        $args = [
            'post_type' => 'corex_submission',
            'post_status' => 'private',
            'posts_per_page' => $query->perPage,
            'paged' => $query->page,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false,
            'meta_query' => $this->inboxMetaQuery($query, $scope),
        ];

        $dateQuery = [];
        if ($query->dateFrom !== '') {
            $dateQuery['after'] = $query->dateFrom . ' 00:00:00';
            $dateQuery['inclusive'] = true;
        }
        if ($query->dateTo !== '') {
            $dateQuery['before'] = $query->dateTo . ' 23:59:59';
            $dateQuery['inclusive'] = true;
        }
        if ($dateQuery !== []) {
            $args['date_query'] = [$dateQuery];
        }

        return $args;
    }

    /** @return array<string,mixed> */
    private function inboxMetaQuery(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
    {
        $clauses = ['relation' => 'AND'];

        if (! $query->includeTest) {
            $clauses[] = ['relation' => 'OR',
                ['key' => 'corex_is_test', 'compare' => 'NOT EXISTS'],
                ['key' => 'corex_is_test', 'value' => '0', 'compare' => '='],
            ];
        }
        if ($query->flowId > 0) {
            $clauses[] = ['key' => 'corex_flow_id', 'value' => $query->flowId, 'compare' => '=', 'type' => 'NUMERIC'];
        }
        if ($query->status !== '') {
            $clauses[] = $this->statusClause($query->status);
        }
        if ($query->owner !== '') {
            [$ownerType, $ownerKey] = explode(':', $query->owner, 2);
            $clauses[] = $this->ownerClause($ownerType, $ownerKey);
        }
        if ($query->search !== '') {
            $clauses[] = $this->searchClause($query->search);
        }
        if (! $scope->manageAll) {
            $clauses[] = $this->scopeClause($scope);
        }

        return $clauses;
    }

    /** @return array<string,mixed> */
    private function statusClause(string $status): array
    {
        if ($status !== 'new') {
            return ['key' => 'corex_submission_status', 'value' => $status, 'compare' => '='];
        }

        return ['relation' => 'OR',
            ['key' => 'corex_submission_status', 'compare' => 'NOT EXISTS'],
            ['key' => 'corex_submission_status', 'value' => 'new', 'compare' => '='],
        ];
    }

    /** @return array<string,mixed> */
    private function ownerClause(string $type, string $key): array
    {
        return ['relation' => 'AND',
            ['key' => 'corex_owner_type', 'value' => $type, 'compare' => '='],
            ['key' => 'corex_owner_key', 'value' => $key, 'compare' => '='],
        ];
    }

    /** @return array<string,mixed> */
    private function searchClause(string $search): array
    {
        return ['relation' => 'OR',
            ['key' => 'corex_submitter_name', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'corex_submitter_email', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'corex_form_slug', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'corex_flow_label_snapshot', 'value' => $search, 'compare' => 'LIKE'],
        ];
    }

    /** @return array<string,mixed> */
    private function scopeClause(SubmissionAccessScope $scope): array
    {
        $allowed = ['relation' => 'OR', $this->ownerClause('user', (string) $scope->actorId)];
        foreach ($scope->teamKeys as $key) {
            $allowed[] = $this->ownerClause('team', $key);
        }
        foreach ($scope->roleKeys as $key) {
            $allowed[] = $this->ownerClause('role', $key);
        }

        return $allowed;
    }

    /**
     * @return array{id:int,date:string,form:string,fields:array<string,mixed>}
     */
    private function shape(\WP_Post $post): array
    {
        $meta   = get_post_meta($post->ID);
        $fields = [];
        foreach ($meta as $key => $value) {
            if (str_starts_with($key, 'corex_field_')) {
                $fields[substr($key, strlen('corex_field_'))] = maybe_unserialize($value[0] ?? '');
            }
        }

        return [
            'id'     => (int) $post->ID,
            'date'   => (string) $post->post_date,
            'form'   => (string) ($meta['corex_form_slug'][0] ?? ''),
            'fields' => $fields,
        ];
    }

    /** @return array<string,mixed> */
    private function shapeInbox(\WP_Post $post, bool $includeRestrictedNotes): array
    {
        $meta = get_post_meta($post->ID);
        $values = $this->metaArray($meta, 'corex_values_json');
        if ($values === []) {
            $values = $this->fields($meta);
        }
        $inbox = $this->metaArray($meta, 'corex_inbox_json');
        $notes = $this->metaArray($meta, 'corex_submission_notes');
        if (! $includeRestrictedNotes) {
            $notes = array_values(array_filter(
                $notes,
                static fn (mixed $note): bool => is_array($note) && ($note['visibility'] ?? '') !== 'restricted',
            ));
        }

        return [
            'id' => (int) $post->ID,
            'uuid' => (string) ($this->metaValue($meta, 'corex_submission_uuid') ?: 'submission-' . $post->ID),
            'flow_id' => (int) $this->metaValue($meta, 'corex_flow_id'),
            'flow_version_id' => (int) $this->metaValue($meta, 'corex_flow_version_id'),
            'flow' => (string) ($this->metaValue($meta, 'corex_flow_label_snapshot') ?: $this->metaValue($meta, 'corex_form_slug')),
            'form' => (string) $this->metaValue($meta, 'corex_form_slug'),
            'is_test' => (bool) $this->metaValue($meta, 'corex_is_test'),
            'status' => (string) ($this->metaValue($meta, 'corex_submission_status') ?: 'new'),
            'read_at' => $this->nullableMeta($meta, 'corex_read_at'),
            'read_by' => $this->nullableInt($meta, 'corex_read_by'),
            'owner_type' => (string) ($this->metaValue($meta, 'corex_owner_type') ?: ($inbox['owner_type'] ?? 'none')),
            'owner_key' => (string) ($this->metaValue($meta, 'corex_owner_key') ?: ($inbox['owner_key'] ?? '')),
            'submitter_name' => $this->submitter($meta, $values, ['name', 'full_name', 'first_name']),
            'submitter_email' => $this->submitter($meta, $values, ['email', 'email_address']),
            'values' => $values,
            'hidden_metadata' => $this->metaArray($meta, 'corex_hidden_metadata_json'),
            'utm' => $this->metaArray($meta, 'corex_utm_json'),
            'consent_snapshot' => $this->metaArray($meta, 'corex_consent_snapshot_json'),
            'spam' => $this->metaArray($meta, 'corex_spam_json'),
            'related_emails' => $this->metaArray($meta, 'corex_email_json'),
            'notes' => $notes,
            'timeline' => $this->timelineEvents($meta),
            'delivery' => $this->deliveryProjection($meta),
            'retention_state' => (string) ($this->metaValue($meta, 'corex_retention_state') ?: 'active'),
            'exported_at' => $this->nullableMeta($meta, 'corex_exported_at'),
            'created_at' => (string) $post->post_date_gmt,
            'updated_at' => (string) ($this->metaValue($meta, 'corex_submission_updated_at') ?: $post->post_modified_gmt),
        ];
    }

    /** @param array<string,list<mixed>> $meta @return array<string,mixed> */
    private function fields(array $meta): array
    {
        $fields = [];
        foreach ($meta as $key => $value) {
            if (str_starts_with($key, 'corex_field_')) {
                $fields[substr($key, strlen('corex_field_'))] = maybe_unserialize($value[0] ?? '');
            }
        }

        return $fields;
    }

    /** @param array<string,list<mixed>> $meta @return array<mixed> */
    private function metaArray(array $meta, string $key): array
    {
        $value = maybe_unserialize($meta[$key][0] ?? []);

        return is_array($value) ? $value : [];
    }

    /** @param array<string,list<mixed>> $meta */
    private function metaValue(array $meta, string $key): mixed
    {
        return maybe_unserialize($meta[$key][0] ?? '');
    }

    /** @param array<string,list<mixed>> $meta */
    private function nullableMeta(array $meta, string $key): ?string
    {
        $value = (string) $this->metaValue($meta, $key);

        return $value === '' ? null : $value;
    }

    /** @param array<string,list<mixed>> $meta */
    private function nullableInt(array $meta, string $key): ?int
    {
        $value = (int) $this->metaValue($meta, $key);

        return $value > 0 ? $value : null;
    }

    /**
     * @param array<string,list<mixed>> $meta
     * @param array<string,mixed> $values
     * @param list<string> $keys
     */
    private function submitter(array $meta, array $values, array $keys): string
    {
        $projection = (string) $this->metaValue($meta, $keys[0] === 'email' ? 'corex_submitter_email' : 'corex_submitter_name');
        if ($projection !== '') {
            return $projection;
        }
        foreach ($keys as $key) {
            if (is_scalar($values[$key] ?? null)) {
                return (string) $values[$key];
            }
        }

        return '';
    }

    /** @param list<array<string,mixed>> $notes */
    private function nextNoteId(array $notes): int
    {
        $ids = array_map(static fn (array $note): int => (int) ($note['id'] ?? 0), $notes);

        return $ids === [] ? 1 : max($ids) + 1;
    }

    /**
     * The timeline in one canonical shape. Events written by earlier versions used the pipeline's
     * old `{kind, state, occurred_at}` form; they are hydrated here rather than dropped, so a legacy
     * submission's history still reads correctly.
     *
     * @param array<string,list<mixed>> $meta
     * @return list<array<string,mixed>>
     */
    private function timelineEvents(array $meta): array
    {
        $events = $this->metaArray($meta, 'corex_submission_timeline');

        return array_values(array_map(
            function (mixed $event): array {
                if (! is_array($event)) {
                    return [];
                }
                if (! isset($event['stage']) && isset($event['kind'])) {
                    return [
                        'id' => (int) ($event['id'] ?? 0),
                        'submission_id' => (int) ($event['submission_id'] ?? 0),
                        'stage' => 'submitted',
                        'outcome' => (string) ($event['state'] ?? 'success'),
                        'summary' => ['kind' => (string) $event['kind']],
                        'created_at' => (string) ($event['occurred_at'] ?? ($event['created_at'] ?? '')),
                    ];
                }

                return $event;
            },
            $events,
        ));
    }

    /**
     * The notification-delivery projection, or an honest "unavailable" for a submission saved before
     * delivery was tracked — never presented as successful (FR-018).
     *
     * @param array<string,list<mixed>> $meta
     * @return array<string,mixed>
     */
    private function deliveryProjection(array $meta): array
    {
        $delivery = $this->metaArray($meta, 'corex_notification_delivery');
        if (! isset($delivery['status'])) {
            return [
                'status' => 'unavailable',
                'attempt_id' => null,
                'provider' => null,
                'attempted_at' => null,
                'retryable' => false,
                'safe_reason' => '',
                'reason_code' => 'unavailable',
            ];
        }

        return $delivery;
    }

    private function appendRetentionEvent(int $submissionId, string $state): void
    {
        $timeline = get_post_meta($submissionId, 'corex_submission_timeline', true);
        $timeline = is_array($timeline) ? array_values($timeline) : [];
        $timeline[] = [
            'id' => count($timeline) + 1,
            'submission_id' => $submissionId,
            'stage' => 'retention',
            'outcome' => 'success',
            'summary' => ['state' => $state],
            'created_at' => gmdate(DATE_ATOM),
        ];
        update_post_meta($submissionId, 'corex_submission_timeline', $timeline);
    }
}
