<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Operations\OperationResult;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * Turns a managed table's declared migrations into the provider the migration service expects
 * (spec 074, FR-3.6).
 *
 * A definition is only ever what the table declared — nothing is inferred, generated, or guessed —
 * and `rollbackSupported` is carried through verbatim, so the workspace offers an undo exactly when
 * the declaration provides one and never otherwise.
 */
final class ManagedTableMigrationProvider implements MigrationProvider
{
    public function __construct(
        private readonly ManagedTable $table,
        private readonly TableMigrationRunner $runner,
    ) {
    }

    /** @return list<MigrationDefinition> */
    public function definitions(): array
    {
        return array_map(
            static fn (array $migration): MigrationDefinition => MigrationDefinition::from($migration),
            $this->table->migrations,
        );
    }

    public function snapshot(MigrationDefinition $definition): string
    {
        return $this->runner->snapshot($this->table->name);
    }

    public function execute(MigrationDefinition $definition, string $snapshotId, bool $rollback): OperationResult
    {
        $started = new DateTimeImmutable('now');

        if ($rollback) {
            // A definition that never promised a way back must not pretend to have taken one.
            if (! $definition->rollbackSupported) {
                return $this->result(
                    $started,
                    OperationResult::STATE_BLOCKED,
                    'This migration does not support rollback.',
                    'migration_rollback_unsupported',
                );
            }

            if ($snapshotId === '' || ! $this->runner->restore($this->table->name, $snapshotId)) {
                return $this->result(
                    $started,
                    OperationResult::STATE_FAILED,
                    'The snapshot could not be restored.',
                    'migration_restore_failed',
                );
            }

            return $this->result($started, OperationResult::STATE_COMPLETED, 'The migration was rolled back.');
        }

        // No snapshot means no way back, so the apply is refused rather than run unprotected.
        if ($snapshotId === '') {
            return $this->result(
                $started,
                OperationResult::STATE_BLOCKED,
                'The table could not be snapshotted, so the migration was not applied.',
                'migration_snapshot_failed',
            );
        }

        $expected = count($definition->plan);
        $ran      = $this->runner->runPlan($this->table->name, $definition->plan);

        if ($ran < $expected) {
            return $this->result(
                $started,
                OperationResult::STATE_PARTIAL,
                sprintf('%d of %d migration steps ran before one failed.', $ran, $expected),
                'migration_step_failed',
            );
        }

        return $this->result($started, OperationResult::STATE_COMPLETED, 'The migration was applied.');
    }

    private function result(
        DateTimeImmutable $started,
        string $state,
        string $message,
        string $errorCode = '',
    ): OperationResult {
        return new OperationResult(
            operationId: Uuid::v4(),
            state: $state,
            message: $message,
            errors: $errorCode === '' ? [] : [['code' => $errorCode, 'message' => $message]],
            affectedIds: [],
            startedAt: $started,
            finishedAt: new DateTimeImmutable('now'),
        );
    }
}
