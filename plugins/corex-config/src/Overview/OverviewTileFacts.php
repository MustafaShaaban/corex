<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Addons\AddonRegistry;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Health\FatalErrorLog;
use Corex\Health\FatalErrorSource;
use Corex\Health\HealthModule;
use Corex\Health\HealthStatus;
use Corex\Multisite\PluginActivationInspector;

/**
 * Reads the facts behind the Overview's state tiles (spec 118, US-OVR-04): CoreX's own health
 * checks, the last PHP fatal error CoreX recorded, the operations mode beside the WordPress
 * environment type, and the CoreX add-ons that run. Each fact is read on its own and one that
 * cannot be read is null, which its tile says as "Could not check".
 */
final class OverviewTileFacts
{
    /** Health checks that are advice, not health: a site with no `brand.json` is not unwell. */
    private const ADVISORY_PROBES = ['brand_present', 'theme_active'];

    private const FATAL_WINDOW_DAYS = 7;

    public function __construct(
        private readonly HealthModule $health,
        private readonly FatalErrorLog $fatals,
        private readonly OperationsMode $modes,
        private readonly OperationsModeStore $modeStore,
        private readonly AddonRegistry $addons,
        private readonly PluginActivationInspector $plugins,
    ) {
    }

    /**
     * @return array{
     *   health: array{issues:list<string>,fatal:array{date:string,source:string}|null}|null,
     *   environment: array{label:string,detail:string,wpType:string,differs:bool}|null,
     *   addons: array{active:int,total:int}|null
     * }
     */
    public function gather(int $now): array
    {
        return [
            'health'      => $this->guarded(fn (): array => [
                'issues' => $this->issues(),
                'fatal'  => $this->fatal($now),
            ]),
            'environment' => $this->guarded($this->environment(...)),
            'addons'      => $this->guarded($this->addonCounts(...)),
        ];
    }

    /** @return list<string> */
    private function issues(): array
    {
        $issues = [];
        foreach ($this->health->report()->results() as $result) {
            if ($result->status !== HealthStatus::Good && ! in_array($result->id, self::ADVISORY_PROBES, true)) {
                $issues[] = $result->description;
            }
        }

        return $issues;
    }

    /**
     * The last fatal error of the last seven days. Which plugin or theme it came from is for an
     * administrator only (OVR-D3); anyone else reads the date.
     *
     * @return array{date:string,source:string}|null
     */
    private function fatal(int $now): ?array
    {
        $latest = $this->fatals->latestSince($now - self::FATAL_WINDOW_DAYS * DAY_IN_SECONDS);
        if ($latest === null) {
            return null;
        }

        return [
            'date'   => (string) wp_date((string) get_option('date_format', 'Y-m-d'), $latest['time']),
            // "Access CoreX admin" comes only with site administration (spec 117).
            'source' => current_user_can(CorexAbility::MANAGE_ADMIN) ? $this->sourceName($latest['kind'], $latest['slug']) : '',
        ];
    }

    private function sourceName(string $kind, string $slug): string
    {
        return match ($kind) {
            FatalErrorSource::PLUGIN    => $this->pluginName($slug),
            FatalErrorSource::THEME     => wp_get_theme($slug)->exists() ? (string) wp_get_theme($slug)->get('Name') : $slug,
            FatalErrorSource::MU_PLUGIN => $slug,
            FatalErrorSource::CORE      => __('WordPress', 'corex'),
            default                     => '',
        };
    }

    private function pluginName(string $slug): string
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (get_plugins() as $file => $plugin) {
            if (dirname($file) === $slug || $file === $slug . '.php') {
                return (string) ($plugin['Name'] ?? $slug);
            }
        }

        return $slug;
    }

    /** @return array{label:string,detail:string,wpType:string,differs:bool} */
    private function environment(): array
    {
        $mode   = $this->modeStore->current();
        $about  = $this->modes->describe($mode);
        $wpType = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : '';

        return [
            'label'   => (string) $about['label'],
            'detail'  => (string) $about['detail'],
            'wpType'  => $wpType,
            // The same rule as the Operations screen's notice: only a declared mode can differ.
            'differs' => $this->modeStore->isDeclared() && $wpType !== '' && $wpType !== $mode,
        ];
    }

    /** @return array{active:int,total:int} */
    private function addonCounts(): array
    {
        $all    = $this->addons->all();
        $active = array_filter($all, fn ($addon): bool => $this->plugins->isActive($addon->pluginFile));

        return ['active' => count($active), 'total' => count($all)];
    }

    /**
     * @template T
     *
     * @param callable():T $read
     *
     * @return T|null
     */
    private function guarded(callable $read): mixed
    {
        try {
            return $read();
        } catch (\Throwable) {
            return null;
        }
    }
}
