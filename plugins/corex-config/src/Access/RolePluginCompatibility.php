<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

/**
 * Detects external role/capability plugins and describes CoreX coexistence behavior.
 */
final class RolePluginCompatibility
{
    private const PERMISSION_PLUGINS = [
        'user-role-editor/'            => 'User Role Editor',
        'members/'                     => 'Members',
        'capability-manager-enhanced/' => 'PublishPress Capabilities',
        'advanced-access-manager/'     => 'Advanced Access Manager',
        'wpfront-user-role-editor/'    => 'WPFront User Role Editor',
    ];

    /**
     * @param list<string> $activePlugins
     *
     * @return list<string>
     */
    public function detect(array $activePlugins): array
    {
        $found = [];
        foreach ($activePlugins as $basename) {
            foreach (self::PERMISSION_PLUGINS as $prefix => $name) {
                if (str_starts_with((string) $basename, $prefix)) {
                    $found[] = $name;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @param list<string> $activePlugins
     *
     * @return array{plugins:list<string>,nativeCapabilitiesEditable:bool,corexAbilitiesEditable:bool,message:string}
     */
    public function coexistence(array $activePlugins): array
    {
        $plugins = $this->detect($activePlugins);

        return [
            'plugins' => $plugins,
            'nativeCapabilitiesEditable' => $plugins === [],
            'corexAbilitiesEditable' => true,
            'message' => $plugins === []
                ? __('CoreX can manage CoreX-owned abilities. Native WordPress capabilities remain compatibility inputs.', 'corex')
                : __('An external role plugin is active. CoreX keeps native platform capabilities read-only and manages only CoreX-owned abilities.', 'corex'),
        ];
    }
}
