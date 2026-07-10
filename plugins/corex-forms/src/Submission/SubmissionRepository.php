<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Models\Model;
use Corex\Repositories\PostRepository;

/**
 * Persists submissions through the data layer (Principle III: the repository is the
 * only layer that touches the data source). Stores a private `corex_submission` post
 * plus the form slug and each validated value as `corex_field_*` meta — queryable by slug.
 */
final class SubmissionRepository extends PostRepository implements SubmissionStore
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

    /** @param array<string,mixed> $metadata */
    public function updatePipelineMetadata(int $submissionId, array $metadata): void
    {
        if ($this->find($submissionId) === null) {
            throw new \DomainException(__('Submission was not found.', 'corex'));
        }

        $this->writeMetadata($submissionId, $metadata);
    }

    /** @param array<string,mixed> $entry */
    public function appendTimeline(int $submissionId, array $entry): void
    {
        if ($this->find($submissionId) === null) {
            throw new \DomainException(__('Submission was not found.', 'corex'));
        }
        $timeline = get_post_meta($submissionId, 'corex_submission_timeline', true);
        $timeline = is_array($timeline) ? $timeline : [];
        $timeline[] = $entry;
        $this->fields->set($submissionId, 'corex_submission_timeline', $timeline);
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
