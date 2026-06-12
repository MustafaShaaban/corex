<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * Pure classifier: decides, per declared page, whether a kit apply should create / adopt / skip it — from
 * the declared pages plus a per-slug signal (does it exist, is it empty, is it an un-populated kit placeholder).
 * The caller (the WP boundary) supplies the signals; this never touches WordPress, so every case is a headless
 * table test (spec 041 FR-007). Replaces the old binary `KitPagePlanner::toCreate()`.
 */
final class PagePlanner
{
    /**
     * @param list<array{title:string,slug:string,content:string,front?:bool}>          $declared
     * @param array<string,array{exists:bool,isEmpty:bool,isKitPlaceholder:bool}>        $signals  keyed by slug
     *
     * @return list<PageDisposition>
     */
    public function plan(array $declared, array $signals): array
    {
        $dispositions = [];

        foreach ($declared as $page) {
            $signal = $signals[$page['slug']] ?? ['exists' => false, 'isEmpty' => false, 'isKitPlaceholder' => false];

            [$action, $reason] = $this->classify($signal);

            $dispositions[] = new PageDisposition($page['slug'], $page['title'], $action, $reason);
        }

        return $dispositions;
    }

    /**
     * @param array{exists:bool,isEmpty:bool,isKitPlaceholder:bool} $signal
     *
     * @return array{0:string,1:string} [action, reason]
     */
    private function classify(array $signal): array
    {
        if (! $signal['exists']) {
            return [PageDisposition::CREATE, 'slug_absent'];
        }

        if ($signal['isKitPlaceholder']) {
            return [PageDisposition::ADOPT, 'kit_placeholder'];
        }

        if ($signal['isEmpty']) {
            return [PageDisposition::ADOPT, 'existing_empty'];
        }

        return [PageDisposition::SKIP, 'user_content'];
    }
}
