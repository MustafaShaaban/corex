<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;

/**
 * Resolves the current WordPress user's Inbox scope without exposing records first.
 *
 * Whoever manages submissions sees every one, as before. Someone who does not, but to whom
 * routing gave work (a team member, or a person with something assigned to them or their role),
 * gets an inbox of exactly that, with none of the managers' extra rights (spec 122, FR-013;
 * DECISIONS #473). The forms engine answers `corex_submission_assigned_access`; without it,
 * nobody but managers gets a scope, as before.
 */
final class WpSubmissionAccessPolicy implements SubmissionAccessPolicy
{
    public function scopeFor(int $actorId): ?SubmissionAccessScope
    {
        if ($actorId < 1 || get_current_user_id() !== $actorId) {
            return null;
        }
        if (! $this->authorized()) {
            return $this->assignedOnly($actorId);
        }

        $user = wp_get_current_user();
        $roles = array_values(array_filter(array_map('sanitize_key', (array) $user->roles)));
        $teams = apply_filters('corex_submission_actor_teams', [], $actorId);

        return new SubmissionAccessScope(
            actorId: $actorId,
            manageAll: (bool) apply_filters('corex_submission_manage_all', true, $actorId),
            teamKeys: $this->keys($teams),
            roleKeys: $roles,
            canViewRestrictedNotes: (bool) apply_filters(
                'corex_submission_view_restricted_notes',
                current_user_can('manage_options'),
                $actorId,
            ),
            // The one right the Data screen asks for too, which Access & Abilities can give. It
            // was an administrator and nobody else, short of this filter (spec 109, D3).
            canExportPersonalData: (bool) apply_filters(
                'corex_submission_export_personal_data',
                current_user_can(CorexAbility::EXPORT_PERSONAL_DATA) || current_user_can('manage_options'),
                $actorId,
            ),
            // Managing an inbox and destroying its records are different responsibilities. The
            // ability is the one the Access screen grants for actions that cannot be undone.
            canDeletePermanently: (bool) apply_filters(
                'corex_submission_delete_permanently',
                current_user_can(CorexAbility::RUN_DANGEROUS_ACTIONS) || current_user_can('manage_options'),
                $actorId,
            ),
        );
    }

    private function authorized(): bool
    {
        return current_user_can(CorexAbility::MANAGE_SUBMISSIONS) || current_user_can('manage_options');
    }

    /** What is assigned to them, their teams and their roles, and nothing more; or no inbox at all. */
    private function assignedOnly(int $actorId): ?SubmissionAccessScope
    {
        if (! (bool) apply_filters('corex_submission_assigned_access', false, $actorId)) {
            return null;
        }
        $user = wp_get_current_user();

        return new SubmissionAccessScope(
            actorId: $actorId,
            manageAll: false,
            teamKeys: $this->keys(apply_filters('corex_submission_actor_teams', [], $actorId)),
            roleKeys: array_values(array_filter(array_map('sanitize_key', (array) $user->roles))),
        );
    }

    /** @return list<string> */
    private function keys(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter(array_map('sanitize_key', $values)))
            : [];
    }
}
