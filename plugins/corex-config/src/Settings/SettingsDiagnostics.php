<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;

/**
 * The Advanced page's read-outs (spec 068 T203): real runtime facts, never stored, never made up.
 * Moved out of the screen class by spec 119.
 */
final class SettingsDiagnostics
{
    public function __construct(
        private readonly MultisiteContext $multisite,
        private readonly NetworkContext $network,
    ) {
    }

    public function value(string $key): string
    {
        return match ($key) {
            'advanced.php_version'  => PHP_VERSION,
            'advanced.wp_version'   => (string) get_bloginfo('version'),
            'advanced.environment'  => $this->environment((string) wp_get_environment_type()),
            'advanced.memory_limit' => (string) ini_get('memory_limit'),
            'advanced.multisite'    => $this->multisite(),
            default                 => '',
        };
    }

    /** WordPress's environment type in words (spec 119, US-SET-08: it was printed as its key). */
    private function environment(string $type): string
    {
        return match ($type) {
            'local'       => __('Local', 'corex'),
            'development' => __('Development', 'corex'),
            'staging'     => __('Staging', 'corex'),
            default       => __('Production', 'corex'),
        };
    }

    private function multisite(): string
    {
        if (! $this->multisite->enabled()) {
            return __('No', 'corex');
        }

        $siteCount = $this->network->siteCount();
        /* translators: 1: WordPress network ID, 2: number of sites in the network. */
        $format = _n(
            'Yes — network %1$s, %2$s site',
            'Yes — network %1$s, %2$s sites',
            $siteCount,
            'corex',
        );

        return sprintf(
            $format,
            number_format_i18n($this->network->id()),
            number_format_i18n($siteCount),
        );
    }
}
