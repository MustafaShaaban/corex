<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

defined('ABSPATH') || exit;

/**
 * Pure resolver for the Overview environment/mode badge (spec 063, Phase 1). It maps the WordPress
 * environment type — the only real, always-available operating signal at this stage — to a truthful
 * badge model. It deliberately does NOT invent an "operations mode" the framework cannot yet change:
 * the honest surface today is the environment type. When a real Operations Mode lands (Phase 4), this
 * resolver is the seam that gains the extra modes. WordPress-free, so it is unit-testable.
 */
final class EnvironmentMode
{
    public const TONE_SUCCESS = 'success';
    public const TONE_WARNING = 'warning';
    public const TONE_INFO    = 'info';

    /**
     * @return array{mode:string,label:string,tone:string,detail:string}
     */
    public function resolve(string $environmentType): array
    {
        $type = strtolower(trim($environmentType));

        return match ($type) {
            'production' => [
                'mode'   => 'production',
                'label'  => __('Production', 'corex'),
                'tone'   => self::TONE_SUCCESS,
                'detail' => __('Live environment — changes affect real visitors.', 'corex'),
            ],
            'staging' => [
                'mode'   => 'staging',
                'label'  => __('Staging', 'corex'),
                'tone'   => self::TONE_WARNING,
                'detail' => __('Pre-production — safe to rehearse launch steps.', 'corex'),
            ],
            'development', 'local' => [
                'mode'   => $type,
                'label'  => $type === 'local' ? __('Local', 'corex') : __('Development', 'corex'),
                'tone'   => self::TONE_INFO,
                'detail' => __('Not a live site — outbound email and analytics should be off.', 'corex'),
            ],
            // WordPress defaults unknown/empty values to "production"; mirror that honestly rather
            // than inventing a mode we cannot verify.
            default => [
                'mode'   => 'production',
                'label'  => __('Production', 'corex'),
                'tone'   => self::TONE_SUCCESS,
                'detail' => __('Live environment — changes affect real visitors.', 'corex'),
            ],
        };
    }
}
