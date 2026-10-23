<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

/**
 * The name a person reads for a CoreX screen, from either of the keys a refusal is recorded
 * under: the section key a screen opens with (`settings`) or its page slug
 * (`corex-settings-config`). The access audit tab, the activity log and the Insights security
 * card name a refused screen with it (spec 114), so all three say the same word for it.
 */
final class ScreenNames
{
    /** The screen's name, or the key itself for a screen CoreX does not know. */
    public static function of(string $section): string
    {
        return self::names()[$section] ?? $section;
    }

    /** @return array<string,string> */
    private static function names(): array
    {
        return [
            'overview'                  => __('Overview', 'corex'),
            'corex-settings'            => __('Overview', 'corex'),
            'addons'                    => __('Add-ons', 'corex'),
            'corex-addons'              => __('Add-ons', 'corex'),
            'forms'                     => __('Forms & Flows', 'corex'),
            'corex-forms'               => __('Forms & Flows', 'corex'),
            'submissions'               => __('Submissions', 'corex'),
            'corex-submissions'         => __('Submissions', 'corex'),
            'email'                     => __('Email Studio', 'corex'),
            'corex-email-studio'        => __('Email Studio', 'corex'),
            'data'                      => __('Data', 'corex'),
            'corex-data'                => __('Data', 'corex'),
            'data-models'               => __('Data Models', 'corex'),
            'corex-data-models'         => __('Data Models', 'corex'),
            'operations-security'       => __('Operations & Security', 'corex'),
            'corex-operations-security' => __('Operations & Security', 'corex'),
            'notifications'             => __('Notifications', 'corex'),
            'corex-notifications'       => __('Notifications', 'corex'),
            'access'                    => __('Access & Abilities', 'corex'),
            'corex-access'              => __('Access & Abilities', 'corex'),
            'blog-pro'                  => __('Blog Pro', 'corex'),
            'corex-blog-pro'            => __('Blog Pro', 'corex'),
            'settings'                  => __('Settings', 'corex'),
            'corex-settings-config'     => __('Settings', 'corex'),
            'insights'                  => __('Insights', 'corex'),
            'corex-insights'            => __('Insights', 'corex'),
            'setup'                     => __('Setup Wizard', 'corex'),
            'corex-setup'               => __('Setup Wizard', 'corex'),
            'releases'                  => __('Releases', 'corex'),
            'corex-releases'            => __('Releases', 'corex'),
            'guides'                    => __('Guides', 'corex'),
            'corex-guides'              => __('Guides', 'corex'),
        ];
    }
}
