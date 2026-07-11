<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\SubmissionPipelineResult;
use Corex\Forms\Submission\SubmissionStageResult;

/**
 * Secret-free wire projections for Flow REST responses.
 */
final class FlowRestPresenter
{
    public function flow(Flow $flow): array
    {
        return [
            'id' => $flow->id,
            'uuid' => $flow->uuid,
            'slug' => $flow->slug,
            'name' => $flow->name,
            'description' => $flow->description,
            'state' => $flow->state,
            'owner_id' => $flow->ownerId,
            'placement_type' => $flow->placementType,
            'placement_id' => $flow->placementId,
            'current_draft_version' => $flow->currentDraftVersion,
            'published_version' => $flow->publishedVersion,
            'test_mode' => $flow->testMode,
            'updated_at' => $flow->updatedAt->format(DATE_ATOM),
            'published_at' => $flow->publishedAt?->format(DATE_ATOM),
            'closed_at' => $flow->closedAt?->format(DATE_ATOM),
            'expires_at' => $flow->expiresAt?->format(DATE_ATOM),
        ];
    }

    public function summary(Flow $flow, FlowVersion $version): array
    {
        return [
            ...$this->flow($flow),
            'field_count' => count($version->configuration->schema),
            'routing_target' => (string) ($version->configuration->routing['fallback']['type'] ?? ''),
        ];
    }

    public function version(FlowVersion $version): array
    {
        return [
            'id' => $version->id,
            'flow_id' => $version->flowId,
            'version_number' => $version->versionNumber,
            'configuration' => [
                'schema' => $version->configuration->schema,
                'validation' => $version->configuration->validation,
                'routing' => $version->configuration->routing,
                'email_routes' => $version->configuration->emailRoutes,
                'success' => $version->configuration->success,
                'placement_snapshot' => $version->configuration->placementSnapshot,
            ],
            'created_by' => $version->createdBy,
            'created_at' => $version->createdAt->format(DATE_ATOM),
            'checksum' => $version->checksum,
        ];
    }

    public function pipeline(SubmissionPipelineResult $pipeline): array
    {
        return [
            'completed' => $pipeline->completed,
            'submission_id' => $pipeline->context->submissionId,
            'is_test' => $pipeline->context->isTest,
            'stages' => array_map($this->stage(...), $pipeline->stages),
        ];
    }

    private function stage(SubmissionStageResult $stage): array
    {
        return [
            'key' => $stage->key,
            'label' => $stage->key,
            'state' => $stage->state,
            'message' => $stage->message,
            'retryable' => $stage->retryable,
        ];
    }
}
