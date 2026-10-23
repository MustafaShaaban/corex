<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * What a CoreX ability, area and risk are called in the site's language (spec 117, US-ACC-05).
 *
 * The catalogue keeps its English text as data, built before WordPress has loaded any translation; the
 * names people read are given here, at the moment they are shown, through the translation functions
 * with literal strings a translator's tools can find. An ability registered by somebody else has no
 * entry and keeps the text it was registered with.
 *
 * Static, like {@see \Corex\Config\AdminUi\CorexScreens}: a lookup with no state and no input or output
 * has nothing a test would replace, and the denied page that needs it has no constructor argument
 * left to give it.
 */
final class AbilityNames
{
    /** The ability's name, or the text it was registered with when CoreX does not ship it. */
    public static function label(string $key, string $fallback = ''): string
    {
        return self::names()[$key][0] ?? ($fallback !== '' ? $fallback : $key);
    }

    /** One sentence saying what the ability allows. */
    public static function description(string $key, string $fallback = ''): string
    {
        return self::names()[$key][1] ?? $fallback;
    }

    public static function labelOf(CorexAbility $ability): string
    {
        return self::label($ability->key, $ability->label);
    }

    public static function descriptionOf(CorexAbility $ability): string
    {
        return self::description($ability->key, $ability->description);
    }

    /** An area's name, such as "Forms & Flows". An unknown area is named by its key, title-cased. */
    public static function area(string $group): string
    {
        return match ($group) {
            CorexAbility::GROUP_ADMIN         => __('CoreX admin', 'corex'),
            CorexAbility::GROUP_FORMS         => __('Forms & Flows', 'corex'),
            CorexAbility::GROUP_SUBMISSIONS   => __('Submissions', 'corex'),
            CorexAbility::GROUP_DATA          => __('Data', 'corex'),
            CorexAbility::GROUP_DATA_MODELS   => __('Data models', 'corex'),
            CorexAbility::GROUP_EMAIL         => __('Email Studio', 'corex'),
            CorexAbility::GROUP_BLOG          => __('Blog Pro', 'corex'),
            CorexAbility::GROUP_OPERATIONS    => __('Operations & Security', 'corex'),
            CorexAbility::GROUP_NOTIFICATIONS => __('Notifications', 'corex'),
            CorexAbility::GROUP_SETUP         => __('Setup Wizard', 'corex'),
            CorexAbility::GROUP_SETTINGS      => __('Settings', 'corex'),
            default                           => ucwords(str_replace('-', ' ', $group)),
        };
    }

    /** A risk in words: Normal, Sensitive, Dangerous or Critical. */
    public static function risk(string $risk): string
    {
        return match ($risk) {
            CorexAbility::RISK_SENSITIVE => __('Sensitive', 'corex'),
            CorexAbility::RISK_DANGEROUS => __('Dangerous', 'corex'),
            CorexAbility::RISK_CRITICAL  => __('Critical', 'corex'),
            default                      => __('Normal', 'corex'),
        };
    }

    /** @return array<string,array{0:string,1:string}> */
    private static function names(): array
    {
        return [
            CorexAbility::MANAGE_ADMIN => [
                __('Access CoreX admin', 'corex'),
                __('Access every CoreX product area.', 'corex'),
            ],
            CorexAbility::MANAGE_ACCESS => [
                __('Manage access', 'corex'),
                __('Grant and review CoreX abilities.', 'corex'),
            ],
            CorexAbility::MANAGE_FORMS => [
                __('Manage forms and flows', 'corex'),
                __('Create, publish, and operate forms and flows.', 'corex'),
            ],
            CorexAbility::MANAGE_SUBMISSIONS => [
                __('Manage submissions', 'corex'),
                __('Read and process form submissions.', 'corex'),
            ],
            CorexAbility::MANAGE_DATA => [
                __('Manage data', 'corex'),
                __('Explore, export, and change supported CoreX data.', 'corex'),
            ],
            CorexAbility::MANAGE_DATA_MODELS => [
                __('Manage data models', 'corex'),
                __('Import, migrate, and configure CoreX data models.', 'corex'),
            ],
            CorexAbility::EXPORT_PERSONAL_DATA => [
                __('Export personal data', 'corex'),
                __('Take personal data out of the site in an exported file.', 'corex'),
            ],
            CorexAbility::MANAGE_EMAIL => [
                __('Manage Email Studio', 'corex'),
                __('Configure, test, and inspect CoreX email.', 'corex'),
            ],
            CorexAbility::MANAGE_BLOG => [
                __('Manage Blog Pro', 'corex'),
                __('Configure editorial and analytics workflows.', 'corex'),
            ],
            CorexAbility::MANAGE_OPERATIONS => [
                __('Manage operations and security', 'corex'),
                __('Configure operating modes, security, and recovery.', 'corex'),
            ],
            CorexAbility::MANAGE_RELEASES => [
                __('Install releases', 'corex'),
                __('Install a release of the site from the admin, and go back to the one before.', 'corex'),
            ],
            CorexAbility::RUN_DANGEROUS_ACTIONS => [
                __('Run dangerous actions', 'corex'),
                __('Confirm production-impacting and destructive operations.', 'corex'),
            ],
            CorexAbility::MANAGE_NOTIFICATIONS => [
                __('Manage notifications', 'corex'),
                __('Review, resolve, and configure CoreX notifications.', 'corex'),
            ],
            CorexAbility::MANAGE_SETUP => [
                __('Manage setup', 'corex'),
                __('Run and resume the CoreX setup workflow.', 'corex'),
            ],
            CorexAbility::MANAGE_SETTINGS => [
                __('Manage settings', 'corex'),
                __('Configure CoreX product settings.', 'corex'),
            ],
            CorexAbility::VIEW_SCREENS => [
                __('See CoreX screens without changing them', 'corex'),
                __('Open Overview, Settings, Operations & Security and Insights read-only.', 'corex'),
            ],
        ];
    }
}
