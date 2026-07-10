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
 */
final class WpSubmissionAccessPolicy implements SubmissionAccessPolicy
{
    public function scopeFor(int $actorId): ?SubmissionAccessScope
    {
        if ($actorId < 1 || get_current_user_id() !== $actorId || ! $this->authorized()) {
            return null;
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
            canExportPersonalData: (bool) apply_filters(
                'corex_submission_export_personal_data',
                current_user_can('manage_options'),
                $actorId,
            ),
        );
    }

    private function authorized(): bool
    {
        return current_user_can(CorexAbility::MANAGE_SUBMISSIONS) || current_user_can('manage_options');
    }

    /** @return list<string> */
    private function keys(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter(array_map('sanitize_key', $values)))
            : [];
    }
}
