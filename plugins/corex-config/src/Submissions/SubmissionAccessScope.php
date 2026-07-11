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
