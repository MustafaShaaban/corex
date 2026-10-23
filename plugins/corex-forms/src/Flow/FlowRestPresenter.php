<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\RoutingDirectory;
use Corex\Forms\Routing\RoutingOwnerLabel;
use Corex\Forms\Submission\SubmissionPipelineResult;
use Corex\Forms\Submission\SubmissionStageResult;
use DateTimeImmutable;

/**
 * Secret-free wire projections for Flow REST responses.
 */
final readonly class FlowRestPresenter
{
    public function __construct(
        private FlowStatusResolver $statuses,
        private FlowNoticeText $text,
        private RoutingDirectory $directory,
        private RoutingOwnerLabel $owners,
    ) {
    }

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
            'publications' => array_map($this->publication(...), array_reverse($flow->publicationHistory())),
            'availability' => $this->availability($flow->availability),
            'status' => $this->status($flow),
        ];
    }

    public function summary(Flow $flow, FlowVersion $version): array
    {
        $routing = $version->configuration->routing;

        return [
            ...$this->flow($flow),
            'field_count' => count($version->configuration->schema),
            'routing_target' => (string) ($routing['fallback']['type'] ?? ''),
            // The fallback in words, never a stored key (spec 122, FR-015).
            'routing_owner' => $this->owners->describe((array) ($routing['fallback'] ?? []), $flow, $version->configuration->schema),
        ];
    }

    /**
     * Whether the flow takes answers now, and the list's reason when it does not.
     *
     * @return array{state:string,reason:string,at:?string,responses:?int,label:string}
     */
    public function status(Flow $flow): array
    {
        $status = $this->statuses->at($flow, new DateTimeImmutable('now'));

        return [...$status->toArray(), 'label' => $this->text->reason($status)];
    }

    /** @return array{version:int,at:string,by:int,by_name:string} */
    private function publication(FlowPublication $publication): array
    {
        return [
            ...$publication->toArray(),
            'by_name' => $publication->publishedBy > 0
                ? ($this->directory->person($publication->publishedBy)?->name ?? __('A former user', 'corex'))
                : '',
        ];
    }

    /**
     * The availability, with its dates also as the site's local date and time the editor's fields
     * show and take back.
     *
     * @return array<string,mixed>
     */
    private function availability(FlowAvailability $availability): array
    {
        $local = static fn (?DateTimeImmutable $instant): string => $instant === null ? '' : wp_date('Y-m-d\TH:i', $instant->getTimestamp());

        return [
            ...$availability->toArray(),
            'opens_at_local' => $local($availability->opensAt),
            'closes_at_local' => $local($availability->closesAt),
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
                'protection' => $version->configuration->protection,
                'actions' => $version->configuration->actions,
                'success' => $version->configuration->success,
                'placement_snapshot' => $version->configuration->placementSnapshot,
            ],
            'created_by' => $version->createdBy,
            'created_at' => $version->createdAt->format(DATE_ATOM),
            'checksum' => $version->checksum,
        ];
    }

    /** @param list<array<string,mixed>> $lines */
    public function routingPreview(array $lines): array
    {
        return ['lines' => $lines];
    }

    public function pipeline(SubmissionPipelineResult $pipeline): array
    {
        return [
            'completed' => $pipeline->completed,
            'submission_id' => $pipeline->context->submissionId,
            'is_test' => $pipeline->context->isTest,
            'stages' => array_map($this->stage(...), $pipeline->stages),
            'held_emails' => $pipeline->heldEmails,
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
