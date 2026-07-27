<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Config\Addons\AddonManager;
use Corex\Config\Addons\AddonView;
use Corex\Container\ContainerInterface;
use Corex\Foundation\AddonStatus;
use Corex\Forms\Catalog\FormCatalog;
use Corex\Forms\Catalog\FormCatalogEntry;
use Corex\Notifications\NotificationProducerRegistry;
use Corex\Support\Config\ConfigInterface;
use Throwable;

/**
 * Resolves the live facts {@see CapabilityReport} summarises (spec 074, FR-5).
 *
 * The WordPress-facing half, kept separate so the shaping stays pure. Every source is optional and
 * resolved inside its own try/catch: a site without the Forms add-on gets a report with no forms
 * rather than a fatal on the screen that was supposed to explain what is missing.
 */
final class CapabilityFacts
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * The sources arrive already described for the actor who asked; everything else here is a fact
     * about the site rather than about the person reading it.
     *
     * @param  list<array<string,mixed>> $sources the catalog the caller has already described
     * @return array<string,mixed>
     */
    public function gather(array $sources): array
    {
        return [
            'forms'     => $this->forms(),
            'models'    => $this->models($sources),
            'addons'    => $this->addons(),
            'producers' => $this->producers(),
            'gaps'      => $this->gaps(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function forms(): array
    {
        try {
            /** @var FormCatalog $catalog */
            $catalog = $this->container->make(FormCatalog::class);

            return array_map(static fn (FormCatalogEntry $entry): array => [
                'slug'   => $entry->slug,
                'label'  => $entry->label,
                'source' => $entry->source,
                'active' => $entry->active,
            ], $catalog->all());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<array<string,mixed>> $sources
     * @return list<array<string,mixed>>
     */
    private function models(array $sources): array
    {
        return array_map(static fn (array $source): array => [
            'key'          => (string) ($source['key'] ?? ''),
            'label'        => (string) ($source['label'] ?? ''),
            'capabilities' => (array) ($source['capabilities'] ?? []),
        ], $sources);
    }

    /** @return list<array<string,mixed>> */
    private function addons(): array
    {
        try {
            /** @var AddonManager $manager */
            $manager = $this->container->make(AddonManager::class);

            // The same three buckets the Add-ons screen badges, from the same state derivation and
            // the same `status()`, so this summary and that screen cannot disagree about what is
            // running.
            return array_map(static fn (AddonView $view): array => [
                'slug'  => $view->addon->slug,
                'label' => $view->addon->label,
                'state' => match (true) {
                    ! $view->installed                      => 'not_installed',
                    $view->status() === AddonStatus::Active => 'active',
                    default                                 => 'inactive',
                },
            ], $manager->views($manager->state(), $manager->isInstalled(...)));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function producers(): array
    {
        try {
            /** @var NotificationProducerRegistry $registry */
            $registry = $this->container->make(NotificationProducerRegistry::class);

            return $registry->registeredKeys();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The configuration that is selected but not finished.
     *
     * Each of these is a state where CoreX looks configured and silently is not — the shape of
     * problem that took a form-submission outage to notice. Every gap names something to do and
     * where to do it; none of them repeats the value that is missing.
     *
     * @return list<array<string,mixed>>
     */
    private function gaps(): array
    {
        $gaps = [];

        try {
            $config = $this->container->make(ConfigInterface::class);
        } catch (Throwable) {
            return [];
        }

        $captchaDriver = (string) $config->get('captcha.driver', 'none');
        $keyDriver     = in_array($captchaDriver, ['recaptcha', 'turnstile', 'hcaptcha'], true);

        if ($keyDriver && trim((string) $config->get('captcha.secret', '')) === '') {
            $gaps[] = [
                'key'          => 'captcha.keys',
                'summary'      => __('A captcha provider is selected but its keys are missing, so it is not protecting your forms. The honeypot is still on.', 'corex'),
                'action_label' => __('Finish captcha setup', 'corex'),
                'action_url'   => admin_url('admin.php?page=corex-settings-config'),
            ];
        }

        if ((string) $config->get('updates.endpoint', '') === '') {
            $gaps[] = [
                'key'          => 'updates.endpoint',
                'summary'      => __('No update source is configured, so CoreX will not tell you when a new version is available.', 'corex'),
                'action_label' => __('Open Settings', 'corex'),
                'action_url'   => admin_url('admin.php?page=corex-settings-config'),
            ];
        }

        return $gaps;
    }
}
