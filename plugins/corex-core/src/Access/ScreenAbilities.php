<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * Which CoreX ability each CoreX screen needs (spec 117, FR-001).
 *
 * One map, read by the screen's menu entry, its render check, its routes, the denied page's request
 * and the Access screen's requirements card, so none of them can name a different requirement. A
 * screen marked `readOnly` also opens, without its controls, for the holder of
 * {@see CorexAbility::VIEW_SCREENS}.
 *
 * Guides is not here: it opens for anyone signed in, and each guide asks for its own screen's ability.
 * Static for the reason {@see AbilityNames} gives.
 */
final class ScreenAbilities
{
    /** @var array<string,array{section:string,ability:string,readOnly:bool}> */
    private const SCREENS = [
        'corex-settings'            => ['section' => 'overview', 'ability' => CorexAbility::MANAGE_ADMIN, 'readOnly' => true],
        'corex-addons'              => ['section' => 'addons', 'ability' => CorexAbility::MANAGE_ADMIN, 'readOnly' => false],
        'corex-forms'               => ['section' => 'forms', 'ability' => CorexAbility::MANAGE_FORMS, 'readOnly' => false],
        'corex-submissions'         => ['section' => 'submissions', 'ability' => CorexAbility::MANAGE_SUBMISSIONS, 'readOnly' => false],
        'corex-email-studio'        => ['section' => 'email', 'ability' => CorexAbility::MANAGE_EMAIL, 'readOnly' => false],
        // Records need "Manage data" and everything else on the screen "Manage data models"; either
        // opens it. Records is what most people ask for, so it is the one a request names.
        'corex-data-models'         => ['section' => 'data-models', 'ability' => CorexAbility::MANAGE_DATA, 'readOnly' => false],
        'corex-operations-security' => ['section' => 'operations-security', 'ability' => CorexAbility::MANAGE_OPERATIONS, 'readOnly' => true],
        'corex-notifications'       => ['section' => 'notifications', 'ability' => CorexAbility::MANAGE_NOTIFICATIONS, 'readOnly' => false],
        'corex-releases'            => ['section' => 'releases', 'ability' => CorexAbility::MANAGE_RELEASES, 'readOnly' => false],
        'corex-access'              => ['section' => 'access', 'ability' => CorexAbility::MANAGE_ACCESS, 'readOnly' => false],
        'corex-blog-pro'            => ['section' => 'blog-pro', 'ability' => CorexAbility::MANAGE_BLOG, 'readOnly' => false],
        'corex-insights'            => ['section' => 'insights', 'ability' => CorexAbility::MANAGE_ADMIN, 'readOnly' => true],
        'corex-setup'               => ['section' => 'setup', 'ability' => CorexAbility::MANAGE_SETUP, 'readOnly' => false],
        'corex-settings-config'     => ['section' => 'settings', 'ability' => CorexAbility::MANAGE_SETTINGS, 'readOnly' => true],
    ];

    /** The ability a screen needs, by its page slug or its section key; "Access CoreX admin" for anything else. */
    public static function abilityFor(string $slugOrSection): string
    {
        return self::find($slugOrSection)['ability'] ?? CorexAbility::MANAGE_ADMIN;
    }

    /** Whether the screen opens read-only for {@see CorexAbility::VIEW_SCREENS}. */
    public static function opensReadOnly(string $slugOrSection): bool
    {
        return self::find($slugOrSection)['readOnly'] ?? false;
    }

    /**
     * Every screen, page slug first, in menu order.
     *
     * @return array<string,array{section:string,ability:string,readOnly:bool}>
     */
    public static function all(): array
    {
        return self::SCREENS;
    }

    /** A screen's name in the site's language, by its page slug or section; the slug when it is none of CoreX's. */
    public static function name(string $slugOrSection): string
    {
        $slug = isset(self::SCREENS[$slugOrSection]) ? $slugOrSection : self::slugOf($slugOrSection);

        return match ($slug) {
            'corex-settings'            => __('Overview', 'corex'),
            'corex-addons'              => __('Add-ons', 'corex'),
            'corex-forms'               => __('Forms & Flows', 'corex'),
            'corex-submissions'         => __('Submissions', 'corex'),
            'corex-email-studio'        => __('Email Studio', 'corex'),
            'corex-data-models'         => __('Data', 'corex'),
            'corex-operations-security' => __('Operations & Security', 'corex'),
            'corex-notifications'       => __('Notifications', 'corex'),
            'corex-releases'            => __('Releases', 'corex'),
            'corex-access'              => __('Access & Abilities', 'corex'),
            'corex-blog-pro'            => __('Blog Pro', 'corex'),
            'corex-insights'            => __('Insights', 'corex'),
            'corex-setup'               => __('Setup Wizard', 'corex'),
            'corex-settings-config'     => __('Settings', 'corex'),
            default                     => $slugOrSection,
        };
    }

    private static function slugOf(string $section): ?string
    {
        foreach (self::SCREENS as $slug => $screen) {
            if ($screen['section'] === $section) {
                return $slug;
            }
        }

        return null;
    }

    /** @return array{section:string,ability:string,readOnly:bool}|null */
    private static function find(string $slugOrSection): ?array
    {
        if (isset(self::SCREENS[$slugOrSection])) {
            return self::SCREENS[$slugOrSection];
        }

        foreach (self::SCREENS as $screen) {
            if ($screen['section'] === $slugOrSection) {
                return $screen;
            }
        }

        return null;
    }
}
