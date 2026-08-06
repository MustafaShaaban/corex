<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Keeps every activation consumer on one WordPress truth while remaining usable
 * before the application container exists.
 */
final class WpPluginActivationInspector implements PluginActivationInspector, SiteScoped
{
    /**
     * @var array<string, ActivationScope>|null
     */
    private ?array $memo = null;

    public function __construct(private readonly MultisiteContext $multisite)
    {
    }

    public function scopeOf(string $pluginFile): ActivationScope
    {
        return $this->scopes()[$pluginFile] ?? ActivationScope::None;
    }

    public function isActive(string $pluginFile): bool
    {
        return $this->scopeOf($pluginFile)->isActive();
    }

    public function activePluginFiles(): array
    {
        $network = [];
        $other = [];

        foreach ($this->scopes() as $pluginFile => $scope) {
            if ($scope === ActivationScope::Network) {
                $network[] = $pluginFile;
            } else {
                $other[] = $pluginFile;
            }
        }

        return [...$network, ...$other];
    }

    public function scopes(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $siteFiles = function_exists('get_option')
            ? array_map('strval', (array) get_option('active_plugins', []))
            : [];
        $networkFiles = [];
        $multisiteEnabled = $this->multisite->enabled();

        if ($multisiteEnabled && function_exists('get_site_option')) {
            $networkFiles = array_map(
                'strval',
                array_keys((array) get_site_option('active_sitewide_plugins', [])),
            );
        }

        $scopes = [];
        foreach ($networkFiles as $pluginFile) {
            $scopes[$pluginFile] = ActivationScope::Network;
        }
        foreach ($siteFiles as $pluginFile) {
            if (! isset($scopes[$pluginFile])) {
                $scopes[$pluginFile] = ActivationScope::Site;
            }
        }

        $siteId = $multisiteEnabled && function_exists('get_current_blog_id')
            ? (int) get_current_blog_id()
            : 1;
        $filtered = function_exists('apply_filters')
            ? apply_filters('corex_plugin_activation_scopes', $scopes, $siteId)
            : $scopes;

        $this->memo = [];
        foreach ((array) $filtered as $pluginFile => $scope) {
            if (is_string($pluginFile) && $scope instanceof ActivationScope) {
                $this->memo[$pluginFile] = $scope;
            }
        }

        return $this->memo;
    }

    public function forgetSiteState(int $siteId): void
    {
        $this->memo = null;
    }
}
