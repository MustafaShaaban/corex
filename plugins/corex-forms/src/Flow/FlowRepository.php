<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DomainException;

/**
 * Owns flow and append-only version persistence.
 */
final class FlowRepository
{
    private const TYPE_FLOW = 'flow';
    private const TYPE_VERSION = 'flow_version';

    public function __construct(private readonly FlowStore $store)
    {
    }

    public function save(Flow $flow): Flow
    {
        $record = $flow->id > 0 ? $this->store->find($flow->id) : null;
        if ($record === null && $this->store->findBySlug(self::TYPE_FLOW, $flow->slug) !== null) {
            throw new DomainException(__('A flow already uses this slug.', 'corex'));
        }

        $payload = $this->flowPayload($flow);
        if ($record === null) {
            $id = $this->store->create(self::TYPE_FLOW, $flow->slug, $flow->name, 0, $payload);

            return $this->flow($this->requireRecord($id, self::TYPE_FLOW));
        }
        if ($record['type'] !== self::TYPE_FLOW || ! $this->store->update($flow->id, $flow->name, $payload)) {
            throw new DomainException(__('Flow metadata could not be updated.', 'corex'));
        }

        return $this->flow($this->requireRecord($flow->id, self::TYPE_FLOW));
    }

    public function appendVersion(FlowVersion $version): FlowVersion
    {
        $this->requireRecord($version->flowId, self::TYPE_FLOW);
        if ($this->findVersion($version->flowId, $version->versionNumber) !== null) {
            throw new DomainException(__('A flow version cannot be overwritten.', 'corex'));
        }

        $payload = $this->versionPayload($version);
        $id = $this->store->create(
            self::TYPE_VERSION,
            sprintf('flow-%d-v%d', $version->flowId, $version->versionNumber),
            sprintf('Flow %d version %d', $version->flowId, $version->versionNumber),
            $version->flowId,
            $payload,
        );

        return $this->version($this->requireRecord($id, self::TYPE_VERSION));
    }

    public function find(int $flowId): ?Flow
    {
        $record = $this->store->find($flowId);

        return $record === null || $record['type'] !== self::TYPE_FLOW ? null : $this->flow($record);
    }

    public function findBySlug(string $slug): ?Flow
    {
        $record = $this->store->findBySlug(self::TYPE_FLOW, $slug);

        return $record === null ? null : $this->flow($record);
    }

    /** @return list<Flow> */
    public function all(): array
    {
        return array_map($this->flow(...), $this->store->all(self::TYPE_FLOW));
    }

    public function findVersion(int $flowId, int $versionNumber): ?FlowVersion
    {
        foreach ($this->versions($flowId) as $version) {
            if ($version->versionNumber === $versionNumber) {
                return $version;
            }
        }

        return null;
    }

    /** @return list<FlowVersion> */
    public function versions(int $flowId): array
    {
        $versions = array_map($this->version(...), $this->store->all(self::TYPE_VERSION, $flowId));
        usort($versions, static fn (FlowVersion $left, FlowVersion $right): int => $left->versionNumber <=> $right->versionNumber);

        return $versions;
    }

    /** @return array<string,mixed> */
    private function flowPayload(Flow $flow): array
    {
        return [
            'uuid' => $flow->uuid,
            'description' => $flow->description,
            'state' => $flow->state,
            'owner_id' => $flow->ownerId,
            'placement_type' => $flow->placementType,
            'placement_id' => $flow->placementId,
            'current_draft_version' => $flow->currentDraftVersion,
            'published_version' => $flow->publishedVersion,
            'test_mode' => $flow->testMode,
            'created_by' => $flow->createdBy,
            'updated_by' => $flow->updatedBy,
            'created_at' => $flow->createdAt->format(DATE_ATOM),
            'updated_at' => $flow->updatedAt->format(DATE_ATOM),
            'published_at' => $flow->publishedAt?->format(DATE_ATOM),
            'closed_at' => $flow->closedAt?->format(DATE_ATOM),
            'expires_at' => $flow->expiresAt?->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function versionPayload(FlowVersion $version): array
    {
        return [
            'version_number' => $version->versionNumber,
            'schema' => $version->configuration->schema,
            'validation' => $version->configuration->validation,
            'routing' => $version->configuration->routing,
            'email_routes' => $version->configuration->emailRoutes,
            'success' => $version->configuration->success,
            'placement_snapshot' => $version->configuration->placementSnapshot,
            'protection' => $version->configuration->protection,
            'created_by' => $version->createdBy,
            'created_at' => $version->createdAt->format(DATE_ATOM),
            'checksum' => $version->checksum,
        ];
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function flow(array $record): Flow
    {
        $payload = $record['payload'];

        return new Flow(
            id: $record['id'],
            uuid: (string) ($payload['uuid'] ?? ''),
            slug: $record['slug'],
            name: $record['name'],
            description: (string) ($payload['description'] ?? ''),
            state: (string) ($payload['state'] ?? ''),
            ownerId: (int) ($payload['owner_id'] ?? 0),
            placementType: (string) ($payload['placement_type'] ?? Flow::PLACEMENT_NONE),
            placementId: isset($payload['placement_id']) ? (int) $payload['placement_id'] : null,
            currentDraftVersion: (int) ($payload['current_draft_version'] ?? 0),
            publishedVersion: (int) ($payload['published_version'] ?? 0),
            testMode: (bool) ($payload['test_mode'] ?? false),
            createdBy: (int) ($payload['created_by'] ?? 0),
            updatedBy: (int) ($payload['updated_by'] ?? 0),
            createdAt: new DateTimeImmutable((string) ($payload['created_at'] ?? '')),
            updatedAt: new DateTimeImmutable((string) ($payload['updated_at'] ?? '')),
            publishedAt: $this->date($payload['published_at'] ?? null),
            closedAt: $this->date($payload['closed_at'] ?? null),
            expiresAt: $this->date($payload['expires_at'] ?? null),
        );
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function version(array $record): FlowVersion
    {
        $payload = $record['payload'];

        return new FlowVersion(
            id: $record['id'],
            flowId: $record['parentId'],
            versionNumber: (int) ($payload['version_number'] ?? 0),
            configuration: new FlowConfiguration(
                schema: array_values((array) ($payload['schema'] ?? [])),
                validation: (array) ($payload['validation'] ?? []),
                routing: (array) ($payload['routing'] ?? []),
                emailRoutes: (array) ($payload['email_routes'] ?? []),
                success: (array) ($payload['success'] ?? []),
                placementSnapshot: (array) ($payload['placement_snapshot'] ?? []),
                protection: FlowProtection::normalize($payload['protection'] ?? []),
            ),
            createdBy: (int) ($payload['created_by'] ?? 0),
            createdAt: new DateTimeImmutable((string) ($payload['created_at'] ?? '')),
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }

    /** @return array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} */
    private function requireRecord(int $id, string $type): array
    {
        $record = $this->store->find($id);
        if ($record === null || $record['type'] !== $type) {
            throw new DomainException(__('Flow record was not found.', 'corex'));
        }

        return $record;
    }
}
