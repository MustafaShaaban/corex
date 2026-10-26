<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\Teams\TeamService;
use Corex\Forms\Submission\SubmissionRepository;
use WP_User;

/**
 * Tells the inbox who routing assigned work to (spec 122, FR-013; DECISIONS #473).
 *
 * - `corex_submission_actor_teams`: the person's CoreX teams join the teams their inbox scope
 *   matches, beside any a site adds.
 * - `corex_submission_assigned_access`: a person who does not manage submissions opens an inbox of
 *   what is theirs when they belong to a team or something is assigned to them or to their role.
 */
final readonly class AssignedAccess
{
    public function __construct(
        private TeamService $teams,
        private SubmissionRepository $submissions,
    ) {
    }

    public function register(): void
    {
        add_filter('corex_submission_actor_teams', [$this, 'teams'], 10, 2);
        add_filter('corex_submission_assigned_access', [$this, 'hasAssigned'], 10, 2);
    }

    /**
     * @param mixed $teams What the filter carries so far.
     * @return list<string>
     */
    public function teams(mixed $teams, int $actorId): array
    {
        $given = is_array($teams) ? array_values(array_filter($teams, 'is_string')) : [];

        return array_values(array_unique([...$given, ...$this->teams->teamsOf($actorId)]));
    }

    public function hasAssigned(mixed $allowed, int $actorId): bool
    {
        if ($allowed === true) {
            return true;
        }
        if ($this->teams->teamsOf($actorId) !== []) {
            return true;
        }
        $user = get_userdata($actorId);

        return $user instanceof WP_User
            && $this->submissions->hasAssigned($actorId, array_values(array_map('strval', (array) $user->roles)));
    }
}
