<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * The check a CoreX route or form handler makes for its screen's ability (spec 117, FR-005).
 *
 * The answer is WordPress's own, `current_user_can()`. What this adds is the record: when a signed-in
 * person is refused, the refusal is published on `corex_access_refused` with the screen it belongs to,
 * and the access audit counts it the way it counts refused page views. Publishing an action, as the
 * denied page does with `corex_admin_access_denied`, keeps add-ons and corex-forms free of the Access
 * screen's classes.
 */
final class AbilityCheck
{
    /** A route call or a save refused for want of the screen's ability. */
    public const KIND_ACTION = 'action';

    /**
     * Whether the current person holds the ability; a refusal is recorded under the screen.
     *
     * @param string $section The screen's page slug, e.g. `corex-email-studio`.
     */
    public function allows(string $ability, string $section): bool
    {
        if (current_user_can($ability)) {
            return true;
        }

        if (is_user_logged_in()) {
            /**
             * A CoreX route or form refused the current person for want of an ability.
             *
             * @param string $kind    Always `action`.
             * @param string $section The screen it belongs to.
             * @param string $ability The ability it needed.
             */
            do_action('corex_access_refused', self::KIND_ACTION, $section, $ability);
        }

        return false;
    }

    /**
     * Whether the person may read the screen: its ability, or the view ability on a screen that opens
     * read-only. Refusals are recorded the same way.
     */
    public function allowsReading(string $ability, string $section): bool
    {
        if (ScreenAbilities::opensReadOnly($section) && current_user_can(CorexAbility::VIEW_SCREENS)) {
            return true;
        }

        return $this->allows($ability, $section);
    }
}
