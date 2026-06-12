<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

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

        return new HealthReport([
            new PhpVersionProbe(PHP_VERSION, self::MIN_PHP),
            new WpVersionProbe((string) get_bloginfo('version'), self::MIN_WP),
            new ThemeActiveProbe(function_exists('wp_is_block_theme') && wp_is_block_theme()),
            new BrandPresentProbe($brandPath !== '' && file_exists($brandPath)),
            new UploadsWritableProbe(empty($uploads['error']) && wp_is_writable($uploads['basedir'])),
        ]);
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
