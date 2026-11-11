<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Immutable record and note visibility granted to one Inbox actor.
 */
final readonly class SubmissionAccessScope
{
    /**
     * @param list<string> $teamKeys
     * @param list<string> $roleKeys
     */
    public function __construct(
        public int $actorId,
        public bool $manageAll,
        public array $teamKeys = [],
        public array $roleKeys = [],
        public bool $canViewRestrictedNotes = false,
        public bool $canExportPersonalData = false,
        /** Deleting a submission for good: apart from managing the inbox (spec 105, FR-010). */
        public bool $canDeletePermanently = false,
        /**
         * Only flow submissions: the assigned-only inbox of someone who does not manage
         * submissions is the work routing gave them, never a code form's submission a manager
         * once assigned to them (spec 122, FR-013; DECISIONS #473).
         */
        public bool $flowsOnly = false,
    ) {
        if ($this->actorId < 1) {
            throw new \InvalidArgumentException('A submission access scope requires an actor.');
        }
    }

    /** @param array<string,mixed> $record */
    public function allows(array $record): bool
    {
        if ($this->manageAll) {
            return true;
        }
        if ($this->flowsOnly && (int) ($record['flow_id'] ?? 0) < 1) {
            return false;
        }

        $type = (string) ($record['owner_type'] ?? 'none');
        $key  = (string) ($record['owner_key'] ?? '');

        return match ($type) {
            'user' => $key === (string) $this->actorId,
            'team' => in_array($key, $this->teamKeys, true),
            'role' => in_array($key, $this->roleKeys, true),
            default => false,
        };
    }
}
