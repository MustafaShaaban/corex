<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Hooks;

defined('ABSPATH') || exit;

/**
 * Declares the WordPress actions and filters a class responds to, so the
 * framework can wire them on its behalf (spec FR-015).
 */
interface SubscribesToHooks
{
    /**
     * @return array<string, string|array{0: string, 1?: int, 2?: int}>
     *   'hook_name' => 'method'
     *   'hook_name' => ['method', $priority = 10, $accepted_args = 1]
     */
    public function hooks(): array;
}
