<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginAddressState;

/**
 * The Overview's first row (spec 118, US-OVR-04, OVR-D2): four tiles that say the site's state
 * where the post and page counts were. Site health, Environment, Login protection and Add-ons, each
 * with a state word, a tone, an icon and one line of detail, and a link to where it is looked at.
 * A fact that could not be read arrives as null and its tile says "Could not check". Pure, so it is
 * unit-testable.
 */
final class OverviewTiles
{
    /**
     * @param array{
     *   health: array{issues:list<string>,fatal:array{date:string,source:string}|null}|null,
     *   environment: array{label:string,detail:string,wpType:string,differs:bool}|null,
     *   login: string|null,
     *   addons: array{active:int,total:int}|null
     * } $facts
     *
     * @return list<array{key:string,label:string,value:string,tone:string,icon:string,detail:string,meta:string,href:string,link:string}>
     */
    public function build(array $facts): array
    {
        return [
            $this->health($facts['health']),
            $this->environment($facts['environment']),
            $this->login($facts['login']),
            $this->addons($facts['addons']),
        ];
    }

    /**
     * @param array{issues:list<string>,fatal:array{date:string,source:string}|null}|null $health
     *
     * @return array<string,string>
     */
    private function health(?array $health): array
    {
        $tile = $this->tile('health', __('Site health', 'corex'), 'insights', 'site-health.php', __('Open Site Health', 'corex'));

        if ($health === null) {
            return $this->unchecked($tile);
        }

        $fatal = $health['fatal'];
        $meta  = match (true) {
            $fatal === null       => __('No PHP fatal errors in the last 7 days', 'corex'),
            $fatal['source'] === '' => sprintf(
                /* translators: %s: the date of the last PHP fatal error. */
                __('Last PHP fatal error: %s', 'corex'),
                $fatal['date'],
            ),
            default => sprintf(
                /* translators: 1: the date of the last PHP fatal error, 2: the plugin or theme it came from. */
                __('Last PHP fatal error: %1$s, in %2$s', 'corex'),
                $fatal['date'],
                $fatal['source'],
            ),
        };

        $healthy = $health['issues'] === [] && $fatal === null;

        return [
            ...$tile,
            'value'  => $healthy ? __('Healthy', 'corex') : __('Needs attention', 'corex'),
            'tone'   => $healthy ? 'success' : 'warning',
            'detail' => $health['issues'] === []
                ? __('CoreX\'s health checks pass', 'corex')
                : implode(' ', $health['issues']),
            'meta'   => $meta,
        ];
    }

    /**
     * The mode in the Operations screen's words, and the WordPress environment beside it when the
     * two differ.
     *
     * @param array{label:string,detail:string,wpType:string,differs:bool}|null $environment
     *
     * @return array<string,string>
     */
    private function environment(?array $environment): array
    {
        $tile = $this->tile(
            'environment',
            __('Environment', 'corex'),
            'settings',
            'admin.php?page=corex-operations-security&tab=environment',
            __('Open Operations & Security', 'corex'),
        );

        if ($environment === null) {
            return $this->unchecked($tile);
        }

        return [
            ...$tile,
            'value'  => $environment['label'],
            'tone'   => $environment['differs'] ? 'warning' : 'info',
            'detail' => $environment['differs']
                ? sprintf(
                    /* translators: %s: the WordPress environment type, e.g. production. */
                    __('WordPress environment: %s', 'corex'),
                    $environment['wpType'],
                )
                : $environment['detail'],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function login(?string $state): array
    {
        $tile = $this->tile(
            'login',
            __('Login protection', 'corex'),
            'security',
            'admin.php?page=corex-operations-security&tab=login',
            __('Open the Security Center', 'corex'),
        );

        return match ($state) {
            null => $this->unchecked($tile),
            LoginAddressState::OFF => [...$tile, 'value' => __('Off', 'corex'), 'tone' => 'warning', 'detail' => __('Sign-in attempts are not limited', 'corex')],
            LoginAddressState::HIDDEN => [...$tile, 'value' => __('On', 'corex'), 'tone' => 'success', 'detail' => __('Login address hidden', 'corex')],
            LoginAddressState::RECOVERY => [...$tile, 'value' => __('On', 'corex'), 'tone' => 'warning', 'detail' => __('Recovery active: login address reachable', 'corex')],
            default => [...$tile, 'value' => __('On', 'corex'), 'tone' => 'info', 'detail' => __('Login address reachable', 'corex')],
        };
    }

    /**
     * @param array{active:int,total:int}|null $addons
     *
     * @return array<string,string>
     */
    private function addons(?array $addons): array
    {
        $tile = $this->tile('addons', __('Add-ons', 'corex'), 'addons', 'admin.php?page=corex-addons', __('Open Add-ons', 'corex'));

        if ($addons === null) {
            return $this->unchecked($tile);
        }

        return [
            ...$tile,
            'value'  => sprintf(
                /* translators: 1: active add-ons, 2: installed add-ons. */
                _n('%1$s of %2$s active', '%1$s of %2$s active', $addons['total'], 'corex'),
                number_format_i18n($addons['active']),
                number_format_i18n($addons['total']),
            ),
            'tone'   => 'info',
            'detail' => __('CoreX add-ons on this site', 'corex'),
        ];
    }

    /**
     * @return array{key:string,label:string,value:string,tone:string,icon:string,detail:string,meta:string,href:string,link:string}
     */
    private function tile(string $key, string $label, string $icon, string $href, string $link): array
    {
        return [
            'key'    => $key,
            'label'  => $label,
            'value'  => '',
            'tone'   => 'neutral',
            'icon'   => $icon,
            'detail' => '',
            'meta'   => '',
            'href'   => $href,
            'link'   => $link,
        ];
    }

    /**
     * @param array<string,string> $tile
     *
     * @return array<string,string>
     */
    private function unchecked(array $tile): array
    {
        return [...$tile, 'value' => __('Could not check', 'corex'), 'tone' => 'warning'];
    }
}
