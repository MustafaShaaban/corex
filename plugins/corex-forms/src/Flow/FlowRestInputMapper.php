<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use WP_REST_Request;

/**
 * Sanitizer shapes and command construction for the Flow REST boundary.
 */
final class FlowRestInputMapper
{
    public function createShape(): array
    {
        return [
            'slug' => 'sanitize_key',
            'name' => 'sanitize_text_field',
            'description' => 'sanitize_textarea_field',
            'owner_id' => 'absint',
            'placement_type' => 'sanitize_key',
            'placement_id' => $this->nullableId(...),
            'test_mode' => $this->boolean(...),
            'configuration' => $this->structured(...),
        ];
    }

    public function updateShape(): array
    {
        return [
            'expected_version' => 'absint',
            'expected_checksum' => 'sanitize_text_field',
            'configuration' => $this->structured(...),
        ];
    }

    public function transitionShape(): array
    {
        return ['expected_version' => 'absint'];
    }

    public function testShape(): array
    {
        return ['expected_version' => 'absint', 'values' => $this->structured(...)];
    }

    /** @return array{query:string,state:string} */
    public function listFilters(WP_REST_Request $request): array
    {
        return [
            'query' => sanitize_text_field((string) $request->get_param('search')),
            'state' => sanitize_key((string) $request->get_param('state')),
        ];
    }

    /** @param array<string,mixed> $input */
    public function newFlow(array $input, int $actorId, DateTimeImmutable $now): NewFlowDraft
    {
        return new NewFlowDraft(
            slug: (string) ($input['slug'] ?? ''),
            name: (string) ($input['name'] ?? ''),
            description: (string) ($input['description'] ?? ''),
            ownerId: (int) ($input['owner_id'] ?? $actorId),
            placementType: (string) ($input['placement_type'] ?? Flow::PLACEMENT_NONE),
            placementId: isset($input['placement_id']) ? (int) $input['placement_id'] : null,
            testMode: (bool) ($input['test_mode'] ?? false),
            configuration: $this->configuration($input['configuration'] ?? []),
            actorId: $actorId,
            occurredAt: $now,
        );
    }

    /** @param array<string,mixed> $input */
    public function draftUpdate(int $flowId, array $input, int $actorId, DateTimeImmutable $now): FlowDraftUpdate
    {
        return new FlowDraftUpdate(
            flowId: $flowId,
            configuration: $this->configuration($input['configuration'] ?? []),
            expectedVersion: (int) ($input['expected_version'] ?? 0),
            expectedChecksum: (string) ($input['expected_checksum'] ?? ''),
            actorId: $actorId,
            occurredAt: $now,
        );
    }

    /** @param array<string,mixed> $input */
    public function transition(int $flowId, array $input, int $actorId, DateTimeImmutable $now): FlowTransition
    {
        return new FlowTransition($flowId, (int) ($input['expected_version'] ?? 0), $actorId, $now);
    }

    private function configuration(mixed $configuration): FlowConfiguration
    {
        $document = is_array($configuration) ? $configuration : [];

        return new FlowConfiguration(
            schema: array_values((array) ($document['schema'] ?? [])),
            validation: (array) ($document['validation'] ?? []),
            routing: (array) ($document['routing'] ?? []),
            emailRoutes: (array) ($document['email_routes'] ?? []),
            success: (array) ($document['success'] ?? []),
            placementSnapshot: (array) ($document['placement_snapshot'] ?? []),
        );
    }

    private function nullableId(mixed $candidate): ?int
    {
        return $candidate === null || $candidate === '' ? null : absint($candidate);
    }

    private function boolean(mixed $candidate): bool
    {
        return filter_var($candidate, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string|int,mixed> */
    private function structured(mixed $candidate): array
    {
        if (! is_array($candidate)) {
            return [];
        }
        $sanitized = [];
        foreach ($candidate as $key => $entry) {
            $safeKey = is_int($key) ? $key : sanitize_key((string) $key);
            $sanitized[$safeKey] = is_array($entry) ? $this->structured($entry) : $this->scalar($entry);
        }

        return $sanitized;
    }

    private function scalar(mixed $candidate): string|int|float|bool|null
    {
        return $candidate === null || is_bool($candidate) || is_int($candidate) || is_float($candidate)
            ? $candidate
            : sanitize_text_field((string) $candidate);
    }
}
