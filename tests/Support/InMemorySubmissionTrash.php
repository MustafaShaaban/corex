<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Config\Submissions\SubmissionTrashStore;
use DomainException;

/**
 * A trash that is a list beside an inbox that is a list.
 *
 * The inbox is any object with a public `$records` array keyed by submission id, which is what
 * the workflow-store fakes of these tests are. What is tied to a submission is set by the test.
 */
final class InMemorySubmissionTrash implements SubmissionTrashStore
{
    /** @var array<int,array<string,mixed>> The trashed submissions. */
    public array $records = [];

    /** @var array<int,list<int>> Each submission's uploaded files, as attachment ids. */
    public array $uploads = [];

    /** @var list<int> Attachments that cannot be removed. */
    public array $unremovable = [];

    /** @var array<int,list<string>> Each submission's email attempt ids. */
    public array $attempts = [];

    /** @var list<int> The attachments removed, in order. */
    public array $forgotten = [];

    /** @var list<int> The submissions deleted for good, in order. */
    public array $deleted = [];

    public function __construct(private object $inbox)
    {
    }

    public function trash(int $id, int $actorId, string $via): void
    {
        $this->records[$id] = [...$this->inbox->records[$id], 'trashed' => true, 'trashed_by' => $actorId, 'trashed_via' => $via];
        unset($this->inbox->records[$id]);
    }

    public function restore(int $id): void
    {
        $this->inbox->records[$id] = array_diff_key($this->records[$id], ['trashed' => 1, 'trashed_by' => 1, 'trashed_via' => 1]);
        unset($this->records[$id]);
    }

    public function findTrashed(int $id): ?array
    {
        return $this->records[$id] ?? null;
    }

    public function uploadsOf(int $id): array
    {
        return $this->uploads[$id] ?? [];
    }

    public function forgetUpload(int $attachmentId): bool
    {
        if (in_array($attachmentId, $this->unremovable, true)) {
            return false;
        }
        $this->forgotten[] = $attachmentId;

        return true;
    }

    public function emailAttemptsOf(int $id): array
    {
        return $this->attempts[$id] ?? [];
    }

    public function delete(int $id): void
    {
        if (! isset($this->records[$id])) {
            throw new DomainException('Submission was not found in the trash.');
        }
        unset($this->records[$id]);
        $this->deleted[] = $id;
    }
}
