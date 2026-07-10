<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\SubmissionPipelineResult;
use DateTimeImmutable;
use WP_REST_Request;

/**
 * Stable Flow REST mapping port composed from separate input and presentation policies.
 */
final readonly class FlowRestMapper
{
    public function __construct(
        private FlowRestInputMapper $input,
        private FlowRestPresenter $presenter,
    ) {
    }

    public function createShape(): array
    {
        return $this->input->createShape();
    }

    public function updateShape(): array
    {
        return $this->input->updateShape();
    }

    public function transitionShape(): array
    {
        return $this->input->transitionShape();
    }

    public function testShape(): array
    {
        return $this->input->testShape();
    }

    public function listFilters(WP_REST_Request $request): array
    {
        return $this->input->listFilters($request);
    }

    public function newFlow(array $input, int $actorId, DateTimeImmutable $now): NewFlowDraft
    {
        return $this->input->newFlow($input, $actorId, $now);
    }

    public function draftUpdate(int $flowId, array $input, int $actorId, DateTimeImmutable $now): FlowDraftUpdate
    {
        return $this->input->draftUpdate($flowId, $input, $actorId, $now);
    }

    public function transition(int $flowId, array $input, int $actorId, DateTimeImmutable $now): FlowTransition
    {
        return $this->input->transition($flowId, $input, $actorId, $now);
    }

    public function flow(Flow $flow): array
    {
        return $this->presenter->flow($flow);
    }

    public function summary(Flow $flow, FlowVersion $version): array
    {
        return $this->presenter->summary($flow, $version);
    }

    public function version(FlowVersion $version): array
    {
        return $this->presenter->version($version);
    }

    public function pipeline(SubmissionPipelineResult $pipeline): array
    {
        return $this->presenter->pipeline($pipeline);
    }
}
