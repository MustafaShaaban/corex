<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Data\DataWriteAdapter;
use Corex\Database\Schema\ManagedTable;
use Corex\Operations\OperationResult;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * Turns a managed table's *declaration* into the write boundary the import and mutation services
 * already expect (spec 074, FR-3.4).
 *
 * The declaration is the authority on what may be written: this adapter passes only the declared
 * writable columns to the writer, so a value for any other column is discarded no matter how it got
 * into the payload. That is the whole point of making writability opt-in — an audit table that
 * declares nothing cannot be written to even if something calls this by mistake.
 */
final class ManagedTableWriteAdapter implements DataWriteAdapter
{
    public function __construct(
        private readonly ManagedTable $table,
        private readonly TableDataWriter $writer,
    ) {
    }

    /** @param array<string,mixed> $values */
    public function create(array $values): OperationResult
    {
        // Defaults first, so the payload can never override them: they cover the columns nobody
        // may import into, and letting an import reach them would be the same hole by another
        // route. The writer is still told only the union of columns it may touch.
        $defaults = $this->table->insertDefaults();
        $columns  = [...$this->table->writableFieldIds(), ...array_keys($defaults)];

        $id = $this->writer->insert($this->table->name, $columns, [...$values, ...$defaults]);

        return $id > 0
            ? $this->completed('The record was created.', [$id])
            : $this->failed('The record could not be created.');
    }

    /** @param list<int|string> $recordIds @param array<string,mixed> $values */
    public function update(array $recordIds, array $values): OperationResult
    {
        $ids     = array_map('intval', $recordIds);
        $updated = $this->writer->update($this->table->name, $this->table->writableFieldIds(), $ids, $values);

        return $updated > 0
            ? $this->completed('The records were updated.', $ids)
            : $this->failed('No record was updated.');
    }

    /** @param list<int|string> $recordIds */
    public function delete(array $recordIds): OperationResult
    {
        $ids     = array_map('intval', $recordIds);
        $deleted = $this->writer->delete($this->table->name, $ids);

        return $deleted > 0
            ? $this->completed('The records were deleted.', $ids)
            : $this->failed('No record was deleted.');
    }

    /** @param list<int|string> $affected */
    private function completed(string $message, array $affected): OperationResult
    {
        $now = new DateTimeImmutable('now');

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_COMPLETED,
            message: $message,
            errors: [],
            affectedIds: $affected,
            startedAt: $now,
            finishedAt: $now,
        );
    }

    private function failed(string $message): OperationResult
    {
        $now = new DateTimeImmutable('now');

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_FAILED,
            message: $message,
            errors: [['code' => 'managed_table_write_failed', 'message' => $message]],
            affectedIds: [],
            startedAt: $now,
            finishedAt: $now,
        );
    }
}
