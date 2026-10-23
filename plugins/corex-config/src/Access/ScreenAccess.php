<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Access\ScreenAbilities;

/**
 * How the signed-in person may use a CoreX screen (spec 117, FR-001, FR-013): fully, read-only, or not
 * at all, answered from {@see ScreenAbilities}.
 */
final class ScreenAccess
{
    public const MANAGE = 'manage';
    public const VIEW   = 'view';
    public const NONE   = 'none';

    /** `manage`, `view` (the view ability on a screen that opens read-only) or `none`. */
    public function mode(string $slug): string
    {
        if (current_user_can(ScreenAbilities::abilityFor($slug))) {
            return self::MANAGE;
        }

        return ScreenAbilities::opensReadOnly($slug) && current_user_can(CorexAbility::VIEW_SCREENS)
            ? self::VIEW
            : self::NONE;
    }

    /** Whether the screen opens for this person at all. */
    public function opens(string $slug): bool
    {
        return $this->mode($slug) !== self::NONE;
    }

    /** Whether the screen opens without its controls for this person. */
    public function readOnly(string $slug): bool
    {
        return $this->mode($slug) === self::VIEW;
    }

    /**
     * The capability the screen's menu entry is registered with.
     *
     * WordPress takes one capability per menu entry and checks it again when the page is opened. The
     * view ability opens the screen only for someone who lacks its own ability, so the entry names
     * whichever of the two this person holds, and the screen's ability when they hold neither, so
     * WordPress refuses them with the access-denied page.
     */
    public function menuCapability(string $slug): string
    {
        return $this->mode($slug) === self::VIEW ? CorexAbility::VIEW_SCREENS : ScreenAbilities::abilityFor($slug);
    }
}
