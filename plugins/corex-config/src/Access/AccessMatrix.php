<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

/**
 * Pure model for Access & Abilities (spec 065 baseline, spec 067 tabs). It builds a truthful role ×
 * capability matrix, role summaries, and permissions-plugin conflict detection from the REAL WordPress
 * roles and the capabilities CoreX actually uses — it invents no `corex_*` capability that does not
 * exist (CoreX admin is gated on `manage_options`). Advanced role editing / a full capability mutation
 * editor is deliberately out of scope; this surface is read-only. WordPress-free, so it is unit-testable.
 */
final class AccessMatrix
{
    /**
     * The roles WordPress ships with; anything else on the site is a custom role.
     */
    private const CORE_ROLES = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

    /**
     * Role/capability-manager plugins CoreX recognises (design: Access overview compatibility
     * notice). Keyed by the plugin-directory prefix of the plugin basename.
     */
    private const PERMISSION_PLUGINS = [
        'user-role-editor/'            => 'User Role Editor',
        'members/'                     => 'Members',
        'capability-manager-enhanced/' => 'PublishPress Capabilities',
        'advanced-access-manager/'     => 'Advanced Access Manager',
        'wpfront-user-role-editor/'    => 'WPFront User Role Editor',
    ];

    /**
     * The capability groups shown, each mapped to the REAL WordPress capability it checks.
     * `risk` marks capabilities that unlock site administration; `locked` marks requirements
     * hard-coded in CoreX (the admin gate is `manage_options` in code — not editable anywhere).
     *
     * @return list<array{key:string,label:string,cap:string,risk:string,locked:bool}>
     */
    public function groups(): array
    {
        return [
            ['key' => 'corex_admin', 'label' => __('Access the CoreX admin', 'corex'), 'cap' => 'manage_options', 'risk' => 'high', 'locked' => true],
            ['key' => 'content', 'label' => __('Publish posts & pages', 'corex'), 'cap' => 'publish_posts', 'risk' => 'standard', 'locked' => false],
            ['key' => 'edit_others', 'label' => __('Edit others’ content', 'corex'), 'cap' => 'edit_others_posts', 'risk' => 'standard', 'locked' => false],
            ['key' => 'media', 'label' => __('Upload media', 'corex'), 'cap' => 'upload_files', 'risk' => 'standard', 'locked' => false],
            ['key' => 'taxonomy', 'label' => __('Manage categories & tags', 'corex'), 'cap' => 'manage_categories', 'risk' => 'standard', 'locked' => false],
        ];
    }

    /**
     * Role summary cards (design: Access overview) — per role, the REAL user count, whether the
     * role is a WordPress core role or a custom one, and how many tracked abilities it holds.
     *
     * @param list<array{key:string,name:string,caps:array<string,bool>}> $roles
     * @param array<string,int>                                           $userCounts role key => users
     *
     * @return list<array{key:string,name:string,isCore:bool,users:int,granted:int,total:int}>
     */
    public function roleSummaries(array $roles, array $userCounts): array
    {
        $groups = $this->groups();
        $total  = count($groups);

        $out = [];
        foreach ($roles as $role) {
            $granted = 0;
            foreach ($groups as $group) {
                if (! empty($role['caps'][$group['cap']])) {
                    $granted++;
                }
            }

            $out[] = [
                'key'     => $role['key'],
                'name'    => $role['name'],
                'isCore'  => in_array($role['key'], self::CORE_ROLES, true),
                'users'   => max(0, (int) ($userCounts[$role['key']] ?? 0)),
                'granted' => $granted,
                'total'   => $total,
            ];
        }

        return $out;
    }

    /**
     * Which known role/capability-manager plugins are REALLY active (design: overview
     * compatibility notice). Returns their display names; empty when none is active.
     *
     * @param list<string> $activePlugins plugin basenames from the active_plugins option
     *
     * @return list<string>
     */
    public function conflicts(array $activePlugins): array
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
     * Build the matrix: one row per capability group, one cell per role indicating whether that role
     * really holds the capability.
     *
     * @param list<array{key:string,name:string,caps:array<string,bool>}> $roles
     *
     * @return array{
     *   roles: list<array{key:string,name:string}>,
     *   rows: list<array{key:string,label:string,cap:string,risk:string,locked:bool,cells:array<string,bool>}>
     * }
     */
    public function build(array $roles): array
    {
        $roleHeads = array_map(
            static fn (array $r): array => ['key' => $r['key'], 'name' => $r['name']],
            $roles,
        );

        $rows = [];
        foreach ($this->groups() as $group) {
            $cells = [];
            foreach ($roles as $role) {
                $cells[$role['key']] = ! empty($role['caps'][$group['cap']]);
            }
            $rows[] = [
                'key'    => $group['key'],
                'label'  => $group['label'],
                'cap'    => $group['cap'],
                'risk'   => $group['risk'],
                'locked' => $group['locked'],
                'cells'  => $cells,
            ];
        }

        return ['roles' => $roleHeads, 'rows' => $rows];
    }

    /**
     * Which of the tracked capabilities the current user holds — for the "your permissions" summary.
     *
     * @param array<string,bool> $userCaps
     *
     * @return list<array{label:string,cap:string,granted:bool}>
     */
    public function forUser(array $userCaps): array
    {
        $out = [];
        foreach ($this->groups() as $group) {
            $out[] = [
                'label'   => $group['label'],
                'cap'     => $group['cap'],
                'granted' => ! empty($userCaps[$group['cap']]),
            ];
        }

        return $out;
    }
}
