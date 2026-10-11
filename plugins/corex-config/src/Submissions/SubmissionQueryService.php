<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Permission-first query facade for Inbox rows, counts, and detail.
 */
final readonly class SubmissionQueryService
{
    /**
     * @param SubmissionQuestions|null  $questions Asked for a form's wording, when there is somebody to ask.
     * @param SubmissionOwnerNames|null $owners    Asked who an owner is, and who can be one.
     * @param SubmissionFileAnswers|null $files    Asked which answer is an uploaded file.
     */
    public function __construct(
        private SubmissionInboxReader $reader,
        private SubmissionAccessPolicy $access,
        private ?SubmissionQuestions $questions = null,
        private ?SubmissionOwnerNames $owners = null,
        private ?SubmissionTrashRetention $trash = null,
        private ?SubmissionReplyView $replies = null,
        private ?SubmissionFileAnswers $files = null,
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,can_delete_permanently:bool,trash_days:int} */
    public function query(int $actorId, SubmissionInboxQuery $query): array
    {
        $scope = $this->scope($actorId);
        $page  = $this->reader->queryInbox($query, $scope);

        return [
            'items' => $page['items'],
            'total' => max(0, $page['total']),
            'page' => $query->page,
            'per_page' => $query->perPage,
            // So the inbox can offer a permanent delete, or say why it does not (spec 105, FR-010).
            'can_delete_permanently' => $scope->canDeletePermanently,
            // How many days the trash keeps a submission; 0 for until somebody deletes it.
            'trash_days' => $this->trash?->retentionDays() ?? 0,
        ];
    }

    /** @return array<string,mixed>|null */
    public function detail(int $actorId, int $submissionId): ?array
    {
        $scope = $this->scope($actorId);
        $record = $this->reader->findInbox($submissionId, $scope);

        return $record !== null && $scope->allows($record) ? $this->described($record) : null;
    }

    /**
     * The file a submission holds as its answer to one field, for a person who may read that
     * submission (spec 081, FR-009). The address a file is opened at asks here on every request.
     *
     * @return array{id:int,name:string,missing:bool}|null Null when the submission is not this
     *                                                     person's to read, or the answer is no file.
     */
    public function file(int $actorId, int $submissionId, string $field): ?array
    {
        $scope  = $this->scope($actorId);
        $record = $this->reader->findInbox($submissionId, $scope);

        if ($record === null || ! $scope->allows($record)) {
            return null;
        }

        return $this->files?->file($field, ((array) ($record['values'] ?? []))[$field] ?? null, $this->questionsOf($record));
    }

    /**
     * The record, with what a person needs to read it: the questions its answers belong to, its
     * owner by name, the people it could be given to, and each uploaded file by its name with
     * the address to open it. The record's other fields are untouched.
     *
     * @param array<string,mixed> $record
     *
     * @return array<string,mixed>
     */
    private function described(array $record): array
    {
        $questions = $this->questionsOf($record);

        return ($this->files?->describedIn($record, $questions) ?? $record) + [
            'questions' => $questions,
            'owner_name' => $this->owners?->nameOf(
                (string) ($record['owner_type'] ?? 'none'),
                (string) ($record['owner_key'] ?? ''),
            ) ?? '',
            'owners' => $this->owners?->people() ?? [],
            // What was answered, and what would happen to an answer sent now (spec 106).
        ] + ($this->replies?->of((int) ($record['id'] ?? 0)) ?? []);
    }

    /**
     * @param array<string,mixed> $record
     *
     * @return list<array{key:string,label:string,type:string}>
     */
    private function questionsOf(array $record): array
    {
        return $this->questions?->for((string) ($record['form'] ?? '')) ?? [];
    }

    private function scope(int $actorId): SubmissionAccessScope
    {
        $scope = $this->access->scopeFor($actorId);
        if ($scope === null) {
            throw new DomainException('This actor is not allowed to manage submissions.');
        }

        return $scope;
    }
}
