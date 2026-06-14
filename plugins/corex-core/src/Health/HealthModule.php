<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

use Corex\Health\Probes\BlockAssetsProbe;
use Corex\Health\Probes\BrandPresentProbe;
use Corex\Health\Probes\PhpVersionProbe;
use Corex\Health\Probes\ThemeActiveProbe;
use Corex\Health\Probes\UploadsWritableProbe;
use Corex\Health\Probes\WpVersionProbe;
use Corex\Support\Config\ConfigInterface;

/**
 * The WordPress boundary for the health engine: it builds the probes from live WP values, exposes
 * a {@see HealthReport}, and registers each probe into the **Site Health** screen
 * (`site_status_tests`). The pure aggregation/judgement lives in {@see HealthReport} + the probes;
 * this class only reads the environment and maps results onto WordPress's test shape.
 */
final class HealthModule
{
    private const MIN_PHP = '8.3';
    private const MIN_WP  = '7.0';

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function register(): void
    {
        add_filter('site_status_tests', [$this, 'registerTests']);
    }

    public function report(): HealthReport
    {
        $uploads   = wp_get_upload_dir();
        $brandPath = (string) $this->config->get('theme.brand_path', '');

        if ($brandPath === '' && function_exists('get_theme_file_path')) {
            $brandPath = (string) get_theme_file_path('brand.json');
        }

        $probes = [
            new PhpVersionProbe(PHP_VERSION, self::MIN_PHP),
            new WpVersionProbe((string) get_bloginfo('version'), self::MIN_WP),
            new ThemeActiveProbe(function_exists('wp_is_block_theme') && wp_is_block_theme()),
            new BrandPresentProbe($brandPath !== '' && file_exists($brandPath)),
            new UploadsWritableProbe(empty($uploads['error']) && wp_is_writable($uploads['basedir'])),
            new BlockAssetsProbe($this->blockAssets(), function_exists('plugins_url') ? plugins_url() : ''),
        ];

        // Optional add-ons (e.g. corex-media) contribute their own probes without core depending on
        // them. Each contributed item must be a HealthProbe; anything else is ignored.
        if (function_exists('apply_filters')) {
            $extended = apply_filters('corex_health_probes', $probes);
            if (is_array($extended)) {
                $probes = array_values(array_filter($extended, static fn ($p): bool => $p instanceof HealthProbe));
            }
        }

        return new HealthReport($probes);
    }

    /**
     * Collect every registered `corex/*` block's script/style asset URLs (spec 040), so the probe can flag any
     * that embed a filesystem path. Reads only — guarded so it is a no-op before the block/asset APIs exist.
     *
     * @return list<array{name:string,urls:list<string>}>
     */
    private function blockAssets(): array
    {
        if (! class_exists('WP_Block_Type_Registry') || ! function_exists('wp_scripts') || ! function_exists('wp_styles')) {
            return [];
        }

        $scripts = wp_scripts();
        $styles  = wp_styles();
        $blocks  = [];

        foreach (\WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
            if (strpos((string) $name, 'corex/') !== 0) {
                continue;
            }

            $urls = [];

            foreach (['editor_script_handles', 'view_script_handles', 'script_handles'] as $property) {
                foreach (($type->{$property} ?? []) as $handle) {
                    $src = $scripts->registered[$handle]->src ?? '';

                    if ($src !== '') {
                        $urls[] = (string) $src;
                    }
                }
            }

            foreach (['editor_style_handles', 'style_handles'] as $property) {
                foreach (($type->{$property} ?? []) as $handle) {
                    $src = $styles->registered[$handle]->src ?? '';

                    if ($src !== '') {
                        $urls[] = (string) $src;
                    }
                }
            }

            $blocks[] = ['name' => (string) $name, 'urls' => $urls];
        }

        return $blocks;
    }

    /**
     * @param array<string,mixed> $tests
     *
     * @return array<string,mixed>
     */
    public function registerTests(array $tests): array
    {
        foreach ($this->report()->results() as $result) {
            $tests['direct']['corex_' . $result->id] = [
                'label' => $result->label,
                'test'  => function () use ($result): array {
                    return $this->toSiteHealthTest($result);
                },
            ];
        }

        return $tests;
    }

    /**
     * @return array<string,mixed>
     */
    private function toSiteHealthTest(ProbeResult $result): array
    {
        $color = match ($result->status) {
            HealthStatus::Good => 'blue',
            HealthStatus::Recommended => 'orange',
            HealthStatus::Critical => 'red',
        };

        $description = '<p>' . esc_html($result->description) . '</p>';

        foreach ($result->actions as $action) {
            $description .= '<p>' . esc_html($action) . '</p>';
        }

        return [
            'label'       => esc_html($result->label),
            'status'      => $result->status->value,
            'badge'       => ['label' => esc_html__('Corex', 'corex'), 'color' => $color],
            'description' => $description,
            'actions'     => '',
            'test'        => 'corex_' . $result->id,
        ];
    }
}
