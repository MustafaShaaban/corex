<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

/**
 * Pure model for the Access & Abilities baseline (spec 065). It builds a truthful role × capability
 * matrix from the REAL WordPress roles and the capabilities CoreX actually uses — it invents no
 * `corex_*` capability that does not exist (CoreX admin is gated on `manage_options`). Advanced role
 * editing / a full capability mutation editor is deliberately out of scope; this baseline is read-only.
 * WordPress-free, so it is unit-testable.
 */
final class AccessMatrix
{
    /**
     * The capability groups shown, each mapped to the REAL WordPress capability it checks.
     *
     * @return list<array{key:string,label:string,cap:string}>
     */
    public function groups(): array
    {
        return [
            ['key' => 'corex_admin', 'label' => __('Access the CoreX admin', 'corex'), 'cap' => 'manage_options'],
            ['key' => 'content', 'label' => __('Publish posts & pages', 'corex'), 'cap' => 'publish_posts'],
            ['key' => 'edit_others', 'label' => __('Edit others’ content', 'corex'), 'cap' => 'edit_others_posts'],
            ['key' => 'media', 'label' => __('Upload media', 'corex'), 'cap' => 'upload_files'],
            ['key' => 'taxonomy', 'label' => __('Manage categories & tags', 'corex'), 'cap' => 'manage_categories'],
        ];
    }

    /**
     * Build the matrix: one row per capability group, one cell per role indicating whether that role
     * really holds the capability.
     *
     * @param list<array{key:string,name:string,caps:array<string,bool>}> $roles
     *
     * @return array{
     *   roles: list<array{key:string,name:string}>,
     *   rows: list<array{key:string,label:string,cap:string,cells:array<string,bool>}>
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
                'key'   => $group['key'],
                'label' => $group['label'],
                'cap'   => $group['cap'],
                'cells' => $cells,
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
