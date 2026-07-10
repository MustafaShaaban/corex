<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Operations\OperationResult;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Validates exact source-declared mutations before issuing a single-use preview.
 */
final class DataMutationService
{
    private const MAX_BULK = 100;

    public function __construct(
        private readonly DataRegistry $registry,
        private readonly DataSourceService $sources,
        private readonly DataMutationPreviewStore $previews,
        private readonly ActivityService $activity,
    ) {
    }

    public function preview(DataMutationRequest $request): DataMutationPreview
    {
        $source = $this->sources->authorize($request->actorId, $request->sourceKey, $request->operation);
        $this->adapter($source);
        $this->assertShape($request);
        $this->assertFields($request);

        return $this->previews->issue($request);
    }

    public function apply(DataMutationApplyRequest $request): OperationResult
    {
        $preview = $this->previews->consume($request->token, $request->actorId);
        if ($preview === null) {
            throw new DomainException('The data mutation preview expired or was already used.');
        }
        if ($preview->sourceKey !== $request->sourceKey) {
            throw new DomainException('The data mutation preview does not match the requested source.');
        }

        $source = $this->sources->authorize($request->actorId, $preview->sourceKey, $preview->operation);
        $result = $this->dispatch($this->adapter($source), $preview);
        $event = $this->recordActivity($preview, $result, $request->actorLabel, $request->now);

        return new OperationResult(
            $result->operationId,
            $result->state,
            $result->message,
            $result->errors,
            $result->affectedIds,
            $result->startedAt,
            $result->finishedAt,
            $event->id,
        );
    }

    private function adapter(DataSource $source): \Corex\Data\DataWriteAdapter
    {
        if (! $source instanceof WritableDataSource) {
            throw new DomainException('The data source does not provide a write adapter.');
        }

        return $source->writeAdapter();
    }

    private function assertShape(DataMutationRequest $request): void
    {
        $count = count($request->recordIds);
        $hasValues = $request->values !== [];
        $valid = match ($request->operation) {
            DataSourceCapabilities::CREATE => $count === 0 && $hasValues,
            DataSourceCapabilities::UPDATE => $count === 1 && $hasValues,
            DataSourceCapabilities::DELETE => $count === 1 && ! $hasValues,
            DataSourceCapabilities::BULK_UPDATE => $count >= 1 && $count <= self::MAX_BULK && $hasValues,
            DataSourceCapabilities::BULK_DELETE => $count >= 1 && $count <= self::MAX_BULK && ! $hasValues,
            default => false,
        };
        if ($valid) {
            return;
        }

        $message = $count > self::MAX_BULK
            ? 'Data mutations are limited to 100 records per bulk preview.'
            : 'The data mutation record IDs or values do not match the operation.';
        throw new InvalidArgumentException($message);
    }

    private function assertFields(DataMutationRequest $request): void
    {
        $fields = [];
        foreach ($this->registry->fields($request->sourceKey) as $field) {
            $fields[$field->key] = $field;
        }
        foreach ($request->values as $key => $value) {
            if (! isset($fields[$key])) {
                throw new InvalidArgumentException('The data mutation contains an unknown field.');
            }
            if ($fields[$key]->readOnly) {
                throw new InvalidArgumentException('The data mutation contains a read-only field.');
            }
        }
        if ($request->operation === DataSourceCapabilities::CREATE) {
            $this->assertRequiredFields($fields, $request->values);
        }
    }

    /** @param array<string,DataField> $fields @param array<string,mixed> $values */
    private function assertRequiredFields(array $fields, array $values): void
    {
        foreach ($fields as $field) {
            if ($field->required && (! array_key_exists($field->key, $values) || $values[$field->key] === '')) {
                throw new InvalidArgumentException('The data mutation is missing a required field.');
            }
        }
    }

    private function dispatch(\Corex\Data\DataWriteAdapter $adapter, DataMutationPreview $preview): OperationResult
    {
        return match ($preview->operation) {
            DataSourceCapabilities::CREATE => $adapter->create($preview->values),
            DataSourceCapabilities::UPDATE,
            DataSourceCapabilities::BULK_UPDATE => $adapter->update($preview->recordIds, $preview->values),
            DataSourceCapabilities::DELETE,
            DataSourceCapabilities::BULK_DELETE => $adapter->delete($preview->recordIds),
            default => throw new DomainException('The data mutation operation is unsupported.'),
        };
    }

    private function recordActivity(
        DataMutationPreview $preview,
        OperationResult $result,
        string $actorLabel,
        DateTimeImmutable $now,
    ): ActivityEvent {
        $kind = match ($preview->operation) {
            DataSourceCapabilities::CREATE => 'data.record.created',
            DataSourceCapabilities::UPDATE, DataSourceCapabilities::BULK_UPDATE => 'data.record.updated',
            DataSourceCapabilities::DELETE, DataSourceCapabilities::BULK_DELETE => 'data.record.deleted',
            default => 'data.record.mutated',
        };

        return $this->activity->record(
            actorId: $preview->actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: $actorLabel,
            area: ActivityEvent::AREA_DATA,
            kind: $kind,
            targetType: 'data_source',
            targetId: $preview->sourceKey,
            targetLabel: $preview->sourceKey,
            outcome: $result->succeeded() ? ActivityEvent::OUTCOME_SUCCESS : ActivityEvent::OUTCOME_FAILURE,
            summary: ['key' => $kind, 'args' => ['source' => $preview->sourceKey]],
            context: [
                'operation' => $preview->operation,
                'requested_count' => count($preview->recordIds),
                'affected_count' => count($result->affectedIds),
            ],
            sensitivity: ActivityEvent::SENSITIVITY_RESTRICTED,
            retentionUntil: $now->modify('+180 days'),
            occurredAt: $now,
        );
    }
}
